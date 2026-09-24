# Contributing to MustrHQ Stock

Thanks for taking the time to help. MustrHQ Stock is used on real shop floors, so the bar is
simple: changes should keep it easy to install on ordinary shared hosting and easy to use with
one thumb in a stockroom.

## Ground rules

- **Plain PHP and MySQL.** No frameworks, no Composer, no Node, no build step. Anything that
  cannot be uploaded to cPanel and run as-is will not be merged.
- **PHP 7.4 or newer** must keep working. Avoid features that need a newer version.
- **No third-party requests.** Fonts, scripts and icons are bundled. Nothing phones home.
- **Security first.** Prepared statements for all SQL, `h()` for all output, a CSRF token on
  every form, and role checks on every page that changes data.
- **Database changes go in `inc/schema.php`** and must upgrade existing installs without
  touching data — add tables and columns, never drop or rewrite.

## Reporting a bug

Open an issue using the **Bug report** template. The most useful reports include the version
(Admin → Updates & backups, or `version.php`), PHP and MySQL versions, what you did, what you
expected and what happened instead. Screenshots help.

**Security problems should not go in a public issue** — see [SECURITY.md](SECURITY.md).

## Suggesting a feature

Open an issue using the **Feature request** template and describe the job you are trying to
get done in the shop or kitchen, not just the button you would add. That makes it much easier
to find a design that works for everyone.

## Sending a change

1. Fork the repository and create a branch from `main`.
2. Set up a local copy: any PHP 7.4+ with MySQL/MariaDB works (MAMP, Laravel Herd, XAMPP,
   or `php -S localhost:8000`). Open `install.php` and load the example data.
3. Make your change. Keep to the existing style: small functions, readable names, comments
   that explain *why*.
4. Check it at desktop and phone widths, and as each role (staff, manager, admin).
5. Run `php -l` over any PHP file you touched.
6. If users will notice the change, add a line under an **Unreleased** heading in
   `CHANGELOG.md`.
7. Open a pull request and fill in the template.

## Licence of contributions

By contributing, you agree that your contribution is licensed under the
[GNU AGPL-3.0](LICENSE), the same licence as the project.

## Code of conduct

Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
