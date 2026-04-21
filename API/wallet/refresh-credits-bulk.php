<?php
// API: Bulk refresh missed DVA wallet credits
// Supports both web requests (GET/POST) and CLI execution (cron)

$isCli = (PHP_SAPI === 'cli');

if ($isCli && !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

$logFile = __DIR__ . '/refresh-credits-bulk-cron.log';

function walletCreditCronLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND);
}

if (!$isCli && $_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$userId = 0;
$limit = 0;
$token = '';

if ($isCli) {
    if ($argc > 1) {
        for ($i = 1; $i < $argc; $i++) {
            $arg = $argv[$i];
            if (strpos($arg, '--user_id=') === 0) {
                $userId = (int)substr($arg, 10);
            } elseif (strpos($arg, '--limit=') === 0) {
                $limit = (int)substr($arg, 8);
            } elseif (strpos($arg, '--token=') === 0) {
                $token = trim(substr($arg, 8));
            } elseif (strpos($arg, '?') !== false) {
                parse_str($arg, $params);
                $userId = isset($params['user_id']) ? (int)$params['user_id'] : $userId;
                $limit = isset($params['limit']) ? (int)$params['limit'] : $limit;
                $token = isset($params['token']) ? trim((string)$params['token']) : $token;
            }
        }
    }
} else {
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $userId = isset($source['user_id']) ? (int)$source['user_id'] : 0;
    $limit = isset($source['limit']) ? (int)$source['limit'] : 0;
    $token = isset($source['token']) ? trim((string)$source['token']) : '';
}

$configuredToken = defined('NIVASITY_WALLET_CREDIT_CRON_TOKEN') ? trim((string)NIVASITY_WALLET_CREDIT_CRON_TOKEN) : '';
if (!$isCli && $configuredToken !== '' && !hash_equals($configuredToken, $token)) {
    sendApiError('Invalid wallet credit cron token', 403);
}

try {
    $result = nivasityRunWalletFundingSweep($conn, [
        'user_id' => $userId,
        'limit' => $limit,
        'source' => $isCli ? 'cron_bulk_refresh' : 'api_bulk_refresh',
    ]);

    walletCreditCronLog('SUMMARY ' . json_encode($result['summary'], JSON_UNESCAPED_SLASHES), $logFile);

    if ($isCli) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(($result['summary']['failed_wallets'] ?? 0) > 0 && ($result['summary']['posted_rows'] ?? 0) === 0 ? 1 : 0);
    }

    sendApiSuccess('Bulk wallet credit refresh completed', $result);
} catch (Throwable $e) {
    walletCreditCronLog('ERROR ' . $e->getMessage(), $logFile);
    if ($isCli) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    sendApiError($e->getMessage(), 500);
}
?>