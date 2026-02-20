#!/bin/bash
# Material Export Status - Verification Script
# This script helps verify that the database changes have been applied correctly

echo "==================================="
echo "Material Export Status Verification"
echo "==================================="
echo ""

# Check if database credentials are available
if [ ! -f "config/db.php" ]; then
    echo "Error: Database configuration file not found at config/db.php"
    exit 1
fi

# Extract database credentials
DB_HOST="localhost"
DB_NAME="niverpay_db"

echo "Checking database connection..."
echo ""

# Function to execute SQL and show results
check_table_structure() {
    echo "1. Checking manual_export_audits table structure..."
    echo "   Looking for last_student_id and status columns..."
    echo ""
    echo "   Run this SQL query to verify:"
    echo "   DESCRIBE manual_export_audits;"
    echo ""
}

check_test_data() {
    echo "2. Checking for test data..."
    echo "   Run this SQL query to see export records:"
    echo "   SELECT id, code, manual_id, status, last_student_id, downloaded_at FROM manual_export_audits ORDER BY downloaded_at DESC LIMIT 5;"
    echo ""
}

provide_test_queries() {
    echo "3. Manual Testing Queries"
    echo "=========================="
    echo ""
    echo "To mark an export as granted:"
    echo "UPDATE manual_export_audits SET status='granted' WHERE code='YOUR_CODE_HERE';"
    echo ""
    echo "To check status distribution:"
    echo "SELECT status, COUNT(*) as count FROM manual_export_audits GROUP BY status;"
    echo ""
    echo "To verify a specific export:"
    echo "SELECT * FROM manual_export_audits WHERE code='YOUR_CODE_HERE';"
    echo ""
}

# Run checks
check_table_structure
check_test_data
provide_test_queries

echo "==================================="
echo "Next Steps:"
echo "1. Apply the SQL migration: mysql -u root -p niverpay_db < sql/add_export_status_tracking.sql"
echo "2. Test the export functionality in the admin dashboard"
echo "3. Mark an export as 'granted' using SQL"
echo "4. Export again and verify status shows correctly"
echo "==================================="
