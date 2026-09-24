# Changelog

All notable changes to MustrHQ Stock. Versions follow [Semantic Versioning](https://semver.org/).

## Unreleased
- Published as open source under the GNU AGPL-3.0.

## 1.4.0
- Barcode scanning on count, waste, goods-in, QCP, ordering and lookup — phone camera, handheld
  scanners, or typed. First-time barcodes are taught once by a manager; case barcodes count as a pack.
- Admin → Barcodes, with a setup mode for linking a whole stockroom.
- Installable app (PWA) with home-screen shortcuts and an offline screen.
- Count and waste numbers are kept on the device until the server confirms the save.
- Admin → Updates & backups: install a release zip, automatic file and database backups, one-click rollback.
- The database upgrades itself when the files are newer, including after an FTP copy.
- Works on hosts without the mbstring extension.

## 1.3.0
- Security hardening: installer locks itself, login throttling, idle sign-out, POST sign-out,
  security headers, secure cookies. PHP and MySQL share one clock.

## 1.2.0
- Out-of-stock prompts and one-click reorder. Full interface redesign; self-hosted font.

## 1.1.0
- Ordering: suppliers, par levels, suggested orders, printable purchase orders, receiving.
- Four-step install wizard.

## 1.0.0
- Stock counts, ingredient and stales waste, quality checkpoint, goods in, stock loss report, admin panel.
