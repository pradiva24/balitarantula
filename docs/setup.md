# Setup guide

## Requirements
- PHP 8.1 or newer with PDO MySQL enabled
- MySQL 8 or compatible MariaDB
- HTTPS on production hosting

## Install
1. Create an empty database and a dedicated database user in cPanel.
2. Import `database/schema.sql` with phpMyAdmin.
3. Copy `config/config.example.php` to `config/config.php`; enter the database name, username, and password.
4. Keep `config/config.php` private and do not commit it.
5. Create the first admin from a command line: `php bin/create-admin.php "Admin Name" admin@example.com`. Set `BTA_ADMIN_PASSWORD` as an environment variable. Do not run the script from a browser.
6. Sign in at `/admin/login.php`.
7. Add an activity and package at `/admin/catalog.php`, then add dates and start times at `/admin/slots.php`.
8. Test a booking at `/booking.php`.

## Important before production
The current booking implementation creates a temporary reservation and a manual-transfer payment record. Configure the business's real payment instructions, verify the expiry/release job, and test payment review and redemption before taking live bookings. The payment gateway is not connected yet. Never issue a ticket until a trusted payment verification step is complete.

Do not merge to `main` or deploy to cPanel until the code review, PHP syntax checks, schema review, end-to-end tests, and backup are complete. Deployment must be a manual action approved by the owner.
