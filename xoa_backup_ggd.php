<?php
declare(strict_types=1);

/**
 * Xoa backup cu tren Google Drive va giu lai 3 file moi nhat.
 *
 * Yeu cau:
 *   - composer require google/apiclient:^2.19
 *   - Tao OAuth client dang Web application trong Google Cloud Console.
 *   - Dat file credentials.json cung thu muc voi script (hoac GOOGLE_CREDENTIALS).
 *
 * Cau hinh tuy chon:
 *   GOOGLE_DRIVE_FOLDER_ID - mac dinh la thu muc backup da cau hinh
 *   GOOGLE_DRIVE_KEEP      - mac dinh 3
 *   GOOGLE_DRIVE_PATTERN    - mac dinh * (ho tro *.zip)
 *   GOOGLE_DRIVE_TOKEN      - noi luu token OAuth, mac dinh google-drive-token.json
 *
 * Lan dau mo script tren trinh duyet de cap quyen OAuth. Sau do:
 *   - Khong co tham so: xem truoc
 *   - Them ?delete=1: xoa cac file cu
 */

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
require_once __DIR__ . '/vendor/autoload.php';
// https://drive.google.com/drive/folders/1bnW0fAPCTDIkrZJDaRJy8b2wbc3pH0Df
const GOOGLE_DRIVE_FOLDER_ID = '1d58XmsijtgkUjw6UobGYkmcV-wE_RkUI';
const GOOGLE_DRIVE_DEFAULT_KEEP = 3;
const GOOGLE_DRIVE_DEFAULT_PATTERN = '*';
const GOOGLE_DRIVE_MAX_FOLDERS = 10000;

/**
 * @return array{folderId: string, keep: int, pattern: string, delete: bool}
 */
function readConfiguration(): array
{
    $folderId = trim((string) ($_GET['folder_id'] ?? getenv('GOOGLE_DRIVE_FOLDER_ID') ?: GOOGLE_DRIVE_FOLDER_ID));
    $keep = (int) ($_GET['keep'] ?? getenv('GOOGLE_DRIVE_KEEP') ?: GOOGLE_DRIVE_DEFAULT_KEEP);
    $pattern = trim((string) ($_GET['pattern'] ?? getenv('GOOGLE_DRIVE_PATTERN') ?: GOOGLE_DRIVE_DEFAULT_PATTERN));

    if ($folderId === '') {
        throw new InvalidArgumentException('Chua cau hinh Folder ID Google Drive.');
    }
    if ($keep < 0) {
        throw new InvalidArgumentException('GOOGLE_DRIVE_KEEP phai la so nguyen khong am.');
    }

    return [
        'folderId' => $folderId,
        'keep' => $keep,
        'pattern' => $pattern !== '' ? $pattern : GOOGLE_DRIVE_DEFAULT_PATTERN,
        'delete' => ($_GET['delete'] ?? '') === '1',
    ];
}

