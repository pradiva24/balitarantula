# Setup guide

## Requirements
- PHP 8.1 or newer with PDO MySQL enabled
- MySQL 8 or compatible MariaDB
- HTTPS on production hosting

## Install
1. Create an empty database and a dedicated database user in cPanel.
2. Import `database/schema.sql` with phpMyAdmin. This includes the booking tables and homepage CMS storage.
3. If the database already existed before the homepage editor was added, import `database/migrations/20261024_homepage_cms.sql` only when the `cms_content` table is missing.
4. Copy `config/config.example.php` to `config/config.php`; enter the database name, username, and password.
5. Keep `config/config.php` private and do not commit it.
6. Create the first admin from a command line: `php bin/create-admin.php "Admin Name" admin@example.com`. Set `BTA_ADMIN_PASSWORD` as an environment variable. Do not run the script from a browser.
7. Sign in at `/admin/login.php`.
8. Add an activity and package at `/admin/catalog.php`, then add dates and start times at `/admin/slots.php`.
9. Test a booking at `/booking.php`.

## Admin content editing
- Open `/admin/homepage-editor.php` to edit homepage copy, hero slider images, adventure cards, track cards, section images, reviews, header labels, and footer copy.
- Upload images as JPG, PNG, WEBP, or GIF, up to 8 MB each. The hosting account must allow writes to `assets/uploads/homepage/`; the editor will try to create this directory if it does not exist.
- The editor saves copy and image paths in `cms_content`. Existing database installations must run the migration above once.

## Important before production
The current booking implementation creates a temporary reservation and a manual-transfer payment record. Configure the business's real payment instructions, verify the expiry/release job, and test payment review and redemption before taking live bookings. The payment gateway is not connected yet. Never issue a ticket until a trusted payment verification step is complete.

Do not merge to `main` or deploy to cPanel until the code review, PHP syntax checks, schema review, end-to-end tests, and backup are complete. Deployment must be a manual action approved by the owner.
