<?php
/**
 * Simple WHOIS lookup utility
 *
 * Features:
 * - Queries WHOIS servers over port 43
 * - Uses a small built-in TLD -> WHOIS server map for common TLDs
 * - Falls back to whois.iana.org and follows referral servers when present
 * - Usable from CLI or programmatically via Whois::lookup($domain)
 *
 * Usage (CLI):
 *   php whois.php example.com
 *
 * Example (programmatic):
 *   echo \Whois::lookup('example.com');
 */

class Whois
{
    protected static $servers = [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'info' => 'whois.afilias.net',
        'biz' => 'whois.neulevel.biz',
        'us' => 'whois.nic.us',
        'uk' => 'whois.nic.uk',
        'co' => 'whois.nic.co',
        'io' => 'whois.nic.io',
        'me' => 'whois.nic.me',
        'tv' => 'tvwhois.verisign-grs.com',
        'cn' => 'whois.cnnic.cn',
        'de' => 'whois.denic.de',
        'ru' => 'whois.tcinet.ru',
        'br' => 'whois.registro.br',
        'au' => 'whois.auda.org.au',
        'vn' => 'whois.nic.vn',
        // add more as needed
    ];

    /**
     * Perform a WHOIS lookup for a domain.
     * Returns the raw WHOIS response (string).
     *
     * @param string $domain
     * @param int $timeout seconds for socket connection
     * @return string
     * @throws InvalidArgumentException
     */
    public static function lookup(string $domain, int $timeout = 10): string
    {
        $domain = strtolower(trim($domain));
        // strip scheme and path
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('/:^\d+$/', '', $domain);
        // basic validation
        if (!preg_match('/^[a-z0-9\-\.]+$/', $domain)) {
            throw new InvalidArgumentException('Invalid domain format');
        }
        if (strpos($domain, '.') === false) {
            throw new InvalidArgumentException('Domain must contain a TLD (e.g. example.com)');
        }

        $parts = explode('.', $domain);
        $tld = array_pop($parts);

        $server = self::$servers[$tld] ?? 'whois.iana.org';

        $response = self::queryServer($server, $domain, $timeout);

        // If we used IANA or the response contains a referral, try following it
        $referral = self::findReferral($response);
        if ($referral && stripos($referral, $server) === false) {
            $follow = self::queryServer($referral, $domain, $timeout);
            // combine responses for transparency
            return "# WHOIS server: {$server}\n" . $response . "\n# Referral WHOIS server: {$referral}\n" . $follow;
        }

        // In some cases the TLD server (like verisign) will include a line pointing to the registrar whois server
        $referral2 = self::findReferral($response);
        if ($referral2 && stripos($referral2, $server) === false) {
            $follow2 = self::queryServer($referral2, $domain, $timeout);
            return "# WHOIS server: {$server}\n" . $response . "\n# Referral WHOIS server: {$referral2}\n" . $follow2;
        }

        return "# WHOIS server: {$server}\n" . $response;
    }

