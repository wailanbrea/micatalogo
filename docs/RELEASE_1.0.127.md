# MiCatalogo 1.0.127 — 2026-10-21

Android versionCode 128; source commit `44a39e9` in micatalogowebApp.
Production package: `com.bsolutions.micatalogo`.
Download: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.127-release.apk
APK size: 83,970,880 bytes.
SHA-256: `2a829f9cdb9222f353abe686a436b701c294682013f78b315082ad15c0dca3ba`.
Existing production certificate preserved and checked against 1.0.126.

## Corrections

- Printing stores the latest layout attributes and uses PrintedPdfDocument for
  selected media size, orientation and printable content rectangle.
- Original report pages scale proportionally into that rectangle, centered,
  preserving page selection and cancellation behavior.
- Reports switch to one-column metrics and full-width actions below 380 dp or
  above font scale 1.25. Financial values remain on one line with horizontal
  scrolling available for unusually long values; text is not truncated.
- Detail values and status labels stack in the accessible layout.

## Validation

169 Android unit tests passed during the signed release build.
Five report instrumented cases passed on the isolated emulator/local SQLite API,
including the two regressions from the previous evaluation:

- Letter selection produces 612 x 792 points, confirmed with PdfRenderer and pypdf.
- At 320 dp / font scale 1.6, the full test amount fits and action labels use one line.
- Existing PDF multipage, page selection/cancellation, search/empty-state and live
  API/share chooser/print dialog checks pass.

Screenshots and rendered Letter PDF reviewed under qa/reports-fixed-20261021/.
The layout test checks actual glyph bounds because the horizontally scrollable
paragraph is measured with unbounded width.

Public manifest and complete downloaded APK verified for version, size, hash,
package, certificate and non-debuggable status. No physical-device installation,
physical printer output or message to a WhatsApp contact was performed.

## Publication controls

Only the public APK and update manifest changed on the server; no backend code,
migration or seeder was deployed. Previous release preserved.
Private manifest backup verified before replacement.
Database backup: 74 tables, 3,850 rows, 2,214,735 bytes. Structure/footer and hash
verified, and a second local private copy has matching SHA-256:
`8a7a67f0f87fec03be7f0d97e3574f467fcbde160fac1f69ae10d5ebf7355faf`.
Before/after user/shop IDs match exactly: 14 users and 12 shops.
