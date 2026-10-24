# Bali Tarantula Adventure — Redesign Audit

Audit date: 2026-10-24
Target branch: redesign/clean-modern
Baseline branch: main
Baseline commit: 3dab0ff69794025c3ec8bc6613b0e1ac5be51792

## Goal

Redesign the public website and admin panel into a clean, bright, minimal interface while preserving existing PHP behavior, database contracts, booking rules, authentication, payments, media management, and SEO functionality. No production deployment is authorized by this audit.

## Repository baseline observed

The GitHub repository currently contains 32 tracked files across the public PHP pages, a compact admin area, PDO application helpers, CSS/JS assets, and a schema. The default branch is `main`; the repository connection reports push access.

Public pages present: home, about, contact, packages, package detail, booking, booking success, gallery, blog, and blog detail. Shared public templates are `includes/header.php` and `includes/footer.php`; primary public styles are in `assets/css/style.css`.

Current repository admin pages present: dashboard, login/logout, bookings, catalog, payment review, ticket redemption, and activity slots. The admin pages currently contain page-local inline CSS rather than a single shared admin shell.

Application foundation present: `app/Database.php`, `app/Auth.php`, `app/Security.php`, and `app/bootstrap.php`. The current repository schema is `database/schema.sql`.

## Separate uploaded backend archive found

The latest uploaded `Archive-2-tarantula-admin.zip` contains 94 non-macOS-metadata entries, including 55 PHP files under `tarantula-admin/`, shared helper/payment/media files under `includes/`, configuration under `config/`, and `database/balitarantula.sql`. The earlier `Archive 2.zip` uses `manuaba-portal/` instead of `tarantula-admin/`.

This archive is not yet represented in the current GitHub tree. Its structure and database contract differ from the current repository implementation. In particular:
- Archive admin pages use `config/database.php`, `includes/functions.php`, `tarantula-admin/includes/auth.php`, and the archive's `database/balitarantula.sql`.
- The current repository uses `app/bootstrap.php`, `app/Database.php`, `app/Auth.php`, and `database/schema.sql`.
- Both schemas have tables with overlapping names but different columns and status conventions. They must not be mixed or imported over one another without a deliberate compatibility plan.
- The archive's database config contains local MAMP credentials and must not be committed as a production config.
- The archive contains macOS metadata and an error_log file that should not be imported as application code.
- The archive's root public-site files are not included, so it is not a complete standalone website ZIP.

PHP CLI syntax checks on the extracted archive's PHP files reported no syntax errors. This is only a syntax check; it does not prove database compatibility, runtime behavior, payment integration, or complete route availability.

## Functionality to preserve

From the current GitHub implementation:
- PDO connection and environment/bootstrap behavior
- Admin login/logout, role authorization, session protections, and CSRF validation
- Activity/package creation and catalog management
- Booking form, slot/capacity reservation, temporary holds, expiration/release behavior
- Manual payment review, booking/payment statuses, ticket creation/redemption
- Existing public URLs, metadata, shared header/footer, and responsive behavior

From the uploaded admin archive, if adopted as the source of truth for the broader existing backend:
- Packages, categories, adventure tracks, package activity times, pickup areas/pricing, availability and capacity
- Bookings, booking details, payment proofs, gateway settings, invoices, reports, and exports
- E-ticket configuration and redemption-related flows
- Coupons, reviews, gallery, blog, editable pages, homepage content, header/footer/menu/social settings
- SEO metadata, Google Search Console configuration, media management, WebP/image tools, performance settings
- Admin users, security, backup, settings, and related CMS controls

## Redesign constraints

1. Visual changes should be separated from business logic wherever possible.
2. Preserve form field names, POST action names, query parameters, database writes, status values, and permission checks unless a verified defect requires a separate fix.
3. Use shared design tokens and reusable public/admin layout components; avoid broad CSS selectors that accidentally restyle forms or booking widgets.
4. Add cache-busting for stylesheets when deployed.
5. Do not expose secrets, local config files, logs, backups, or database dumps in Git.
6. Work on this feature branch; do not push changes directly to production hosting.
7. Test PHP syntax, critical form flows, admin permissions, mobile layouts, and database-backed flows before merge/deploy.

## Planned milestones

1. Resolve and document the canonical application/schema to use; keep the current GitHub baseline intact.
2. Build a clean-modern design system and shared public header/footer.
3. Redesign public pages one at a time, preserving their URLs and booking integration.
4. Build a shared admin shell (sidebar, topbar, content area, tables, forms, alerts, responsive behavior).
5. Apply the admin shell to each module without changing its PHP operations.
6. Run syntax checks and a regression checklist for booking, payment review, capacity, ticket redemption, media, and role restrictions.
7. Review diffs and only then prepare a pull request. Production deployment remains a separate explicit step.