function createGoogleClient(): Google_Client
{
    $credentialsPath = getenv('GOOGLE_CREDENTIALS') ?: __DIR__ . '/credentials.json';
    if (!is_file($credentialsPath)) {
        throw new RuntimeException(
            "Khong tim thay file OAuth credentials: {$credentialsPath}. " .
            'Hay tai OAuth client JSON tu Google Cloud Console.'
        );
    }
    $credentialsJson = json_decode((string) file_get_contents($credentialsPath), true);
    if (!is_array($credentialsJson) || (!isset($credentialsJson['web']) && !isset($credentialsJson['installed']))) {
        throw new RuntimeException(
            "File OAuth credentials khong hop le hoac dang rong: {$credentialsPath}. " .
            'Hay tai lai OAuth Client ID JSON loai Web application.'
        );
    }

    $client = new Google_Client();
    $client->setAuthConfig($credentialsPath);
    $client->setScopes([Google_Service_Drive::DRIVE]);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setRedirectUri(currentUrl());
    $caBundle = getenv('CURL_CA_BUNDLE') ?: __DIR__ . '/cacert.pem';
    if (!is_file($caBundle) || !is_readable($caBundle)) {
        throw new RuntimeException(
            "Khong tim thay CA certificate bundle: {$caBundle}. " .
            'Hay dat CURL_CA_BUNDLE tro den file cacert.pem.'
        );
    }
    $client->setHttpClient(new GuzzleHttp\Client([
        'verify' => $caBundle,
        'connect_timeout' => 15,
        'timeout' => 45,
    ]));
    $forceAuth = ($_GET['auth'] ?? '') === '1';

    $tokenPath = getenv('GOOGLE_DRIVE_TOKEN') ?: __DIR__ . '/google-drive-token.json';
    if (!$forceAuth && is_file($tokenPath)) {
        $token = json_decode((string) file_get_contents($tokenPath), true);
        if (is_array($token)) {
            $client->setAccessToken($token);
        }
    }

    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode((string) $_GET['code']);
        if (isset($token['error'])) {
            throw new RuntimeException('OAuth loi: ' . (string) ($token['error_description'] ?? $token['error']));
        }
        $client->setAccessToken($token);
        if (file_put_contents($tokenPath, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException("Khong the luu token OAuth vao {$tokenPath}.");
        }
        header('Location: ' . currentUrl());
        exit;
    }

    if (!$forceAuth && $client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        if ($refreshToken !== null) {
            $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (file_put_contents($tokenPath, json_encode($client->getAccessToken(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
                throw new RuntimeException("Khong the cap nhat token OAuth vao {$tokenPath}.");
            }
        }
    }

    if ($forceAuth || !$client->getAccessToken()) {
        $authUrl = $client->createAuthUrl();
        throw new RuntimeException('CAN_CAP_QUYEN_OAUTH:' . $authUrl);
    }

    return $client;
}

function currentUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['PHP_SELF'] ?? '/xoa_backup_ggd.php');
}

/**
 * @return list<Google_Service_Drive_DriveFile>
 */
function findDriveBackups(Google_Service_Drive $service, string $folderId, string $pattern): array
{
    set_time_limit(0);
    $files = [];
    $foldersToVisit = [$folderId];
    $visitedFolders = [];

    while ($foldersToVisit !== []) {
        if (count($visitedFolders) >= GOOGLE_DRIVE_MAX_FOLDERS) {
            throw new RuntimeException(
                'Cay thu muc co qua nhieu thu muc con (gioi han ' .
                GOOGLE_DRIVE_MAX_FOLDERS . '). Hay thu hep Folder ID hoac pattern.'
            );
        }
        $currentFolderId = array_pop($foldersToVisit);
        if (isset($visitedFolders[$currentFolderId])) {
            continue;
        }
        $visitedFolders[$currentFolderId] = true;

        $query = sprintf(
            "'%s' in parents and trashed = false",
            addcslashes($currentFolderId, "\\'")
        );
        $pageToken = null;
        do {
            $result = $service->files->listFiles([
                'q' => $query,
                'orderBy' => 'modifiedTime desc, name',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
                'fields' => 'nextPageToken, files(id,name,modifiedTime,size,mimeType,webViewLink)',
            ]);
            foreach ($result->getFiles() as $file) {
                if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                    $foldersToVisit[] = (string) $file->getId();
                } elseif (fnmatch($pattern, (string) $file->getName(), FNM_CASEFOLD)) {
                    $files[] = $file;
                }
            }
            $pageToken = $result->getNextPageToken();
        } while ($pageToken !== null);
    }

    usort(
        $files,
        static function (Google_Service_Drive_DriveFile $left, Google_Service_Drive_DriveFile $right): int {
            $leftTime = strtotime((string) $left->getModifiedTime());
            $rightTime = strtotime((string) $right->getModifiedTime());
            $timeComparison = $rightTime <=> $leftTime;
            return $timeComparison !== 0
                ? $timeComparison
                : strcasecmp((string) $left->getName(), (string) $right->getName());
        }
    );

    return $files;
}

