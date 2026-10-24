-- Run in phpMyAdmin (database: barangay_incident_db).
-- Your "role" column is already VARCHAR(50), so "super_admin" works as is.

-- Make ONE account the super admin (use the email you want):
UPDATE users
SET role = 'super_admin'
WHERE email = 'barangaypitipiwpiw@gmail.com';
