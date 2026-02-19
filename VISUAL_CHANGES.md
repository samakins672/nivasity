# Visual Changes Overview

## 1. Export List Display (admin/index.php)

### Before:
```
+-----+------------------+------------+-----------+-----------+
| S/N | NAMES            | MATRIC NO  | ADM YEAR  | PRICE PAID|
+-----+------------------+------------+-----------+-----------+
| 1   | John Doe         | 12345      | 2020      | 5000      |
| 2   | Jane Smith       | 12346      | 2020      | 5000      |
| 3   | Bob Johnson      | 12347      | 2021      | 5000      |
+-----+------------------+------------+-----------+-----------+
```

### After:
```
+-----+------------------+------------+-----------+-----------+----------+
| S/N | NAMES            | MATRIC NO  | ADM YEAR  | PRICE PAID| STATUS   |
+-----+------------------+------------+-----------+-----------+----------+
| 1   | John Doe         | 12345      | 2020      | 5000      | Granted  |
| 2   | Jane Smith       | 12346      | 2020      | 5000      | Granted  |
| 3   | Bob Johnson      | 12347      | 2021      | 5000      | Given    |
+-----+------------------+------------+-----------+-----------+----------+
```

**New Column Added**: STATUS column showing "Given" or "Granted" based on export history

---

## 2. Export Metadata Section

### Before:
```
Verification Code: A7K9Q2L8M3
Total Students: 75
Total Amount: ₦ 375,000
Date Exported: 19 Feb 2026, 2:27pm
HOC: Admin User (admin@example.com)
```

### After (Same - No Changes):
```
Verification Code: A7K9Q2L8M3
Total Students: 75
Total Amount: ₦ 375,000
Date Exported: 19 Feb 2026, 2:27pm
HOC: Admin User (admin@example.com)
```

**Note**: Metadata section remains unchanged. Status is now saved to database but not shown in header.

---

## 3. Verification Page (manual-export-verify.php)

### Before:
```
┌──────────────────────────────────────────────────────────┐
│  Result for Code: A7K9Q2L8M3                             │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  Manual                         HOC                       │
│  BIO101 — Introduction to Bio   John Doe                 │
│  Internal ID: M001              Computer Science Dept    │
│                                 john@example.com          │
│                                                           │
│  Total Students    Total Amount        Date Exported      │
│  75                ₦ 375,000          19 Feb 2026, 2:27pm│
│                                                           │
└──────────────────────────────────────────────────────────┘
```

### After:
```
┌──────────────────────────────────────────────────────────┐
│  Result for Code: A7K9Q2L8M3                             │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  Manual                         HOC                       │
│  BIO101 — Introduction to Bio   John Doe                 │
│  Internal ID: M001              Computer Science Dept    │
│                                 john@example.com          │
│                                                           │
│  Total Students  Total Amount  Status      Date Exported │
│  75              ₦ 375,000     Given       19 Feb 2026   │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

**New Field**: STATUS column added showing "Given" or "Granted"
**Layout Change**: Changed from 3-column to 4-column grid

---

## 4. Database Structure

### manual_export_audits Table - Before:
```
+----------------+--------------+
| Field          | Type         |
+----------------+--------------+
| id             | INT(11)      |
| code           | VARCHAR(25)  |
| manual_id      | INT(11)      |
| hoc_user_id    | INT(11)      |
| students_count | INT(11)      |
| total_amount   | INT(11)      |
| downloaded_at  | DATETIME     |
+----------------+--------------+
```

### manual_export_audits Table - After:
```
+------------------+--------------+
| Field            | Type         |
+------------------+--------------+
| id               | INT(11)      |
| code             | VARCHAR(25)  |
| manual_id        | INT(11)      |
| hoc_user_id      | INT(11)      |
| students_count   | INT(11)      |
| total_amount     | INT(11)      |
| downloaded_at    | DATETIME     |
| last_student_id  | INT(11)      | <- NEW
| status           | VARCHAR(20)  | <- NEW
+------------------+--------------+
```

**New Columns**:
- `last_student_id`: Tracks the last student who purchased when export was made
- `status`: 'given' or 'granted' to indicate export type

---

## 5. API Response Changes

### Before:
```json
{
  "status": "success",
  "code": "A7K9Q2L8M3",
  "rows": [
    {
      "name": "John Doe",
      "matric_no": "12345",
      "adm_year": "2020",
      "price": 5000
    }
  ]
}
```

### After:
```json
{
  "status": "success",
  "code": "A7K9Q2L8M3",
  "rows": [
    {
      "user_id": 123,
      "name": "John Doe",
      "matric_no": "12345",
      "adm_year": "2020",
      "price": 5000,
      "status": "granted"
    }
  ]
}
```

**New Fields**:
- `user_id`: Student's user ID (used internally)
- `status`: "given" or "granted" for each student

---

## Usage Flow

### Scenario: First Export → Grant → Second Export

#### Step 1: First Export (All "Given")
```
Admin exports material
└─> 50 students purchased
    └─> All show "Given" status
        └─> Export saved with status='given', last_student_id=50
```

#### Step 2: Mark as Granted
```sql
UPDATE manual_export_audits SET status='granted' WHERE code='ABC123';
```

#### Step 3: Second Export (Mixed Status)
```
5 new students purchase material (total: 55 students)
Admin exports same material again
└─> System checks previous granted export (last_student_id=50)
    ├─> Students 1-50: Show "Granted" (from previous grant)
    └─> Students 51-55: Show "Given" (new purchases)
        └─> Export saved with status='given', last_student_id=55
```

---

## Color Coding Suggestion (Future Enhancement)

To make the status more visually distinct, consider adding CSS:

```css
.status-granted {
  color: #28a745; /* Green */
  font-weight: bold;
}

.status-given {
  color: #007bff; /* Blue */
  font-weight: bold;
}
```

This would make "Granted" appear in green and "Given" in blue for quick visual identification.
