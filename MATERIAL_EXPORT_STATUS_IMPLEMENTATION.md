# Material Export Status Implementation

## Overview
This implementation adds status tracking for material exports, showing whether each student's record is "Given" (in the current export) or "Granted" (from a previous export that was marked as granted).

## Database Changes

### SQL Migration
Run the following SQL migration to add the required columns:
```sql
ALTER TABLE `manual_export_audits` 
ADD COLUMN `last_student_id` INT(11) DEFAULT NULL COMMENT 'ID of the last student who bought this material at time of export',
ADD COLUMN `status` VARCHAR(20) DEFAULT 'given' COMMENT 'Status of the export: given or granted',
ADD KEY `idx_manual_export_last_student` (`last_student_id`);
```

This migration is available in `/sql/add_export_status_tracking.sql`

## Implementation Details

### 1. Export Logic (`model/export.php`)
- **Captures last student ID**: Tracks the student who made the latest purchase based on `created_at` timestamp
- **Checks previous exports**: Queries for previous exports with `status='granted'` and retrieves the `last_student_id`
- **Determines granted status**: All students who purchased before or at the same time as the previous `last_student_id` get status='granted'
- **Default status**: New students or those not in previous granted exports get status='given'
- **Database persistence**: Saves `last_student_id` and `status='given'` for each new export

### 2. Export Display (`admin/index.php`)
- **Added STATUS column**: New column in export tables (both with and without RRR)
- **Status formatting**: Displays "Given" or "Granted" with proper capitalization
- **Response handling**: Reads status from API response for each student row

### 3. Verification Page (`manual-export-verify.php`)
- **Status display**: Shows export status in the verification details
- **Database query**: Retrieves status from `manual_export_audits` table
- **UI update**: Added status field in a 4-column grid layout

## How It Works

### Scenario 1: First Export
1. Admin exports material for 50 students
2. System saves `last_student_id` = ID of student with latest purchase
3. All 50 students show status = "Given"
4. Export record saved with `status='given'`

### Scenario 2: Marking Export as Granted
1. Admin manually updates the export record: `UPDATE manual_export_audits SET status='granted' WHERE code='ABC123'`
2. This marks that all students in this export have been granted their materials

### Scenario 3: Subsequent Export After Grant
1. Admin exports the same material again (now 75 students total)
2. System checks for previous exports with `status='granted'`
3. Finds the previous export with `last_student_id=50`
4. Students 1-50 (who purchased before/at student 50) show "Granted"
5. Students 51-75 (new purchasers) show "Given"
6. New export saves with `last_student_id=75` and `status='given'`

## Testing

### Manual Testing Steps

1. **Database Setup**
   ```bash
   mysql -u [username] -p [database_name] < sql/add_export_status_tracking.sql
   ```

2. **First Export Test**
   - Navigate to Admin dashboard
   - Find a material with purchases
   - Click "Export list"
   - Verify all students show "Given" status
   - Note the verification code

3. **Grant Status Test**
   ```sql
   UPDATE manual_export_audits SET status='granted' WHERE code='[verification_code]';
   ```

4. **Second Export Test**
   - Add new student purchases (if needed)
   - Export the same material again
   - Verify old students show "Granted"
   - Verify new students show "Given"

5. **Verification Page Test**
   - Visit `manual-export-verify.php?code=[verification_code]`
   - Verify status is displayed correctly

## Files Modified

1. **`sql/add_export_status_tracking.sql`** - Database migration
2. **`model/export.php`** - Export logic with status tracking
3. **`admin/index.php`** - Export table display with STATUS column
4. **`manual-export-verify.php`** - Verification page with status display

## API Response Changes

The export API now returns an additional `status` field for each student:

```json
{
  "status": "success",
  "rows": [
    {
      "user_id": 123,
      "name": "John Doe",
      "matric_no": "12345",
      "adm_year": "2020",
      "price": 5000,
      "status": "granted"  // NEW FIELD
    }
  ]
}
```

## Security Considerations

- No new security vulnerabilities introduced
- Uses existing authentication and authorization
- SQL queries use proper escaping with `mysqli_real_escape_string()`
- No direct user input for status field (controlled by system logic)

## Future Enhancements

1. **Admin UI for Status Management**: Add interface to mark exports as granted without SQL
2. **Status History**: Track status changes over time
3. **Bulk Status Update**: Allow updating multiple export statuses at once
4. **Export Filtering**: Filter exports by status in admin dashboard
