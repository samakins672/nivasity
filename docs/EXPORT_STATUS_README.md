# Material Export Status Feature - README

## Quick Start

This feature adds status tracking to material exports, showing whether students have been "Given" or "Granted" their materials based on export history.

## What Was Changed?

### 1. Database (Required Setup)
Run this migration first:
```bash
mysql -u [username] -p [database_name] < sql/add_export_status_tracking.sql
```

### 2. Export Functionality
- Exports now track which student was the last to purchase
- Each student record gets a status: "Given" or "Granted"
- Status is automatically determined based on previous granted exports

### 3. User Interface
- **Export Table**: New STATUS column in printed/PDF exports
- **Verification Page**: Status displayed in export verification details

## How to Use

### For Admins/HOCs

#### Export Materials (Normal Use)
1. Go to Admin Dashboard
2. Find material to export
3. Click "Export list"
4. Note the verification code
5. **All students will show "Given" status**

#### Mark Export as Granted
When materials have been distributed to students:
```sql
UPDATE manual_export_audits 
SET status='granted' 
WHERE code='YOUR_VERIFICATION_CODE';
```

#### Next Export (After Grant)
1. Export the same material again
2. Previous students will now show "Granted"
3. New students will show "Given"
4. This helps track who already received materials

### For Students/Public

#### Verify Export
1. Visit: `manual-export-verify.php?code=[CODE]`
2. See export details including status
3. Confirm the export is legitimate

## Documentation

| Document | Purpose |
|----------|---------|
| `VISUAL_CHANGES.md` | Before/after screenshots and examples |
| `TESTING_GUIDE.md` | Step-by-step testing procedures |
| `MATERIAL_EXPORT_STATUS_IMPLEMENTATION.md` | Technical implementation details |
| `CHANGES_SUMMARY.md` | Complete list of changes |
| `verify_export_status.sh` | Database verification helper |

## Example Workflow

```
Day 1: Export for 50 students
├─> All show "Given"
├─> Materials distributed
└─> Mark export as 'granted'

Day 30: 25 new students purchase
└─> Export again (now 75 total students)
    ├─> First 50 students: "Granted" ✓
    └─> New 25 students: "Given" ⚠

Day 45: Materials distributed to new students
└─> Mark second export as 'granted'
```

## Troubleshooting

### Q: All students show "Given" even after marking as granted
**A:** The granted status only affects subsequent exports. Students in the current export always show their original status. Export again to see the difference.

### Q: Status column not showing in export
**A:** Clear browser cache and ensure database migration was applied.

### Q: Verification page shows error
**A:** Check that the verification code is correct and the export exists in database.

## Technical Details

### Database Schema
- `last_student_id`: Tracks the last student who purchased at export time
- `status`: Either 'given' (default) or 'granted' (manually set)

### Status Logic
```php
// System checks for previous granted exports
// If found, students who purchased before last_student_id show "Granted"
// All other students show "Given"
```

### API Changes
Export API now returns:
```json
{
  "rows": [
    {
      "user_id": 123,
      "status": "granted",  // NEW
      // ... other fields
    }
  ]
}
```

## Support

For questions or issues:
1. Check `TESTING_GUIDE.md` for common scenarios
2. Review `MATERIAL_EXPORT_STATUS_IMPLEMENTATION.md` for technical details
3. Contact system administrator for database access

## Version
- **Feature**: Material Export Status Tracking
- **Version**: 1.0
- **Date**: February 2026
- **Author**: GitHub Copilot Agent
