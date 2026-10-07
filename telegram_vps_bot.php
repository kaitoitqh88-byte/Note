<?php
/**
 * Telegram VPS Interaction Bot
 *
 * Features:
 * - /domains: List domains from aaPanel vhost configs
 * - /count_domains: Count unique domains
 * - /uptime: Show server uptime
 * - /help: Show command list
 *
 * Setup:
 * 1) Create .env with TELEGRAM_BOT_TOKEN and optional TELEGRAM_ALLOWED_CHAT_ID
 * 2) Run: php telegram_vps_bot.php
 */

declare(strict_types=1);

final class TelegramVpsBot
{
    private string $token;
    private ?string $allowedChatId;
    private ?string $caBundlePath;
    private bool $insecureSsl;
    private int $offset = 0;

    public function __construct(array $env)
    {
        $this->token = trim((string)($env['TELEGRAM_BOT_TOKEN'] ?? ''));
        $allowed = trim((string)($env['TELEGRAM_ALLOWED_CHAT_ID'] ?? ''));
        $this->allowedChatId = $allowed !== '' ? $allowed : null;
        $caBundle = trim((string)($env['CURL_CA_BUNDLE'] ?? ''));
        $this->caBundlePath = $caBundle !== '' ? $caBundle : null;
        $this->insecureSsl = strtolower(trim((string)($env['TELEGRAM_INSECURE_SSL'] ?? 'false'))) === 'true';

        if ($this->token == '') {
            throw new RuntimeException('Missing TELEGRAM_BOT_TOKEN in .env');
        }
    }

    public function run(bool $once = false): void
    {
        $this->log('Bot started. Waiting for messages...');

        do {
            $updates = $this->getUpdates($this->offset, 25);
            foreach ($updates as $update) {
                $this->handleUpdate($update);
                $this->offset = max($this->offset, ((int)($update['update_id'] ?? 0)) + 1);
            }
        } while (!$once);
    }