    /**
     * Parse a raw WHOIS response into structured data.
     * Returns an associative array with keys: status, registrar, created, expires, statuses, nameservers, raw
     * Status will be either 'available' or 'registered' (best-effort detection).
     *
     * @param string $response
     * @param string $domain Optional domain to include in result
     * @return array
     */
    public static function parse(string $response, string $domain = ''): array
    {
        $res = [
            'domain' => $domain,
            'status' => 'unknown',
            'registrar' => [
                'name' => null,
                'iana_id' => null,
                'email' => null,
                'abuse_email' => null,
                'abuse_phone' => null,
            ],
            'created' => null,
            'updated' => null,
            'expires' => null,
            'statuses' => [],
            'nameservers' => [],
            'contacts' => [
                'registrant' => [],
                'admin' => [],
                'tech' => [],
            ],
            'raw' => trim($response),
        ];

        $lc = strtolower($response);
        $notFoundIndicators = [
            'no match for', 'not found', 'no entries found', 'no data found', 'status: free', 'is available for purchase', 'no whois server', 'we do not have an entry', "no such domain",
            'domain not found', "not registered", "available\n"
        ];
        foreach ($notFoundIndicators as $pat) {
            if (strpos($lc, $pat) !== false) {
                $res['status'] = 'available';
                return $res;
            }
        }

        $res['status'] = 'registered';

        // Registrar name
        if (preg_match('/^\s*Registrar:\s*(.+)$/im', $response, $m)) {
            $res['registrar']['name'] = trim($m[1]);
        } elseif (preg_match('/^\s*Sponsoring Registrar:\s*(.+)$/im', $response, $m)) {
            $res['registrar']['name'] = trim($m[1]);
        }

        // Registrar IANA ID
        if (preg_match('/(?:IANA ID|Registrar IANA ID):\s*(\d+)/i', $response, $m)) {
            $res['registrar']['iana_id'] = trim($m[1]);
        }

        // Registrar emails / abuse
        if (preg_match('/Registrar Abuse Contact Email:\s*(\S+)/i', $response, $m)) {
            $res['registrar']['abuse_email'] = trim($m[1]);
            $res['registrar']['email'] = $res['registrar']['abuse_email'];
        } elseif (preg_match('/Registrar Abuse Email:\s*(\S+)/i', $response, $m)) {
            $res['registrar']['abuse_email'] = trim($m[1]);
            $res['registrar']['email'] = $res['registrar']['abuse_email'];
        } elseif (preg_match('/Registrar Contact Email:\s*(\S+)/i', $response, $m)) {
            $res['registrar']['email'] = trim($m[1]);
        }
        if (preg_match('/Registrar Abuse Contact Phone:\s*(.+)/i', $response, $m)) {
            $res['registrar']['abuse_phone'] = trim($m[1]);
        }

        // Dates
        if (preg_match('/^(?:Creation Date|Created On|Registered On|Created):\s*(.+)$/im', $response, $m)) {
            $res['created'] = trim($m[1]);
        }
        if (preg_match('/^(?:Updated Date|Last Updated|Updated On|Domain Last Updated):\s*(.+)$/im', $response, $m)) {
            $res['updated'] = trim($m[1]);
        }
        if (preg_match('/^(?:Registry Expiry Date|Expiry Date|Expiration Date|Registrar Registration Expiration Date|Expires On|paid-till|expires):\s*(.+)$/im', $response, $m)) {
            $res['expires'] = trim($m[1]);
        }

        // Status lines
        if (preg_match_all('/^(?:Domain Status|Status):\s*(.+)$/im', $response, $m)) {
            foreach ($m[1] as $s) {
                $s = trim($s);
                if ($s !== '') $res['statuses'][] = $s;
            }
        }

        // Nameservers
        if (preg_match_all('/^\s*(?:Name Server|Nameserver|nserver):\s*(.+)$/im', $response, $m)) {
            foreach ($m[1] as $ns) {
                $ns = trim($ns);
                if ($ns !== '') $res['nameservers'][] = $ns;
            }
        }

        // Helper to extract contacts
        $lines = preg_split('/\r?\n/', $response);
        $roles = [
            'registrant' => ['Registrant', 'Registrant Contact', 'Registrant Contact Details'],
            'admin' => ['Admin', 'Administrative Contact', 'Administrative Contact\s*'],
            'tech' => ['Tech', 'Technical Contact', 'Tech Contact'],
        ];
        foreach ($roles as $key => $labels) {
            $contact = [
                'name' => null,
                'organization' => null,
                'street' => [],
                'city' => null,
                'state' => null,
                'postal' => null,
                'country' => null,
                'phone' => null,
                'fax' => null,
                'email' => null,
            ];
            foreach ($lines as $line) {
                foreach ($labels as $label) {
                    if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*([^:]+):\s*(.+)$/i', $line, $m)) {
                        $field = strtolower(trim($m[1]));
                        $val = trim($m[2]);
                        if ($val === '') continue;
                        if (strpos($field, 'name') !== false) $contact['name'] = $val;
                        elseif (strpos($field, 'org') !== false) $contact['organization'] = $val;
                        elseif (strpos($field, 'street') !== false || strpos($field, 'address') !== false) $contact['street'][] = $val;
                        elseif (strpos($field, 'city') !== false) $contact['city'] = $val;
                        elseif (strpos($field, 'state') !== false || strpos($field, 'province') !== false || strpos($field, 'region') !== false) $contact['state'] = $val;
                        elseif (strpos($field, 'postal') !== false || strpos($field, 'zip') !== false) $contact['postal'] = $val;
                        elseif (strpos($field, 'country') !== false) $contact['country'] = $val;
                        elseif (strpos($field, 'phone') !== false && $contact['phone'] === null) $contact['phone'] = $val;
                        elseif (strpos($field, 'fax') !== false) $contact['fax'] = $val;
                        elseif (strpos($field, 'email') !== false) $contact['email'] = $val;
                    }
                }
            }
            // flatten street
            $contact['street'] = $contact['street'] ? implode('\n', $contact['street']) : null;
            $res['contacts'][$key] = $contact;
        }

