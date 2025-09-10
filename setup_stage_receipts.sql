-- =====================================================
-- STAGE RECEIPTS DATABASE SETUP
-- =====================================================
-- This script sets up the stage_receipts table for storing receipt images
-- Run this script in your MySQL database to create the table

-- Drop table if exists (for clean setup)
DROP TABLE IF EXISTS stage_receipts;

-- Create the stage_receipts table
CREATE TABLE stage_receipts (
  id int(11) NOT NULL AUTO_INCREMENT,
  lead_id int(11) NOT NULL,
  stage_type varchar(50) NOT NULL,
  filename varchar(255) NOT NULL,
  original_name varchar(255) NOT NULL,
  file_path varchar(500) NOT NULL,
  file_size int(11) DEFAULT NULL,
  mime_type varchar(100) DEFAULT NULL,
  uploaded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by int(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_lead_stage (lead_id, stage_type),
  KEY idx_uploaded_at (uploaded_at),
  KEY idx_stage_type (stage_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert some sample data for testing (optional)
-- INSERT INTO `stage_receipts` (`lead_id`, `stage_type`, `filename`, `original_name`, `file_path`, `file_size`, `mime_type`, `created_by`) VALUES
-- (1, 'downpayment', 'test_receipt_1.jpg', 'receipt_1.jpg', 'uploads/receipts/test_receipt_1.jpg', 1024, 'image/jpeg', 1);

-- =====================================================
-- VERIFICATION QUERIES (Run these separately if needed)
-- =====================================================
-- Check if table exists
-- SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME 
-- FROM information_schema.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'stage_receipts';

-- Check table structure
-- DESCRIBE stage_receipts;

-- Check indexes
-- SHOW INDEX FROM stage_receipts;

-- =====================================================
-- UPLOAD DIRECTORY SETUP (PHP)
-- =====================================================
-- After running this SQL, also run the PHP script below to create upload directories

/*
<?php
// Run this PHP script to create the upload directory structure
$upload_dirs = [
    'uploads/',
    'uploads/receipts/',
    'uploads/receipts/downpayment/',
    'uploads/receipts/installment/',
    'uploads/receipts/turnover/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir\n";
        }
    } else {
        echo "Directory already exists: $dir\n";
    }
}

// Create .htaccess file to protect uploads
$htaccess_content = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf)$\">\nAllow from all\n</Files>";
file_put_contents('uploads/.htaccess', $htaccess_content);
echo "Created .htaccess file for upload protection\n";
?>
*/

-- =====================================================
-- TEST QUERIES (Run these separately if needed)
-- =====================================================
-- Test insert (replace lead_id with actual lead ID from your leads table)
-- INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type) 
-- VALUES (1, 'downpayment', 'test_file.jpg', 'test_file.jpg', 'uploads/receipts/test_file.jpg', 1024, 'image/jpeg');

-- Test select
-- SELECT * FROM stage_receipts WHERE lead_id = 1 AND stage_type = 'downpayment';

-- Test delete (cleanup)
-- DELETE FROM stage_receipts WHERE lead_id = 1 AND stage_type = 'downpayment';
