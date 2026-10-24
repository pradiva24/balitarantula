# Bali Tarantula Adventure

Responsive homepage concept for Bali Tarantula Adventure using a bold black-and-orange extreme-adventure visual direction.

## Project structure

```
index.php
assets/
  css/
    style.css
  js/
    main.js
```

## Preview locally

Open `index.php` in a browser. For local development, use the Live Server extension in VS Code.

## Before launch

1. Replace the placeholder WhatsApp URL `https://wa.me/` in `index.php` with the official business number, using international digits only.
2. Replace stock photography with approved Bali Tarantula Adventure images.
3. Confirm package names, descriptions, pricing, contact details, and booking destinations.
4. Review third-party image and Google Fonts requests for production.

## Next phase

Connect the approved frontend to PHP Native templates and add MySQL/PDO-backed package and booking functionality.


## File types
- Website pages use PHP (`.php`).
- Stylesheets and browser scripts remain in `assets/css/` (`.css`) and `assets/js/` (`.js`), because these are static asset types and should not be renamed to PHP.
- The current pages are still mostly static frontend templates. The contact form is a demo and is not connected to email or a booking backend yet.
- Deploy manually to the hosting environment only after reviewing and approving the changes.


## Admin CMS
- Shared responsive sidebar is available across the admin pages.
- Use `/admin/homepage-editor.php` to update homepage headings, descriptions, hero slider images, activity and track card text/images, reviews, and shared header/footer copy.
- Fresh installs get the `cms_content` table from `database/schema.sql`. Existing databases should run `database/migrations/20261024_homepage_cms.sql` if the table is missing.
- Public content uses fallback text if the CMS table is not installed. Image uploads need write permission for `assets/uploads/homepage/`.


## Global Website Settings
- Admin page: `/admin/settings.php` (owner/admin roles).
- Configure website title and description, upload the logo, update contact details, and manage social media links.
- SMTP host, port, encryption, username, sender identity, and encrypted password are configurable, with a test-email action.
- Existing databases must import `database/migrations/20261024_site_settings.sql` if `site_settings` is missing. Fresh installations include the table in `database/schema.sql`.
- SMTP test sending is implemented; booking notification flows need to call `bta_smtp_send()` to use this transport.
