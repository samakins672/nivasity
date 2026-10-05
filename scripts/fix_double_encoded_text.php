<?php
// One-off repair for text stored "double-encoded" (e.g. "itâ€™s" instead of "it’s").
//
// Before model/config.php set the connection to utf8mb4, the student site and API talked to the
// database in the live server's default (latin1), so UTF-8 text from students was stored
// double-encoded. The command center (utf8mb4) shows it garbled.
//
// For every UTF-8 text column it fixes only values that are double-encoded: the value is turned
// back into its latin1 bytes and re-read as UTF-8, and only when that round trip is lossless.
// Text that is already correct (written by the command center) and plain ASCII are never touched,
// and running it again changes nothing.
//
// Run from the server's terminal (cPanel > Terminal), from the site's root folder, right after
// uploading the new model/config.php:
//   php scripts/fix_double_encoded_text.php           # preview: counts per column, changes nothing
//   php scripts/fix_double_encoded_text.php --apply   # fix
// Take a database backup first.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
require __DIR__ . '/../model/config.php'; // sets utf8mb4 on $conn
mysqli_report(MYSQLI_REPORT_OFF);

$apply = in_array('--apply', $argv, true);
echo $apply ? "Fixing double-encoded text...\n" : "Preview (nothing is changed; run with --apply to fix)\n";

$charset = mysqli_fetch_row(mysqli_query($conn, 'SELECT @@character_set_connection'))[0] ?? '';
if (stripos($charset, 'utf8') !== 0) {
    exit("Connection charset is '$charset', expected utf8mb4. Upload the new model/config.php first.\n");
}

$textTypes = '/^(char|varchar|tinytext|text|mediumtext|longtext)\b/i';
$total = 0;
$tables = mysqli_query($conn, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($tables && ($t = mysqli_fetch_row($tables))) {
    $table = $t[0];
    if (stripos($table, 'zz_') === 0) {
        continue; // scratch tables
    }
    $cols = mysqli_query($conn, "SHOW FULL COLUMNS FROM `$table`");
    while ($cols && ($c = mysqli_fetch_assoc($cols))) {
        if (!preg_match($textTypes, $c['Type']) || stripos((string) $c['Collation'], 'utf8') !== 0) {
            continue;
        }
        $col = '`' . str_replace('`', '``', $c['Field']) . '`';
        $fixed = "CONVERT(CAST(CONVERT($col USING latin1) AS BINARY) USING utf8mb4)";
        // Byte comparisons, so columns with any utf8mb4 collation work
        $where = "CAST($col AS BINARY) <> CAST($fixed AS BINARY) AND CAST(CONVERT($col USING latin1) AS BINARY) = CAST($fixed AS BINARY)";

        if ($apply) {
            $ok = mysqli_query($conn, "UPDATE `$table` SET $col = $fixed WHERE $where");
            $n = $ok ? mysqli_affected_rows($conn) : -1;
        } else {
            $res = mysqli_query($conn, "SELECT COUNT(*) FROM `$table` WHERE $where");
            $n = $res ? (int) mysqli_fetch_row($res)[0] : -1;
        }

        if ($n === -1) {
            echo "  ! $table.{$c['Field']}: " . mysqli_error($conn) . "\n";
        } elseif ($n > 0) {
            echo "  $table.{$c['Field']}: $n row(s)" . ($apply ? ' fixed' : '') . "\n";
            $total += $n;
        }
    }
}

echo $apply ? "Done: $total value(s) fixed.\n" : "Total: $total value(s) would be fixed.\n";