/**
 * @param list<Google_Service_Drive_DriveFile> $files
 * @return array{kept: list<Google_Service_Drive_DriveFile>, candidates: list<Google_Service_Drive_DriveFile>, deleted: list<string>, errors: list<string>}
 */
function removeOldDriveBackups(Google_Service_Drive $service, array $files, int $keep, bool $delete): array
{
    $kept = array_slice($files, 0, $keep);
    $candidates = array_slice($files, $keep);
    $deleted = [];
    $errors = [];

    if (!$delete) {
        return compact('kept', 'candidates', 'deleted', 'errors');
    }

    foreach ($candidates as $file) {
        try {
            $service->files->delete((string) $file->getId());
            $deleted[] = (string) $file->getName();
        } catch (Throwable $exception) {
            $errors[] = $file->getName() . ': ' . $exception->getMessage();
        }
    }

    return compact('kept', 'candidates', 'deleted', 'errors');
}

function formatDriveFile(Google_Service_Drive_DriveFile $file): string
{
    $modified = $file->getModifiedTime() !== null
        ? date('Y-m-d H:i:s', strtotime((string) $file->getModifiedTime()))
        : 'khong ro ngay';
    return (string) $file->getName() . ' (' . $modified . ')';
}

$message = '';
$authUrl = null;
$result = null;

try {
    $config = readConfiguration();
    $client = createGoogleClient();
    $service = new Google_Service_Drive($client);
    $files = findDriveBackups($service, $config['folderId'], $config['pattern']);
    $result = removeOldDriveBackups($service, $files, $config['keep'], $config['delete']);
    $message = $config['delete']
        ? 'Da xoa ' . count($result['deleted']) . ' file backup cu tren Google Drive.'
        : 'Che do xem truoc: chua xoa file nao. Them ?delete=1 de xoa.';
} catch (Throwable $exception) {
    $message = 'Loi: ' . $exception->getMessage();
    if (strpos($message, 'Loi: CAN_CAP_QUYEN_OAUTH:') === 0) {
        $authUrl = substr($message, strlen('Loi: CAN_CAP_QUYEN_OAUTH:'));
        $message = 'Can cap quyen Google Drive lan dau.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Xoa backup Google Drive</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.5; margin: 32px; max-width: 900px; }
        .button { background: #1967d2; border-radius: 4px; color: #fff; display: inline-block; padding: 10px 16px; text-decoration: none; }
        .button:hover { background: #1557b0; }
        .warning { background: #fff3cd; padding: 12px; }
    </style>
</head>
<body>
    <h1>Xoa backup cu tren Google Drive</h1>
    <form method="get">
        <input type="hidden" name="auth" value="1">
        <button class="button" type="submit">Tao / cap lai Google Drive Auth</button>
    </form>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($authUrl !== null): ?>
        <p class="warning">
            <a class="button" href="<?= htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') ?>">Dang nhap Google va cap quyen</a>
        </p>
    <?php elseif ($result !== null): ?>
        <p>Folder ID: <code><?= htmlspecialchars($config['folderId'], ENT_QUOTES, 'UTF-8') ?></code></p>
        <h2>Giu lai (<?= count($result['kept']) ?> file)</h2>
        <ul>
            <?php foreach ($result['kept'] as $file): ?>
                <li><?= htmlspecialchars(formatDriveFile($file), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <h2><?= $config['delete'] ? 'Da xoa' : 'Se xoa' ?> (<?= count($result['candidates']) ?> file)</h2>
        <ul>
            <?php foreach ($result['candidates'] as $file): ?>
                <li><?= htmlspecialchars(formatDriveFile($file), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($result['errors'] !== []): ?>
            <h2>Loi khi xoa</h2>
            <ul>
                <?php foreach ($result['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
