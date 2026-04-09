<?php
// API: Process school settlements
// Supports both web requests (GET/POST) and CLI execution (cron)

$isCli = (PHP_SAPI === 'cli');

if ($isCli && !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

$logFile = __DIR__ . '/process-settlements-cron.log';

function settlementCronLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND);
}

if (!$isCli && $_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$schoolId = 0;
$limit = 0;
$dryRun = false;
$force = false;
$scheduledFor = '';
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
            } elseif (strpos($arg, '--force=') === 0) {
                $force = in_array(strtolower(substr($arg, 8)), ['1', 'true', 'yes'], true);
            } elseif (strpos($arg, '--scheduled_for=') === 0) {
                $scheduledFor = trim(substr($arg, 16));
            } elseif (strpos($arg, '--token=') === 0) {
                $token = trim(substr($arg, 8));
            } elseif (strpos($arg, '?') !== false) {
                parse_str($arg, $params);
                $schoolId = isset($params['school_id']) ? (int)$params['school_id'] : $schoolId;
                $limit = isset($params['limit']) ? (int)$params['limit'] : $limit;
                $dryRun = isset($params['dry_run']) ? in_array(strtolower((string)$params['dry_run']), ['1', 'true', 'yes'], true) : $dryRun;
                $force = isset($params['force']) ? in_array(strtolower((string)$params['force']), ['1', 'true', 'yes'], true) : $force;
                $scheduledFor = isset($params['scheduled_for']) ? trim((string)$params['scheduled_for']) : $scheduledFor;
                $token = isset($params['token']) ? trim((string)$params['token']) : $token;
            }
        }
    }
} else {
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $schoolId = isset($source['school_id']) ? (int)$source['school_id'] : 0;
    $limit = isset($source['limit']) ? (int)$source['limit'] : 0;
    $dryRun = isset($source['dry_run']) ? in_array(strtolower((string)$source['dry_run']), ['1', 'true', 'yes'], true) : false;
    $force = isset($source['force']) ? in_array(strtolower((string)$source['force']), ['1', 'true', 'yes'], true) : false;
    $scheduledFor = isset($source['scheduled_for']) ? trim((string)$source['scheduled_for']) : '';
    $token = isset($source['token']) ? trim((string)$source['token']) : '';
}

$configuredToken = defined('NIVASITY_SETTLEMENT_CRON_TOKEN') ? trim((string)NIVASITY_SETTLEMENT_CRON_TOKEN) : '';
if (!$isCli && $configuredToken !== '' && !hash_equals($configuredToken, $token)) {
    sendApiError('Invalid settlement cron token', 403);
}

if ($scheduledFor !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledFor)) {
    sendApiError('scheduled_for must use YYYY-MM-DD format', 422);
}

try {
    $summary = nivasityRunSettlementSweep($conn, [
        'school_id' => $schoolId,
        'limit' => $limit,
        'dry_run' => $dryRun,
        'force' => $force,
        'scheduled_for' => $scheduledFor !== '' ? $scheduledFor : date('Y-m-d'),
    ]);

    settlementCronLog('SUMMARY ' . json_encode($summary, JSON_UNESCAPED_SLASHES), $logFile);

    if ($isCli) {
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    sendApiSuccess('Settlement sweep completed', $summary);
} catch (Throwable $e) {
    settlementCronLog('ERROR ' . $e->getMessage(), $logFile);
    if ($isCli) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    sendApiError($e->getMessage(), 500);
}
?>