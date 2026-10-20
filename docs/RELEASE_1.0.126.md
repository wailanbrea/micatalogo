# MiCatalogo 1.0.126 — Operational reports

Published 2026-10-20. Android versionCode 127, production package
`com.bsolutions.micatalogo`, source Android commit `ea83f27`.

Download: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.126-release.apk
Size: 83,970,880 bytes.
SHA-256: `f53b3e41e733f8c6252fafda1563d215c4e718cfb6e6e1f1d144b439611a460a`.
Signed with the existing production certificate; compatible with 1.0.125.

## Behavior

- Dedicated report screen using dashboard spacing, surfaces, typography and palette.
- Interface labels moved to Android resources, with plural record counts.
- Sections, compact KPI grid, detail search, contextual calculation help and empty states.
- Sales filters: today, last seven days and month, based on shop timezone.
- Complete profitability detail, without the previous 10/20-row cutoffs.
- Inventory separated from sales; current, 30-day and cumulative scopes are explicit.
- CSV/Excel sales exports send the selected sales dates.
- Native searchable A4 PDFs preserve the selected section, scope, metrics and searched detail.
- Share PDF via WhatsApp, WhatsApp Business or the Android share chooser.
- Android printing uses the actual page count, honors page selection and cancellation.

## Verification

- Full Web suite: 720 passed, 8,009 assertions.
- Full Android unit suite passed during signed release build.
- Three report instrumentation cases passed on the isolated emulator/local SQLite API.
- Cases cover dashboard controls/search/empty states, live API-to-UI data and Android
  share chooser, PDF content URI grants, print dialog, page selection and cancellation.
- Long PDF fixture: 90 records across 16 pages; extracted text preserves all 90,
  including the last record. First, middle and final pages inspected visually.
- Public APK downloaded and rechecked: certificate, package, version, size and SHA-256.
- Homepage HTTP 200; production read-model check passed with five sections and context.

## Deployment safety

Existing remote controller matched its saved baseline before replacement.
Controller and release manifest saved privately before deployment.
Database backup: 74 tables, 3,847 rows, 2,213,103 bytes. SQL structure/footer and hash
verified, plus a second local private copy with matching SHA-256:
`5df7d5ae9a89de8c5b8d543109d45ca8129f0dc54afd79a5c0dfff29a87428db`.
User/shop identifiers match exactly before and after: 14 users, 12 shops.
No migrations or seeders required. Previous APK preserved.
Tests used debug on the isolated emulator; no installation on a physical device
or physical printer job was performed.
