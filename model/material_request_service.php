<?php

if (!function_exists('nivasityMaterialRequestTableExists')) {
    function nivasityMaterialRequestTableExists($conn, $tableName) {
        static $cache = [];

        $tableName = strtolower(trim((string)$tableName));
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
        $rs = mysqli_query($conn, "SHOW TABLES LIKE '$tableNameSafe'");
        $cache[$tableName] = $rs && mysqli_num_rows($rs) > 0;
        return $cache[$tableName];
    }
}

if (!function_exists('nivasityMaterialRequestColumnExists')) {
    function nivasityMaterialRequestColumnExists($conn, $tableName, $columnName) {
        static $cache = [];

        $tableName = strtolower(trim((string)$tableName));
        $columnName = strtolower(trim((string)$columnName));
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        $cacheKey = $tableName . ':' . $columnName;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $tableNameSafe = mysqli_real_escape_string($conn, $tableName);
        $columnNameSafe = mysqli_real_escape_string($conn, $columnName);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$tableNameSafe` LIKE '$columnNameSafe'");
        $cache[$cacheKey] = $rs && mysqli_num_rows($rs) > 0;
        return $cache[$cacheKey];
    }
}

if (!function_exists('nivasityMaterialRequestsReady')) {
    function nivasityMaterialRequestsReady($conn) {
        return nivasityMaterialRequestTableExists($conn, 'material_requests')
            && nivasityMaterialRequestTableExists($conn, 'material_request_votes')
            && nivasityMaterialRequestColumnExists($conn, 'material_requests', 'target_faculty_ids_json');
    }
}

if (!function_exists('nivasityMaterialRequestNormalize')) {
    function nivasityMaterialRequestNormalize($value) {
        $value = preg_replace('/\s+/', ' ', trim((string)$value));
        return strtolower($value);
    }
}

if (!function_exists('nivasityMaterialRequestGenerateToken')) {
    function nivasityMaterialRequestGenerateToken() {
        try {
            return bin2hex(random_bytes(10));
        } catch (Throwable $e) {
            return substr(md5(uniqid('material_request_', true)), 0, 20);
        }
    }
}

if (!function_exists('nivasityMaterialRequestGetUserFacultyId')) {
    function nivasityMaterialRequestGetUserFacultyId($conn, $deptId, $schoolId) {
        static $cache = [];

        $deptId = (int)$deptId;
        $schoolId = (int)$schoolId;
        $cacheKey = $schoolId . ':' . $deptId;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($deptId <= 0 || $schoolId <= 0) {
            $cache[$cacheKey] = 0;
            return 0;
        }

        $deptsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM depts LIKE 'faculty_id'");
        if (!$deptsFacultyColumnRes || mysqli_num_rows($deptsFacultyColumnRes) < 1) {
            $cache[$cacheKey] = 0;
            return 0;
        }

        $rs = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $deptId AND school_id = $schoolId LIMIT 1");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            $cache[$cacheKey] = (int)($row['faculty_id'] ?? 0);
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = 0;
        return 0;
    }
}

if (!function_exists('nivasityMaterialRequestBuildManualVisibilityWhere')) {
    function nivasityMaterialRequestBuildManualVisibilityWhere($conn, $userDeptId, $schoolId, $alias = 'm') {
        $userDeptId = (int)$userDeptId;
        $schoolId = (int)$schoolId;
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($alias === '') {
            $alias = 'm';
        }

        $legacyWhere = '1 = 0';
        if ($userDeptId > 0) {
            $legacyWhere = "$alias.dept = $userDeptId";
        }

        try {
            $deptsHasFacultyId = false;
            $manualsHasFaculty = false;
            $manualsHasDepts = false;

            $deptsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM depts LIKE 'faculty_id'");
            if ($deptsFacultyColumnRes && mysqli_num_rows($deptsFacultyColumnRes) > 0) {
                $deptsHasFacultyId = true;
            }

            $manualsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'faculty'");
            if ($manualsFacultyColumnRes && mysqli_num_rows($manualsFacultyColumnRes) > 0) {
                $manualsHasFaculty = true;
            }

            $manualsDeptsColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'depts'");
            if ($manualsDeptsColumnRes && mysqli_num_rows($manualsDeptsColumnRes) > 0) {
                $manualsHasDepts = true;
            }

            if ($deptsHasFacultyId && $manualsHasFaculty && $userDeptId > 0) {
                $userFacultyId = nivasityMaterialRequestGetUserFacultyId($conn, $userDeptId, $schoolId);
                if ($userFacultyId > 0) {
                    $legacyWhere .= " OR ($alias.dept = 0 AND $alias.faculty = $userFacultyId)";
                }
            }

            if ($manualsHasDepts && $userDeptId > 0) {
                $normalizedDeptsExpr = "REPLACE(REPLACE(REPLACE(REPLACE($alias.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
                return "(($alias.depts IS NOT NULL AND FIND_IN_SET($userDeptId, $normalizedDeptsExpr) > 0) OR ($alias.depts IS NULL AND ($legacyWhere)))";
            }
        } catch (Throwable $e) {
            return $legacyWhere;
        }

        return $legacyWhere;
    }
}

if (!function_exists('nivasityMaterialRequestBuildManualDeptMatchWhere')) {
    function nivasityMaterialRequestBuildManualDeptMatchWhere($conn, $userDeptId, $alias = 'm') {
        $userDeptId = (int)$userDeptId;
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($alias === '') {
            $alias = 'm';
        }

        if ($userDeptId <= 0) {
            return '1 = 0';
        }

        $legacyWhere = "$alias.dept = $userDeptId";

        try {
            $manualsDeptsColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'depts'");
            if ($manualsDeptsColumnRes && mysqli_num_rows($manualsDeptsColumnRes) > 0) {
                $normalizedDeptsExpr = "REPLACE(REPLACE(REPLACE(REPLACE($alias.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
                return "(($alias.depts IS NOT NULL AND FIND_IN_SET($userDeptId, $normalizedDeptsExpr) > 0) OR ($alias.depts IS NULL AND ($legacyWhere)))";
            }
        } catch (Throwable $e) {
            return $legacyWhere;
        }

        return $legacyWhere;
    }
}

if (!function_exists('nivasityMaterialRequestBuildAudienceWhereForUser')) {
    function nivasityMaterialRequestBuildAudienceWhereForUser($userDeptId, $userFacultyId, $alias = 'mr') {
        $userDeptId = (int)$userDeptId;
        $userFacultyId = (int)$userFacultyId;
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($alias === '') {
            $alias = 'mr';
        }

        $parts = ["$alias.scope = 'school'"];

        if ($userFacultyId > 0) {
            $parts[] = "($alias.scope = 'faculty' AND $alias.target_faculty_id = $userFacultyId)";
            $normalizedFacultiesExpr = "REPLACE(REPLACE(REPLACE(REPLACE($alias.target_faculty_ids_json, '[', ''), ']', ''), '\"', ''), ' ', '')";
            $parts[] = "($alias.scope = 'selected_faculties' AND $alias.target_faculty_ids_json IS NOT NULL AND FIND_IN_SET($userFacultyId, $normalizedFacultiesExpr) > 0)";
        }

        if ($userDeptId > 0) {
            $parts[] = "($alias.scope = 'my_department' AND $alias.target_department_id = $userDeptId)";
            $normalizedDeptsExpr = "REPLACE(REPLACE(REPLACE(REPLACE($alias.target_dept_ids_json, '[', ''), ']', ''), '\"', ''), ' ', '')";
            $parts[] = "($alias.scope = 'selected_departments' AND $alias.target_dept_ids_json IS NOT NULL AND FIND_IN_SET($userDeptId, $normalizedDeptsExpr) > 0)";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('nivasityMaterialRequestExpectedBuyersCount')) {
    function nivasityMaterialRequestExpectedBuyersCount($conn, $schoolId, $scope, $targetFacultyId = 0, $targetDepartmentId = 0, $targetDeptIds = [], $targetFacultyIds = []) {
        $schoolId = (int)$schoolId;
        $targetFacultyId = (int)$targetFacultyId;
        $targetDepartmentId = (int)$targetDepartmentId;
        $scope = trim((string)$scope);
        if ($schoolId <= 0 || $scope === '') {
            return 0;
        }

        $baseWhere = "u.school = $schoolId AND u.status = 'verified' AND u.role IN ('student','hoc')";

        if ($scope === 'school') {
            $rs = mysqli_query($conn, "SELECT COUNT(u.id) AS total FROM users u WHERE $baseWhere");
        } elseif ($scope === 'faculty') {
            if ($targetFacultyId <= 0) {
                return 0;
            }

            $rs = mysqli_query($conn, "SELECT COUNT(u.id) AS total
                                      FROM users u
                                      INNER JOIN depts d ON d.id = u.dept
                                      WHERE $baseWhere
                                        AND d.school_id = $schoolId
                                        AND d.faculty_id = $targetFacultyId");
                } elseif ($scope === 'selected_faculties') {
                        $targetFacultyIds = array_values(array_unique(array_filter(array_map('intval', (array)$targetFacultyIds))));
                        if (empty($targetFacultyIds)) {
                                return 0;
                        }

                        $facultyList = implode(',', $targetFacultyIds);
                        $rs = mysqli_query($conn, "SELECT COUNT(u.id) AS total
                                                                            FROM users u
                                                                            INNER JOIN depts d ON d.id = u.dept
                                                                            WHERE $baseWhere
                                                                                AND d.school_id = $schoolId
                                                                                AND d.faculty_id IN ($facultyList)");
        } elseif ($scope === 'my_department') {
            if ($targetDepartmentId <= 0) {
                return 0;
            }

            $rs = mysqli_query($conn, "SELECT COUNT(u.id) AS total FROM users u WHERE $baseWhere AND u.dept = $targetDepartmentId");
        } elseif ($scope === 'selected_departments') {
            $targetDeptIds = array_values(array_unique(array_filter(array_map('intval', (array)$targetDeptIds))));
            if (empty($targetDeptIds)) {
                return 0;
            }

            $deptList = implode(',', $targetDeptIds);
            $rs = mysqli_query($conn, "SELECT COUNT(u.id) AS total FROM users u WHERE $baseWhere AND u.dept IN ($deptList)");
        } else {
            return 0;
        }

        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            return (int)($row['total'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('nivasityMaterialRequestResolveScope')) {
    function nivasityMaterialRequestResolveScope($conn, $schoolId, $userDeptId, $userFacultyId, $scope, $targetFacultyId = 0, $selectedDeptIds = [], $selectedFacultyIds = []) {
        $schoolId = (int)$schoolId;
        $userDeptId = (int)$userDeptId;
        $userFacultyId = (int)$userFacultyId;
        $targetFacultyId = (int)$targetFacultyId;
        $scope = trim((string)$scope);
        $selectedDeptIds = array_values(array_unique(array_filter(array_map('intval', (array)$selectedDeptIds))));
        $selectedFacultyIds = array_values(array_unique(array_filter(array_map('intval', (array)$selectedFacultyIds))));

        if (!in_array($scope, ['school', 'faculty', 'selected_faculties', 'selected_departments', 'my_department'], true)) {
            throw new Exception('Please choose who should see this request.');
        }

        $targetDepartmentId = 0;
        $targetFacultyIdsJson = null;
        $targetDeptIdsJson = null;

        if ($scope === 'faculty') {
            $targetFacultyId = $userFacultyId;
            if ($targetFacultyId <= 0) {
                throw new Exception('Complete your academic faculty before using faculty-wide requests.');
            }

            $facultyRs = mysqli_query($conn, "SELECT id FROM faculties WHERE id = $targetFacultyId AND school_id = $schoolId AND status = 'active' LIMIT 1");
            if (!$facultyRs || mysqli_num_rows($facultyRs) < 1) {
                throw new Exception('We could not resolve your faculty for this request.');
            }
        } elseif ($scope === 'selected_faculties') {
            if (empty($selectedFacultyIds)) {
                throw new Exception('Please select at least one faculty.');
            }

            $facultyList = implode(',', $selectedFacultyIds);
            $facultyCheckRs = mysqli_query($conn, "SELECT COUNT(id) AS total FROM faculties WHERE school_id = $schoolId AND status = 'active' AND id IN ($facultyList)");
            $validTotal = 0;
            if ($facultyCheckRs && mysqli_num_rows($facultyCheckRs) > 0) {
                $validTotal = (int)(mysqli_fetch_assoc($facultyCheckRs)['total'] ?? 0);
            }
            if ($validTotal !== count($selectedFacultyIds)) {
                throw new Exception('One or more selected faculties are invalid.');
            }

            $targetFacultyIdsJson = json_encode($selectedFacultyIds);
        } elseif ($scope === 'selected_departments') {
            if (empty($selectedDeptIds)) {
                throw new Exception('Please select at least one department.');
            }

            $deptList = implode(',', $selectedDeptIds);
            $deptCheckRs = mysqli_query($conn, "SELECT COUNT(id) AS total FROM depts WHERE school_id = $schoolId AND status = 'active' AND id IN ($deptList)");
            $validTotal = 0;
            if ($deptCheckRs && mysqli_num_rows($deptCheckRs) > 0) {
                $validTotal = (int)(mysqli_fetch_assoc($deptCheckRs)['total'] ?? 0);
            }
            if ($validTotal !== count($selectedDeptIds)) {
                throw new Exception('One or more selected departments are invalid.');
            }

            $targetDeptIdsJson = json_encode($selectedDeptIds);
        } elseif ($scope === 'my_department') {
            if ($userDeptId <= 0) {
                throw new Exception('Complete your academic department before using department-only requests.');
            }
            $targetDepartmentId = $userDeptId;
        }

        $expectedBuyersCount = nivasityMaterialRequestExpectedBuyersCount($conn, $schoolId, $scope, $targetFacultyId, $targetDepartmentId, $selectedDeptIds, $selectedFacultyIds);
        if ($expectedBuyersCount <= 0) {
            throw new Exception('We could not estimate any expected buyers for the selected audience.');
        }

        return [
            'scope' => $scope,
            'target_faculty_id' => $scope === 'faculty' ? $targetFacultyId : 0,
            'target_faculty_ids_json' => $scope === 'selected_faculties' ? $targetFacultyIdsJson : null,
            'target_department_id' => $scope === 'my_department' ? $targetDepartmentId : 0,
            'target_dept_ids_json' => $scope === 'selected_departments' ? $targetDeptIdsJson : null,
            'expected_buyers_count' => $expectedBuyersCount,
        ];
    }
}

if (!function_exists('nivasityMaterialRequestMatchCondition')) {
    function nivasityMaterialRequestMatchCondition($conn, $codeNormalized, $titleNormalized, $codeField, $titleField) {
        $conditions = [];
        if ($codeNormalized !== '') {
            $codeSafe = mysqli_real_escape_string($conn, $codeNormalized);
            $conditions[] = "LOWER(TRIM(COALESCE($codeField, ''))) = '$codeSafe'";
        }
        if ($titleNormalized !== '') {
            $titleSafe = mysqli_real_escape_string($conn, $titleNormalized);
            $conditions[] = "LOWER(TRIM(COALESCE($titleField, ''))) = '$titleSafe'";
        }

        if (empty($conditions)) {
            return '1 = 0';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }
}

if (!function_exists('nivasityMaterialRequestFindOpenMaterialMatch')) {
    function nivasityMaterialRequestFindOpenMaterialMatch($conn, $schoolId, $userDeptId, $codeNormalized, $titleNormalized) {
        $schoolId = (int)$schoolId;
                $userDeptId = (int)$userDeptId;
                if ($schoolId <= 0 || $userDeptId <= 0) {
            return null;
        }

        $matchCondition = nivasityMaterialRequestMatchCondition($conn, $codeNormalized, $titleNormalized, 'm.course_code', 'm.title');
                $deptMatchWhere = nivasityMaterialRequestBuildManualDeptMatchWhere($conn, $userDeptId, 'm');

        $sql = "SELECT m.id, m.title, m.course_code
                FROM manuals m
                WHERE m.school_id = $schoolId
                  AND m.status = 'open'
                                    AND ($deptMatchWhere)
                  AND $matchCondition
                ORDER BY m.id DESC
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityMaterialRequestGetByIdForUser')) {
    function nivasityMaterialRequestGetByIdForUser($conn, $requestId, $schoolId, $userDeptId, $userFacultyId) {
        $requestId = (int)$requestId;
        $schoolId = (int)$schoolId;
        if ($requestId <= 0 || $schoolId <= 0) {
            return null;
        }

        $audienceWhere = nivasityMaterialRequestBuildAudienceWhereForUser($userDeptId, $userFacultyId, 'mr');
        $sql = "SELECT mr.*
                FROM material_requests mr
                WHERE mr.id = $requestId
                  AND mr.school_id = $schoolId
                  AND ($audienceWhere)
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityMaterialRequestFindExistingVisibleRequest')) {
    function nivasityMaterialRequestFindExistingVisibleRequest($conn, $schoolId, $userDeptId, $userFacultyId, $codeNormalized, $titleNormalized) {
        $schoolId = (int)$schoolId;
        if ($schoolId <= 0) {
            return null;
        }

        $audienceWhere = nivasityMaterialRequestBuildAudienceWhereForUser($userDeptId, $userFacultyId, 'mr');
        $matchCondition = nivasityMaterialRequestMatchCondition($conn, $codeNormalized, $titleNormalized, 'mr.material_code_normalized', 'mr.material_title_normalized');
        $sql = "SELECT mr.*
                FROM material_requests mr
                WHERE mr.school_id = $schoolId
                  AND mr.status IN ('open', 'under_review')
                  AND $matchCondition
                  AND ($audienceWhere)
                ORDER BY mr.id DESC
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityMaterialRequestCountVotes')) {
    function nivasityMaterialRequestCountVotes($conn, $requestId) {
        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            return 0;
        }

        $rs = mysqli_query($conn, "SELECT COUNT(id) AS total FROM material_request_votes WHERE request_id = $requestId");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            return (int)($row['total'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('nivasityMaterialRequestUpdateStatusByVotes')) {
    function nivasityMaterialRequestUpdateStatusByVotes($conn, $requestId) {
        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            return null;
        }

        $rs = mysqli_query($conn, "SELECT id, status, expected_buyers_count, threshold_percent FROM material_requests WHERE id = $requestId LIMIT 1");
        if (!$rs || mysqli_num_rows($rs) < 1) {
            return null;
        }

        $request = mysqli_fetch_assoc($rs);
        $votes = nivasityMaterialRequestCountVotes($conn, $requestId);
        $expected = max(1, (int)($request['expected_buyers_count'] ?? 0));
        $thresholdPercent = (float)($request['threshold_percent'] ?? 40);
        $progressPercent = ($votes / $expected) * 100;

        $status = (string)($request['status'] ?? 'open');
        if ($status !== 'resolved' && $progressPercent >= $thresholdPercent && $status === 'open') {
            mysqli_query($conn, "UPDATE material_requests SET status = 'under_review', updated_at = NOW() WHERE id = $requestId LIMIT 1");
            $status = 'under_review';
        }

        return [
            'status' => $status,
            'votes' => $votes,
            'expected_buyers_count' => $expected,
            'progress_percent' => round($progressPercent, 1),
            'threshold_percent' => $thresholdPercent,
            'threshold_met' => $progressPercent >= $thresholdPercent,
        ];
    }
}

if (!function_exists('nivasityMaterialRequestCreate')) {
    function nivasityMaterialRequestCreate($conn, $user, $payload) {
        if (!nivasityMaterialRequestsReady($conn)) {
            throw new Exception('Material requests are not available until the latest SQL update is applied.');
        }

        $userId = (int)($user['id'] ?? 0);
        $schoolId = (int)($user['school'] ?? 0);
        $userDeptId = (int)($user['dept'] ?? 0);
        $userFacultyId = nivasityMaterialRequestGetUserFacultyId($conn, $userDeptId, $schoolId);

        $materialCode = preg_replace('/\s+/', ' ', trim((string)($payload['material_code'] ?? '')));
        $materialTitle = preg_replace('/\s+/', ' ', trim((string)($payload['material_title'] ?? '')));
        $scope = trim((string)($payload['scope'] ?? ''));
        $targetFacultyId = (int)($payload['target_faculty_id'] ?? 0);
        $targetFacultyIds = isset($payload['target_faculty_ids']) ? (array)$payload['target_faculty_ids'] : [];
        $targetDeptIds = isset($payload['target_dept_ids']) ? (array)$payload['target_dept_ids'] : [];

        if ($userId <= 0 || $schoolId <= 0) {
            throw new Exception('Your session has expired. Please sign in again.');
        }
        if ($userDeptId <= 0) {
            throw new Exception('Complete your academic information before creating a material request.');
        }
        if ($materialCode === '' || $materialTitle === '') {
            throw new Exception('Material code and title are required.');
        }

        $codeNormalized = nivasityMaterialRequestNormalize($materialCode);
        $titleNormalized = nivasityMaterialRequestNormalize($materialTitle);

        $manualMatch = nivasityMaterialRequestFindOpenMaterialMatch($conn, $schoolId, $userDeptId, $codeNormalized, $titleNormalized);
        if ($manualMatch) {
            return [
                'status' => 'material_exists',
                'message' => 'A related open material is already available in the store. Please check the current materials list first.',
                'match' => $manualMatch,
            ];
        }

        $existingRequest = nivasityMaterialRequestFindExistingVisibleRequest($conn, $schoolId, $userDeptId, $userFacultyId, $codeNormalized, $titleNormalized);
        if ($existingRequest) {
            return [
                'status' => 'duplicate',
                'message' => 'A similar material request is already active for your audience. Upvote that request instead of creating another one.',
                'request' => $existingRequest,
            ];
        }

        $scopeData = nivasityMaterialRequestResolveScope($conn, $schoolId, $userDeptId, $userFacultyId, $scope, $targetFacultyId, $targetDeptIds, $targetFacultyIds);

        $shareToken = nivasityMaterialRequestGenerateToken();
        $materialCodeSafe = mysqli_real_escape_string($conn, $materialCode);
        $materialTitleSafe = mysqli_real_escape_string($conn, $materialTitle);
        $codeNormalizedSafe = mysqli_real_escape_string($conn, $codeNormalized);
        $titleNormalizedSafe = mysqli_real_escape_string($conn, $titleNormalized);
        $scopeSafe = mysqli_real_escape_string($conn, $scopeData['scope']);
        $shareTokenSafe = mysqli_real_escape_string($conn, $shareToken);
        $targetFacultyIdsJsonValue = $scopeData['target_faculty_ids_json'] === null
            ? 'NULL'
            : "'" . mysqli_real_escape_string($conn, (string)$scopeData['target_faculty_ids_json']) . "'";
        $targetDeptsJsonValue = $scopeData['target_dept_ids_json'] === null
            ? 'NULL'
            : "'" . mysqli_real_escape_string($conn, (string)$scopeData['target_dept_ids_json']) . "'";

        mysqli_begin_transaction($conn);
        try {
            $insertSql = "INSERT INTO material_requests (
                    school_id, requester_user_id, requester_dept_id, requester_faculty_id,
                    material_code, material_title, material_code_normalized, material_title_normalized,
                    scope, target_faculty_id, target_department_id, target_faculty_ids_json, target_dept_ids_json,
                    expected_buyers_count, share_token, status, threshold_percent
                ) VALUES (
                    $schoolId, $userId, $userDeptId, $userFacultyId,
                    '$materialCodeSafe', '$materialTitleSafe', '$codeNormalizedSafe', '$titleNormalizedSafe',
                    '$scopeSafe', " . (int)$scopeData['target_faculty_id'] . ", " . (int)$scopeData['target_department_id'] . ", $targetFacultyIdsJsonValue, $targetDeptsJsonValue,
                    " . (int)$scopeData['expected_buyers_count'] . ", '$shareTokenSafe', 'open', 40.00
                )";
            if (!mysqli_query($conn, $insertSql)) {
                throw new Exception('Could not create the material request right now.');
            }

            $requestId = (int)mysqli_insert_id($conn);
            $insertVoteSql = "INSERT INTO material_request_votes (request_id, user_id) VALUES ($requestId, $userId)";
            if (!mysqli_query($conn, $insertVoteSql)) {
                throw new Exception('Could not register your initial upvote.');
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        $statusData = nivasityMaterialRequestUpdateStatusByVotes($conn, $requestId);
        return [
            'status' => 'created',
            'message' => 'Material request submitted successfully. Share it with your course mates so they can upvote it.',
            'request_id' => $requestId,
            'share_token' => $shareToken,
            'status_data' => $statusData,
        ];
    }
}

if (!function_exists('nivasityMaterialRequestAddVote')) {
    function nivasityMaterialRequestAddVote($conn, $requestId, $user) {
        if (!nivasityMaterialRequestsReady($conn)) {
            throw new Exception('Material requests are not available until the latest SQL update is applied.');
        }

        $requestId = (int)$requestId;
        $userId = (int)($user['id'] ?? 0);
        $schoolId = (int)($user['school'] ?? 0);
        $userDeptId = (int)($user['dept'] ?? 0);
        $userFacultyId = nivasityMaterialRequestGetUserFacultyId($conn, $userDeptId, $schoolId);

        if ($requestId <= 0) {
            throw new Exception('Invalid material request selected.');
        }
        if ($userId <= 0 || $schoolId <= 0 || $userDeptId <= 0) {
            throw new Exception('Complete your academic information before upvoting requests.');
        }

        $request = nivasityMaterialRequestGetByIdForUser($conn, $requestId, $schoolId, $userDeptId, $userFacultyId);
        if (!$request) {
            throw new Exception('You cannot upvote this request.');
        }
        if (($request['status'] ?? '') === 'resolved') {
            throw new Exception('This request has already been resolved.');
        }

        $alreadyVotedRs = mysqli_query($conn, "SELECT id FROM material_request_votes WHERE request_id = $requestId AND user_id = $userId LIMIT 1");
        if ($alreadyVotedRs && mysqli_num_rows($alreadyVotedRs) > 0) {
            return [
                'status' => 'exists',
                'message' => 'You have already upvoted this request.',
                'status_data' => nivasityMaterialRequestUpdateStatusByVotes($conn, $requestId),
            ];
        }

        if (!mysqli_query($conn, "INSERT INTO material_request_votes (request_id, user_id) VALUES ($requestId, $userId)")) {
            throw new Exception('Could not save your upvote right now.');
        }

        $statusData = nivasityMaterialRequestUpdateStatusByVotes($conn, $requestId);
        return [
            'status' => 'success',
            'message' => 'Upvote recorded successfully.',
            'status_data' => $statusData,
        ];
    }
}

if (!function_exists('nivasityMaterialRequestGetDeptNames')) {
    function nivasityMaterialRequestGetDeptNames($conn, $deptIds) {
        $deptIds = array_values(array_unique(array_filter(array_map('intval', (array)$deptIds))));
        if (empty($deptIds)) {
            return [];
        }

        $deptList = implode(',', $deptIds);
        $names = [];
        $rs = mysqli_query($conn, "SELECT id, name FROM depts WHERE id IN ($deptList)");
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $names[(int)$row['id']] = (string)($row['name'] ?? '');
            }
        }

        $resolved = [];
        foreach ($deptIds as $deptId) {
            if (isset($names[$deptId]) && $names[$deptId] !== '') {
                $resolved[] = $names[$deptId];
            }
        }

        return $resolved;
    }
}

if (!function_exists('nivasityMaterialRequestGetFacultyNames')) {
    function nivasityMaterialRequestGetFacultyNames($conn, $facultyIds) {
        $facultyIds = array_values(array_unique(array_filter(array_map('intval', (array)$facultyIds))));
        if (empty($facultyIds)) {
            return [];
        }

        $facultyList = implode(',', $facultyIds);
        $names = [];
        $rs = mysqli_query($conn, "SELECT id, name FROM faculties WHERE id IN ($facultyList)");
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $names[(int)$row['id']] = (string)($row['name'] ?? '');
            }
        }

        $resolved = [];
        foreach ($facultyIds as $facultyId) {
            if (isset($names[$facultyId]) && $names[$facultyId] !== '') {
                $resolved[] = $names[$facultyId];
            }
        }

        return $resolved;
    }
}

if (!function_exists('nivasityMaterialRequestDecorateRow')) {
    function nivasityMaterialRequestDecorateRow($conn, $row) {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['expected_buyers_count'] = max(1, (int)($row['expected_buyers_count'] ?? 0));
        $row['upvote_count'] = (int)($row['upvote_count'] ?? 0);
        $row['threshold_percent'] = (float)($row['threshold_percent'] ?? 40);
        $row['progress_percent'] = round(($row['upvote_count'] / $row['expected_buyers_count']) * 100, 1);
        $row['threshold_met'] = $row['progress_percent'] >= $row['threshold_percent'];
        $row['viewer_has_upvoted'] = !empty($row['viewer_has_upvoted']);
        $row['requester_name'] = trim((string)($row['requester_name'] ?? ''));

        $scope = (string)($row['scope'] ?? 'school');
        if ($scope === 'school') {
            $row['audience_label'] = 'All students in this school';
        } elseif ($scope === 'faculty') {
            $facultyName = trim((string)($row['target_faculty_name'] ?? ''));
            $row['audience_label'] = $facultyName !== '' ? 'All students in ' . $facultyName : 'Selected faculty';
        } elseif ($scope === 'selected_faculties') {
            $facultyIds = json_decode((string)($row['target_faculty_ids_json'] ?? '[]'), true);
            $facultyNames = nivasityMaterialRequestGetFacultyNames($conn, is_array($facultyIds) ? $facultyIds : []);
            $row['audience_label'] = empty($facultyNames) ? 'Selected faculties' : implode(', ', $facultyNames);
        } elseif ($scope === 'my_department') {
            $deptName = trim((string)($row['target_department_name'] ?? ''));
            $row['audience_label'] = $deptName !== '' ? 'Only ' . $deptName : 'Only one department';
        } else {
            $deptIds = json_decode((string)($row['target_dept_ids_json'] ?? '[]'), true);
            $deptNames = nivasityMaterialRequestGetDeptNames($conn, is_array($deptIds) ? $deptIds : []);
            $row['audience_label'] = empty($deptNames) ? 'Selected departments' : implode(', ', $deptNames);
        }

        return $row;
    }
}

if (!function_exists('nivasityMaterialRequestFetchVisibleRequests')) {
    function nivasityMaterialRequestFetchVisibleRequests($conn, $user) {
        if (!nivasityMaterialRequestsReady($conn)) {
            return [];
        }

        $userId = (int)($user['id'] ?? 0);
        $schoolId = (int)($user['school'] ?? 0);
        $userDeptId = (int)($user['dept'] ?? 0);
        $userFacultyId = nivasityMaterialRequestGetUserFacultyId($conn, $userDeptId, $schoolId);

        if ($userId <= 0 || $schoolId <= 0) {
            return [];
        }

        $audienceWhere = nivasityMaterialRequestBuildAudienceWhereForUser($userDeptId, $userFacultyId, 'mr');
        $sql = "SELECT
                    mr.*, 
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS requester_name,
                    f.name AS target_faculty_name,
                    d.name AS target_department_name,
                    (
                        SELECT COUNT(v.id)
                        FROM material_request_votes v
                        WHERE v.request_id = mr.id
                    ) AS upvote_count,
                    EXISTS(
                        SELECT 1
                        FROM material_request_votes vv
                        WHERE vv.request_id = mr.id AND vv.user_id = $userId
                    ) AS viewer_has_upvoted
                FROM material_requests mr
                LEFT JOIN users u ON u.id = mr.requester_user_id
                LEFT JOIN faculties f ON f.id = mr.target_faculty_id
                LEFT JOIN depts d ON d.id = mr.target_department_id
                WHERE mr.school_id = $schoolId
                  AND ($audienceWhere)
                ORDER BY FIELD(mr.status, 'under_review', 'open', 'resolved') ASC, mr.updated_at DESC, mr.id DESC";

        $rows = [];
        $rs = mysqli_query($conn, $sql);
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $rows[] = nivasityMaterialRequestDecorateRow($conn, $row);
            }
        }

        return $rows;
    }
}
