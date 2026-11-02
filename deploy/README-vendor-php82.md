# PHP 8.2 production vendor package

This repository includes a GitHub Actions workflow to build the production Composer dependencies for PHP 8.2.

## Build the package

1. Open the repository on GitHub.
2. Go to **Actions**.
3. Select **Build PHP 8.2 vendor package**.
4. Click **Run workflow** and select the `main` branch.
5. Wait for the workflow to finish successfully.

The workflow resolves the production dependencies from `composer.json`, validates Dompdf and the barcode library, creates `deploy/vendor-php82.zip`, and commits both the archive and `composer.lock` to `main`.

## Install on cPanel

1. Download `deploy/vendor-php82.zip` from the repository after the workflow succeeds.
2. Upload it using cPanel **File Manager** into the website's project root (the same folder as `composer.json`).
3. Extract the ZIP there. It must create a `vendor/` folder in the project root.
4. Confirm that `vendor/autoload.php` exists.

The ZIP contains only Composer's `vendor/` directory. It does not contain application files, secrets, or generated E-Ticket PDFs.

The package is built on GitHub Actions using PHP 8.2. Rebuild it whenever `composer.json` changes.