    private function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message || !isset($message['chat']['id'])) {
            return;
        }

        $chatId = (string)$message['chat']['id'];
        $text = trim((string)($message['text'] ?? ''));

        if ($text === '') {
            return;
        }

        if ($this->allowedChatId !== null && $chatId !== $this->allowedChatId) {
            $this->sendMessage($chatId, 'Unauthorized chat ID. Access denied.');
            return;
        }

        $command = strtolower(explode(' ', $text)[0]);
        $this->log('Command from ' . $chatId . ': ' . $command);

        switch ($command) {
            case '/start':
            case '/help':
                $this->sendMessage($chatId, $this->helpText());
                break;
            case '/domains':
                $this->replyDomains($chatId);
                break;
            case '/count_domains':
                $domains = $this->collectDomains();
                $this->sendMessage($chatId, 'Total unique domains: ' . count($domains));
                break;
            case '/uptime':
                $this->sendMessage($chatId, $this->serverUptime());
                break;
            default:
                $this->sendMessage($chatId, 'Unknown command. Use /help');
                break;
        }
    }

    private function helpText(): string
    {
        return implode("\n", [
            'VPS Interaction Bot',
            '',
            'Commands:',
            '/domains - List all unique domains from aaPanel vhost configs',
            '/count_domains - Show number of unique domains',
            '/uptime - Show server uptime',
            '/help - Show this help',
        ]);
    }

    private function replyDomains(string $chatId): void
    {
        $domains = $this->collectDomains();

        if ($domains === []) {
            $this->sendMessage($chatId, 'No domains found in configured vhost paths.');
            return;
        }

        $header = 'Found ' . count($domains) . ' unique domains:';
        $payload = $header . "\n\n" . implode("\n", $domains);

        // Telegram max text length is 4096 chars; use safe chunks.
        foreach ($this->chunkMessage($payload, 3500) as $chunk) {
            $this->sendMessage($chatId, $chunk);
        }
    }

    private function collectDomains(): array
    {
        $pathsRaw = getenv('VPS_DOMAIN_PATHS');
        $paths = $pathsRaw !== false && trim($pathsRaw) !== ''
            ? array_map('trim', explode(',', $pathsRaw))
            : [
                '/www/server/panel/vhost/nginx',
                '/www/server/panel/vhost/apache',
                '/www/server/panel/vhost/openlitespeed',
            ];

        $skip = [
            '0.default',
            '0.fastcgi_cache',
            '0.site_total_log_format',
            '0.websocket',
            'phpmyadmin',
            'phpfpm_status',
            'speed',
            'btwaf',
            'waf2monitor_data',
            'default',
        ];

        $found = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $items = scandir($path);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $full = $path . DIRECTORY_SEPARATOR . $item;
                if (!is_file($full)) {
                    continue;
                }

                if (preg_match('/^(.+)\\.conf(?:\\.bar\\.bar|\\.bak|0|\\.bar)?$/', $item, $m) !== 1) {
                    continue;
                }

                $domain = trim($m[1]);
                if ($domain === '' || in_array($domain, $skip, true)) {
                    continue;
                }

                $found[$domain] = true;
            }
        }

        $domains = array_keys($found);
        sort($domains, SORT_STRING);

        return $domains;
    }

    private function serverUptime(): string
    {
        $uptime = @shell_exec('uptime -p 2>/dev/null');
        if (!is_string($uptime) || trim($uptime) === '') {
            $uptime = @shell_exec('cat /proc/uptime 2>/dev/null');
            if (!is_string($uptime) || trim($uptime) === '') {
                return 'Could not read uptime.';
            }
            return 'Uptime data: ' . trim($uptime);
        }

        return 'Server ' . trim($uptime);
    }

    private function getUpdates(int $offset, int $timeout): array
    {
        $result = $this->apiRequest('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'edited_message'], JSON_UNESCAPED_SLASHES),
        ]);

        return $result['result'] ?? [];
    }

    private function sendMessage(string $chatId, string $text): void
    {
        $this->apiRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);
    }

    private function apiRequest(string $method, array $params): array
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;

        $ch = curl_init($url);
        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($this->caBundlePath !== null && is_file($this->caBundlePath)) {
            $options[CURLOPT_CAINFO] = $this->caBundlePath;
        }

        if ($this->insecureSsl) {
            // Use only for local troubleshooting when CA trust store is broken.
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->log('cURL error (' . $errno . '): ' . $error);
            return [];
        }

        if ($status < 200 || $status >= 300) {
            $this->log('HTTP ' . $status . ' for method ' . $method);
            return [];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            $this->log('Invalid JSON from Telegram API.');
            return [];
        }

        if (($decoded['ok'] ?? false) !== true) {
            $this->log('Telegram API returned not ok for method ' . $method);
            return [];
        }

        return $decoded;
    }

    private function chunkMessage(string $text, int $maxLen): array
    {
        $lines = explode("\n", $text);
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : ($current . "\n" . $line);
            if (strlen($candidate) <= $maxLen) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
                $current = $line;
            } else {
                // Fallback for very long single line.
                $chunks[] = substr($line, 0, $maxLen);
                $current = substr($line, $maxLen);
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function log(string $message): void
    {
        $stamp = date('Y-m-d H:i:s');
        writeConsole('[' . $stamp . '] ' . $message);
    }
}

function writeConsole(string $message, bool $isError = false): void
{
    static $stdout = null;
    static $stderr = null;

    if ($isError) {
        if ($stderr === null) {
            $stderr = defined('STDERR') ? STDERR : @fopen('php://stderr', 'wb');
        }
        if (is_resource($stderr)) {
            fwrite($stderr, $message . PHP_EOL);
            return;
        }

        error_log($message);
        return;
    }

    if ($stdout === null) {
        $stdout = defined('STDOUT') ? STDOUT : @fopen('php://stdout', 'wb');
    }
    if (is_resource($stdout)) {
        fwrite($stdout, $message . PHP_EOL);
        return;
    }

    error_log($message);
}

function loadEnvFile(string $filePath): void
{
    if (!is_file($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $val = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        putenv($key . '=' . $val);
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

loadEnvFile(__DIR__ . DIRECTORY_SEPARATOR . '.env');

$once = in_array('--once', $argv ?? [], true);

try {
    $bot = new TelegramVpsBot($_ENV + $_SERVER);
    $bot->run($once);
} catch (Throwable $e) {
    writeConsole('[fatal] ' . $e->getMessage(), true);
    exit(1);
}
