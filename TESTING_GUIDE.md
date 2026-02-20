#!/bin/bash
# Material Export Status - Integration Test Script
# This script provides SQL commands to test the material export status functionality

cat << 'EOF'
==================================================
Material Export Status - Integration Test Guide
==================================================

This guide provides step-by-step SQL commands to test the new 
material export status tracking functionality.

PREREQUISITES:
1. Database migration has been applied (sql/add_export_status_tracking.sql)
2. You have access to the MySQL database
3. You have at least one material with student purchases

==================================================
TEST SCENARIO 1: First Export (All "Given")
==================================================

Step 1: Check existing manuals with purchases
----------------------------------------------
SELECT m.id, m.title, m.course_code, COUNT(mb.buyer) as purchase_count
FROM manuals m
LEFT JOIN manuals_bought mb ON m.id = mb.manual_id AND mb.status = 'successful'
GROUP BY m.id, m.title, m.course_code
HAVING purchase_count > 0
ORDER BY purchase_count DESC
LIMIT 5;

Step 2: Perform an export through the admin dashboard
------------------------------------------------------
1. Log in to admin dashboard
2. Find a material from the list above
3. Click "Export list"
4. Note the verification code displayed

Step 3: Verify the export record was created correctly
--------------------------------------------------------
SELECT id, code, manual_id, hoc_user_id, students_count, 
       total_amount, last_student_id, status, downloaded_at
FROM manual_export_audits
ORDER BY downloaded_at DESC
LIMIT 1;

Expected:
- status should be 'given'
- last_student_id should be populated
- students_count should match the number of purchases

Step 4: Check the exported PDF/Print preview
---------------------------------------------
Verify that:
- All students show STATUS = "Given"
- Verification code is displayed
- All student data is correct

==================================================
TEST SCENARIO 2: Mark Export as Granted
==================================================

Step 1: Update the export status to 'granted'
----------------------------------------------
-- Replace 'YOUR_CODE_HERE' with the actual verification code
UPDATE manual_export_audits 
SET status = 'granted' 
WHERE code = 'YOUR_CODE_HERE';

Step 2: Verify the update
--------------------------
SELECT id, code, manual_id, status, last_student_id
FROM manual_export_audits
WHERE code = 'YOUR_CODE_HERE';

Expected:
- status should now be 'granted'

Step 3: Test the verification page
-----------------------------------
1. Visit: manual-export-verify.php?code=YOUR_CODE_HERE
2. Verify that Status shows "Granted"

==================================================
TEST SCENARIO 3: Second Export (Mixed Status)
==================================================

Step 1: Add new student purchases (optional)
---------------------------------------------
-- This simulates new students buying the material
-- Skip this if you already have recent purchases

INSERT INTO manuals_bought (manual_id, price, seller, buyer, school_id, ref_id, status)
VALUES 
  ([manual_id], 5000, 1, [new_student_id_1], 1, CONCAT('TEST_', UUID()), 'successful'),
  ([manual_id], 5000, 1, [new_student_id_2], 1, CONCAT('TEST_', UUID()), 'successful');

Step 2: Perform a second export
--------------------------------
1. Go back to admin dashboard
2. Export the same material again
3. Note the new verification code

Step 3: Verify the new export record
-------------------------------------
SELECT id, code, manual_id, students_count, last_student_id, status, downloaded_at
FROM manual_export_audits
WHERE manual_id = [manual_id]
ORDER BY downloaded_at DESC
LIMIT 2;

Expected:
- Newest export has status = 'given'
- Newest export has updated last_student_id

Step 4: Check the exported PDF/Print preview
---------------------------------------------
Verify that:
- Old students (from first export) show STATUS = "Granted"
- New students show STATUS = "Given"
- STATUS column is present in the table

==================================================
TEST SCENARIO 4: Verification Page
==================================================

Test the verification page with both exports:

1. First export (marked as granted):
   URL: manual-export-verify.php?code=[FIRST_CODE]
   Expected: Status shows "Granted"

2. Second export (still given):
   URL: manual-export-verify.php?code=[SECOND_CODE]
   Expected: Status shows "Given"

==================================================
CLEANUP (Optional)
==================================================

To remove test data:

-- Delete test export records
DELETE FROM manual_export_audits 
WHERE code IN ('CODE1', 'CODE2');

-- Delete test purchases (if you added any)
DELETE FROM manuals_bought 
WHERE ref_id LIKE 'TEST_%';

==================================================
TROUBLESHOOTING
==================================================

Issue: Export shows all "Given" even after marking as granted
Solution:
1. Verify the first export was marked as granted:
   SELECT status FROM manual_export_audits WHERE code = 'FIRST_CODE';
   
2. Check that last_student_id is populated:
   SELECT last_student_id FROM manual_export_audits WHERE code = 'FIRST_CODE';
   
3. Verify the query finds previous granted exports:
   SELECT last_student_id FROM manual_export_audits 
   WHERE manual_id = [manual_id] AND status = 'granted' 
   ORDER BY downloaded_at DESC LIMIT 1;

Issue: NULL value in last_student_id
Solution: This is normal if there were no purchases at the time of export

Issue: Verification page doesn't show status
Solution: 
1. Clear browser cache
2. Check that the column exists:
   SHOW COLUMNS FROM manual_export_audits LIKE 'status';

==================================================
EOF
