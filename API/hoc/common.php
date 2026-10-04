<?php
// Shared helpers for the HOC (class rep) endpoints: materials list, export, granted exports.
// Same rules as the old website's admin/index.php, model/export.php and admin/granted_exports.php.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

function hocRequireUser($conn): array {
    $user = authenticateApiRequest($conn);
    if (($user['role'] ?? '') !== 'hoc') {
        sendApiError('Only class reps (HOC) can use this.', 403);
    }
    if ((int) ($user['dept'] ?? 0) <= 0) {
        sendApiError('Your department is required before exporting material lists.', 403);
    }
    return $user;
}

function hocColumnExists($conn, string $table, string $column): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}

// Materials this HOC can see: school-admin materials shared with their department. Materials
// HOCs created themselves in the past are retired and never listed.
function hocMaterialsWhere($conn, array $user): string {
    $hocId = (int) $user['id'];
    $deptId = (int) $user['dept'];
    $schoolId = (int) $user['school'];

    $legacy = ["m.dept = $deptId"];
    if (hocColumnExists($conn, 'depts', 'faculty_id') && hocColumnExists($conn, 'manuals', 'faculty')) {
        $q = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $deptId AND school_id = $schoolId LIMIT 1");
        $facultyId = ($q && ($r = mysqli_fetch_assoc($q))) ? (int) $r['faculty_id'] : 0;
        if ($facultyId > 0) {
            $legacy[] = "(m.dept = 0 AND m.faculty = $facultyId)";
        }
    }
    $legacyWhere = implode(' OR ', $legacy);
    if (hocColumnExists($conn, 'manuals', 'depts')) {
        $norm = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
        $shared = "(m.depts IS NOT NULL AND FIND_IN_SET($deptId, $norm) > 0) OR (m.depts IS NULL AND ($legacyWhere))";
    } else {
        $shared = $legacyWhere;
    }
    return "(m.user_id = 0 AND m.school_id = $schoolId AND ($shared))";
}

function hocAuditStatusColumn($conn): string {
    if (hocColumnExists($conn, 'manual_export_audits', 'grant_status')) return 'grant_status';
    if (hocColumnExists($conn, 'manual_export_audits', 'status')) return 'status';
    return '';
}

function hocGrantedSql(string $col): string {
    return "LOWER(TRIM(CAST($col AS CHAR))) IN ('granted', '1', 'true', 'yes')";
}

// Where verification links point: the school's portal (schools.domain, else FUNAAB's portal).
function hocVerifyBaseUrl($conn, int $schoolId): string {
    $q = mysqli_query($conn, "SELECT domain FROM schools WHERE id = $schoolId LIMIT 1");
    $domain = ($q && ($r = mysqli_fetch_assoc($q))) ? trim((string) ($r['domain'] ?? '')) : '';
    if ($domain !== '') {
        return 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/'));
    }
    return $schoolId === 1 ? 'https://funaab.nivasity.com' : 'https://www.nivasity.com';
}

// Students in a granted export (same fallbacks as admin/granted_exports.php).
function hocGrantedExportRows($conn, array $audit, int $deptId): array {
    $manualId = (int) $audit['manual_id'];
    $auditId = (int) $audit['id'];
    $select = "SELECT TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS name,
                      u.matric_no, u.adm_year, mb.price
               FROM manuals_bought AS mb JOIN users AS u ON u.id = mb.buyer";
    $order = " ORDER BY u.matric_no ASC, name ASC";
    $run = function (string $where) use ($conn, $select, $order) {
        $rows = [];
        $res = mysqli_query($conn, "$select WHERE $where AND mb.manual_id IS NOT NULL$order");
        while ($res && ($r = mysqli_fetch_assoc($res))) $rows[] = $r;
        return $rows;
    };
    if (hocColumnExists($conn, 'manuals_bought', 'export_id')) {
        $rows = $run("mb.export_id = $auditId AND mb.manual_id = $manualId AND mb.status = 'successful'");
        if ($rows) return $rows;
    }
    $ids = json_decode((string) ($audit['bought_ids_json'] ?? ''), true);
    if (is_array($ids)) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            $rows = $run("mb.id IN (" . implode(',', $ids) . ") AND mb.manual_id = $manualId AND mb.status = 'successful'");
            if ($rows) return $rows;
        }
    }
    $from = (int) ($audit['from_bought_id'] ?? 0);
    $to = (int) ($audit['to_bought_id'] ?? 0);
    if ($from > 0 && $to >= $from) {
        $rows = $run("mb.id BETWEEN $from AND $to AND mb.manual_id = $manualId AND mb.status = 'successful'");
        if ($rows) return $rows;
    }
    $at = mysqli_real_escape_string($conn, (string) ($audit['downloaded_at'] ?? ''));
    return $run("mb.manual_id = $manualId AND mb.status = 'successful'" . ($at !== '' ? " AND mb.created_at <= '$at'" : '') . " AND u.dept = $deptId");
}
