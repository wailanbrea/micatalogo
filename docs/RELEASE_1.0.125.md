# MiCatalogo 1.0.125 — 2026-10-20

Android versionCode 126, package `com.bsolutions.micatalogo`.
Source: `wailanbrea/micatalogowebApp`, commit `cfea2cb`.
URL: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.125-release.apk
APK SHA-256: `786d99c3d9f10e2d1893d05a1aa0f402f32774d6ea7f5dcbddcc747c22e07026`.
Size: 83,910,300 bytes. Certificate matches 1.0.124; production package, non-debuggable.

Includes business-timezone finance dates, audited reconciliation adjustments,
collection and synchronization improvements, invoice photo reading and practice mode.
Web now supplies `business_timezone` from the configured shop settings.

Validation: 718 Web tests / 7,996 assertions, full Android unit suite passed,
and the isolated connected reconciliation test passed before release preparation.
The public APK was downloaded and its signature, package, version, size and hash verified.
Public homepage returned HTTP 200.

Before deployment, 19 modified production files and the three new implementation files
matched local sources. Preserved the 23 remote modified/untracked files in a private
archive and backed up ShopController separately before replacement.
The adjustments migration was already applied; no migration or seeder was executed.

Database backup: 74 tables, 3,846 rows, 2,212,611 bytes; schema/row statements, footer
and SHA-256 verified. A second local private copy has the same hash:
`ecfa4f195133c8b6bd7ae70144c8ee92f3605d19a2c4c36067883128ee24eba2`.
Before/after user and shop identifiers match exactly: 14 users, 12 shops.
Private snapshots and backups remain outside public/ and Git.

Production release manifest updated and configuration cache rebuilt.
Previous APK remains available. No real-device installation was performed.
