<?php
// API: Repair missing school payable ledger rows for successful purchase transactions
// Supports both web requests (GET/POST) and CLI execution (cron)

$isCli = (PHP_SAPI === 'cli');

if ($isCli && !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

$logFile = __DIR__ . '/repair-school-ledger-cron.log';

function schoolLedgerRepairCronLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND);
}

if (!$isCli && $_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$schoolId = 0;
$limit = 0;
$dryRun = false;
$olderThanMinutes = 10;
$refId = '';
$token = '';

if ($isCli) {
    if ($argc > 1) {
        for ($i = 1; $i < $argc; $i++) {
            $arg = $argv[$i];
            if (strpos($arg, '--school_id=') === 0) {
                $schoolId = (int)substr($arg, 12);
            } elseif (strpos($arg, '--limit=') === 0) {
                $limit = (int)substr($arg, 8);
            } elseif (strpos($arg, '--dry-run=') === 0) {
                $dryRun = in_array(strtolower(substr($arg, 10)), ['1', 'true', 'yes'], true);
            } elseif (strpos($arg, '--older_than_minutes=') === 0) {
                $olderThanMinutes = (int)substr($arg, 21);
            } elseif (strpos($arg, '--ref_id=') === 0) {
                $refId = trim(substr($arg, 9));
            } elseif (strpos($arg, '--token=') === 0) {
                $token = trim(substr($arg, 8));
            } elseif (strpos($arg, '?') !== false) {
                parse_str($arg, $params);
                $schoolId = isset($params['school_id']) ? (int)$params['school_id'] : $schoolId;
                $limit = isset($params['limit']) ? (int)$params['limit'] : $limit;
                $dryRun = isset($params['dry_run']) ? in_array(strtolower((string)$params['dry_run']), ['1', 'true', 'yes'], true) : $dryRun;
                $olderThanMinutes = isset($params['older_than_minutes']) ? (int)$params['older_than_minutes'] : $olderThanMinutes;
                $refId = isset($params['ref_id']) ? trim((string)$params['ref_id']) : $refId;
                $token = isset($params['token']) ? trim((string)$params['token']) : $token;
            }
        }
    }
} else {
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $schoolId = isset($source['school_id']) ? (int)$source['school_id'] : 0;
    $limit = isset($source['limit']) ? (int)$source['limit'] : 0;
    $dryRun = isset($source['dry_run']) ? in_array(strtolower((string)$source['dry_run']), ['1', 'true', 'yes'], true) : false;
    $olderThanMinutes = isset($source['older_than_minutes']) ? (int)$source['older_than_minutes'] : 10;
    $refId = isset($source['ref_id']) ? trim((string)$source['ref_id']) : '';
    $token = isset($source['token']) ? trim((string)$source['token']) : '';
}

$configuredToken = defined('NIVASITY_LEDGER_REPAIR_CRON_TOKEN') ? trim((string)NIVASITY_LEDGER_REPAIR_CRON_TOKEN) : '';
if (!$isCli && $configuredToken !== '' && !hash_equals($configuredToken, $token)) {
    sendApiError('Invalid school ledger repair cron token', 403);
}

try {
    $result = nivasityRunSchoolPayableRepairSweep($conn, [
        'school_id' => $schoolId,
        'limit' => $limit,
        'dry_run' => $dryRun,
        'older_than_minutes' => $olderThanMinutes,
        'ref_id' => $refId,
    ]);

    schoolLedgerRepairCronLog('SUMMARY ' . json_encode($result['summary'], JSON_UNESCAPED_SLASHES), $logFile);

    if ($isCli) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(($result['summary']['failed'] ?? 0) > 0 && ($result['summary']['repaired'] ?? 0) === 0 ? 1 : 0);
    }

    sendApiSuccess('School ledger repair sweep completed', $result);
} catch (Throwable $e) {
    schoolLedgerRepairCronLog('ERROR ' . $e->getMessage(), $logFile);
    if ($isCli) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    sendApiError($e->getMessage(), 500);
}
?>