        return $res;
    }

    protected static function queryServer(string $server, string $domain, int $timeout): string
    {
        $port = 43;
        $errno = 0;
        $errstr = '';

        $fp = @fsockopen($server, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return "Error connecting to {$server}: {$errstr} ({$errno})\n";
        }

        stream_set_timeout($fp, $timeout);

        // Many servers expect just the domain, some expect special prefixes (e.g. verisign doesn't need anything special)
        fwrite($fp, $domain . "\r\n");

        $response = '';
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($line === false) break;
            $response .= $line;
        }

        fclose($fp);

        return $response;
    }

    /**
     * Normalize various date strings into YYYY-MM-DD when possible.
     */
    public static function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') return null;
        $ts = strtotime(trim($date));
        if ($ts === false) return trim($date);
        return date('Y-m-d', $ts);
    }

    /**
     * Try to find a referral WHOIS server from a WHOIS response
     * Looks for common labels like "Whois Server:", "whois:" or "refer:"
     *
     * @param string $response
     * @return string|null
     */
    protected static function findReferral(string $response): ?string
    {
        $lines = preg_split('/\r?\n/', $response);
        foreach ($lines as $line) {
            if (stripos($line, 'whois server:') !== false || stripos($line, 'whois:') !== false) {
                $parts = preg_split('/[:\s]+/', $line, 2);
                if (isset($parts[1])) {
                    $server = trim($parts[1]);
                    // clean up like "whois://whois.nic.example"
                    $server = preg_replace('#^whois://#i', '', $server);
                    $server = preg_replace('#^https?://#i', '', $server);
                    $server = preg_replace('#/.*$#', '', $server);
                    if ($server !== '') return $server;
                }
            }
            if (stripos($line, 'refer:') !== false) {
                $parts = preg_split('/[:\s]+/', $line, 2);
                if (isset($parts[1])) {
                    $server = trim($parts[1]);
                    $server = preg_replace('#/.*$#', '', $server);
                    if ($server !== '') return $server;
                }
            }
        }
        return null;
    }
}

