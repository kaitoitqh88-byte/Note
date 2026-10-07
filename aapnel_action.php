<?php
/**
 * aaPanel actions endpoint
 * - Update aaPanel account password in Data_Config/TK_aapanel.json and Data_Config/TK_aapanel.txt
 */

require_once 'APISecretKeyManager.php';
require_once 'APIKeyAuthInterface.php';

session_start();
$keyManager = new APISecretKeyManager();
$authResult = $keyManager->validateSession();

if (!$authResult['valid']) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$permissions = $authResult['permissions'] ?? [];
if (!in_array('admin', $permissions, true)) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Admin permission required'
    ]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$jsonFile = 'Data_Config/TK_aapanel.json';
$txtFile = 'Data_Config/TK_aapanel.txt';

function normalizeLink($link) {
    return rtrim(trim((string) $link), '/');
}

function extractHostFromLink($link) {
    $host = parse_url((string) $link, PHP_URL_HOST);
    return $host ? strtolower($host) : '';
}

function isValidPassword($password) {
    $len = strlen($password);
    return $len >= 6 && $len <= 255;
}

function loadAapanelJson($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception('TK_aapanel.json not found');
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) {
        throw new Exception('Cannot read TK_aapanel.json');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON format in TK_aapanel.json');
    }

    return $data;
}

function saveAapanelJson($filePath, array $data) {
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new Exception('Failed to encode JSON');
    }

    if (file_put_contents($filePath, $encoded) === false) {
        throw new Exception('Cannot write TK_aapanel.json');
    }
}

function updateTxtPasswords($filePath, array $matchHosts, $newPassword) {
    if (!file_exists($filePath)) {
        return 0;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new Exception('Cannot read TK_aapanel.txt');
    }

    $updated = 0;
    foreach ($lines as $idx => $line) {
        if (trim($line) === '') {
            continue;
        }

        $parts = explode("\t", $line);
        if (count($parts) < 5) {
            $parts = preg_split('/\s+/', trim($line), 5);
        }
        if (!is_array($parts) || count($parts) < 5) {
            continue;
        }

        $host = extractHostFromLink($parts[0]);
        if ($host !== '' && isset($matchHosts[$host])) {
            $parts[2] = $newPassword;
            $lines[$idx] = implode("\t", $parts);
            $updated++;
        }
    }

    if ($updated > 0) {
        $output = implode(PHP_EOL, $lines) . PHP_EOL;
        if (file_put_contents($filePath, $output) === false) {
            throw new Exception('Cannot write TK_aapanel.txt');
        }
    }

    return $updated;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'update_aapanel_password') {
        throw new Exception('Invalid action');
    }

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if ($identifier === '') {
        throw new Exception('identifier is required (IP or link_aapanel)');
    }

    if (!isValidPassword($newPassword)) {
        throw new Exception('new_password must be 6-255 characters');
    }

    $identifierNorm = normalizeLink($identifier);
    $identifierHost = extractHostFromLink($identifierNorm);
    if ($identifierHost === '' && filter_var($identifierNorm, FILTER_VALIDATE_IP)) {
        $identifierHost = strtolower($identifierNorm);
    }

    $rows = loadAapanelJson($jsonFile);
    $matched = 0;
    $matchHosts = [];

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }

        $link = normalizeLink($row['link_aapanel'] ?? '');
        $host = extractHostFromLink($link);

        $matchByLink = ($identifierNorm !== '' && $link !== '' && strcasecmp($identifierNorm, $link) === 0);
        $matchByHost = ($identifierHost !== '' && $host !== '' && strcasecmp($identifierHost, $host) === 0);

        if ($matchByLink || $matchByHost) {
            $row['password_aapanel'] = $newPassword;
            $matched++;
            if ($host !== '') {
                $matchHosts[strtolower($host)] = true;
            }
        }
    }
    unset($row);

    if ($matched === 0) {
        throw new Exception('No matching aaPanel account found for identifier');
    }

    saveAapanelJson($jsonFile, $rows);
    $txtUpdated = updateTxtPasswords($txtFile, $matchHosts, $newPassword);

    echo json_encode([
        'success' => true,
        'message' => 'Updated aaPanel password successfully',
        'updated_json_rows' => $matched,
        'updated_txt_rows' => $txtUpdated,
        'identifier' => $identifier,
        'updated_hosts' => array_keys($matchHosts)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