// CLI support
if (php_sapi_name() === 'cli' && isset($argv) && count($argv) > 1) {
    array_shift($argv); // script name
    foreach ($argv as $d) {
        try {
            $out = Whois::lookup($d);
            echo "===== WHOIS for: {$d} =====\n";
            echo $out . "\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    // Web form support
    $domainInput = '';
    $rawResponse = '';
    $structured = null;
    $jsonOutput = '';

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain'])) {
        $domainInput = trim((string)$_POST['domain']);
        if ($domainInput !== '') {
            try {
                $rawResponse = Whois::lookup($domainInput);
                $structured = Whois::parse($rawResponse, $domainInput);
                $jsonOutput = json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                $rawResponse = 'Error: ' . $e->getMessage();
            }
        } else {
            $rawResponse = 'Please enter a domain.';
        }
    } elseif (isset($_GET['domain'])) {
        // support quick GET lookups like ?domain=example.com
        $domainInput = trim((string)$_GET['domain']);
        if ($domainInput !== '') {
            try {
                $rawResponse = Whois::lookup($domainInput);
                $structured = Whois::parse($rawResponse, $domainInput);
                $jsonOutput = json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                $rawResponse = 'Error: ' . $e->getMessage();
            }
        }
    }

    // Render HTML form
    echo '<!doctype html><html><head><meta charset="utf-8"><title>WHOIS Lookup</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;margin:20px}input[type=text]{width:400px;padding:8px}textarea{width:100%;height:300px;margin-top:10px;padding:8px;font-family:monospace} .status-available{color:#0a0} .status-registered{color:#a00}</style>';
    echo '</head><body>';
    echo '<h1>WHOIS Lookup</h1>';
    echo '<form method="post" action="">';
    echo '<label for="domain">Domain:</label> ';
    echo '<input id="domain" name="domain" type="text" value="'.htmlspecialchars($domainInput, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'"> ';
    echo '<button type="submit">Lookup</button>';
    echo '</form>';

    if ($rawResponse !== '') {
        echo '<h2>Result</h2>';
        if (is_array($structured)) {
            $status = $structured['status'] ?? 'unknown';
            $statusClass = ($status === 'available') ? 'status-available' : ($status === 'registered' ? 'status-registered' : '');
            echo '<p>Status: <strong class="'.htmlspecialchars($statusClass).'">'.htmlspecialchars($status).'</strong></p>';

            echo '<h3>Parsed</h3>';

            // Build formatted plain-text output similar to requested layout
            $fmt = '';
            $fmt .= "Domain:\n" . ($structured['domain'] ?? $domainInput) . "\n";
            $fmt .= "Registered On:\n" . (Whois::normalizeDate($structured['created']) ?? ($structured['created'] ?? '')) . "\n";
            $fmt .= "Expires On:\n" . (Whois::normalizeDate($structured['expires']) ?? ($structured['expires'] ?? '')) . "\n";
            $fmt .= "Updated On:\n" . (Whois::normalizeDate($structured['updated']) ?? ($structured['updated'] ?? '')) . "\n";

            // Status (use statuses if available, else generic status)
            $statusLine = '';
            if (!empty($structured['statuses'])) {
                $statusLine = implode(', ', $structured['statuses']);
            } else {
                $statusLine = $structured['status'] ?? 'unknown';
            }
            $fmt .= "Status:\n" . ($statusLine) . "\n";

            // Nameservers
            $fmt .= "Name Servers:\n";
            if (!empty($structured['nameservers'])) {
                foreach ($structured['nameservers'] as $ns) {
                    $fmt .= trim($ns) . "\n";
                }
            }

            $fmt .= "\nRegistrar Information\n";
            $fmt .= "Registrar:\n" . ($structured['registrar']['name'] ?? '') . "\n";
            $fmt .= "IANA ID:\n" . ($structured['registrar']['iana_id'] ?? '') . "\n";
            $fmt .= "Email:\n" . ($structured['registrar']['email'] ?? '') . "\n";
            $fmt .= "Abuse Email:\n" . ($structured['registrar']['abuse_email'] ?? '') . "\n";
            $fmt .= "Abuse Phone:\n" . ($structured['registrar']['abuse_phone'] ?? '') . "\n";

            // Contacts
            $rolesMap = [
                'registrant' => 'Registrant Contact',
                'admin' => 'Administrative Contact',
                'tech' => 'Technical Contact',
            ];
            foreach ($rolesMap as $key => $title) {
                $c = $structured['contacts'][$key] ?? [];
                // skip if empty
                $isEmpty = true;
                foreach ($c as $v) { if (!empty($v)) { $isEmpty = false; break; } }
                if ($isEmpty) continue;

                $fmt .= "\n" . $title . "\n";
                if (!empty($c['street'])) {
                    $fmt .= "Street:\n" . $c['street'] . "\n";
                }
                $fmt .= "City:\n" . ($c['city'] ?? '') . "\n";
                $fmt .= "State:\n" . ($c['state'] ?? '') . "\n";
                $fmt .= "Country:\n" . ($c['country'] ?? '') . "\n";
                $fmt .= "Phone:\n" . ($c['phone'] ?? '') . "\n";
                $fmt .= "Fax:\n" . ($c['fax'] ?? '') . "\n";
                $fmt .= "Email:\n" . ($c['email'] ?? '') . "\n";
            }

            echo '<pre style="background:#f9f9f9;padding:12px;border:1px solid #ddd">'.htmlspecialchars($fmt, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</pre>';

            echo '<h3>Raw WHOIS</h3>';
            echo '<textarea readonly>'.htmlspecialchars($rawResponse, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</textarea>';
        } else {
            echo '<textarea readonly>'.htmlspecialchars($rawResponse, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</textarea>';
        }
    }

    echo '</body></html>';
}
