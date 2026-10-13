# Changelog

All notable changes to this project will be documented in this file.

## [0.4.1] - 2026-10-12 (ALPHA)

### Changed
- Renamed from local_profilefield_repeatable; no behaviour change.

## [0.4.0] - 2026-06-16 (ALPHA)

### Added
- Moodle 5.2 support (`$plugin->supported = [405, 502]`); CI runs the full PHP × DB
  matrix on Moodle 5.02 with 5.01/5.00/4.05 on PostgreSQL only.
- Automated release to the Moodle plugins directory via `moodle-release.yml`
  (triggered on `v*` tags).

### Changed
- CI runs on the moodle-an-hochschulen/moodle-workflows reusable workflow (PHPUnit +
  Behat on every PHP × DB leg), cross-installing the companion `profilefield_repeatable`.

## [0.3.0] - 2026-06-11 (ALPHA)

### Added
- Read-only capability `local/profilefield_repeatable:viewreference` for the
  `get_reference_labels` web service (cloned from `managereference` on upgrade, so existing
  role assignments keep read access); the write service still requires `managereference`
- Behat coverage: domain creation, invalid-shortname rejection, and CSV import scenarios

### Removed
- Legacy `local_pfr_*` runtime table fallback in resolver and manager (the upgrade step
  renames those tables, so the fallback was unreachable on current code); uninstall now also
  drops stray legacy tables

### Changed
- Form submit buttons renamed to "Save domain" / "Import items": they shared their label with
  the collapsible section headers, making the collapse toggle and the submit button
  indistinguishable for assistive technology and UI tests
- Submit buttons use named elements (`savedomainbutton` / `importitemsbutton`) instead of the
  moodleform default, fixing duplicate `id_submitbutton` DOM ids when both forms render on
  manage.php

## [0.2.0] - 2026-06-10 (ALPHA)

### Fixed
- Unknown-domain and empty-shortname errors now raise `moodle_exception`, so the localised
  message reaches the admin UI and web-service clients (previously the text was buried in
  `invalid_parameter_exception` debuginfo and admins saw a generic error)
- Removed case-duplicate `Manager.php` / `Resolver.php` index entries left by a rename on a
  case-insensitive filesystem
- Removed orphan `importerror` string (pt_br only, unused); English strings re-sorted
  alphabetically

### Added
- `manager::upsert_items_chunked()`: bounded per-chunk transactions for large imports; the
  admin CSV import now uses it (web services keep the hard `MAX_BATCH_SIZE` limit)
- `import_csv_form` validates the domain-shortname pattern like `create_domain_form`

### Changed
- `manage.php` escapes error notifications with `s()` (core notifications render raw HTML)
- Note: version.php was not bumped for the docs-only 0.1.1 entry; from 0.2.0 onward
  version.php and CHANGELOG move together

## [0.1.1] - 2026-04-02 (ALPHA)

### Changed
- Documentation aligned with Moodle 4.5+ database baseline (PostgreSQL 13+ / MySQL 8.0+)
- Clarified cross-database compatibility and integration notes with profilefield_repeatable

## [0.1.0] - 2026-04-01 (ALPHA)

### Added
- Initial alpha release of local_profilefield_repeatable plugin
- Static resolver API for code-to-label lookups with caching
- Domain and reference item CRUD operations via Manager class
- CSV import/parse for bulk reference data updates
- Web service API: `local_profilefield_repeatable_get_reference_labels`, `local_profilefield_repeatable_upsert_reference_items`
- Admin management interface at `/local/profilefield_repeatable/manage.php`
- Database tables: `local_profilefield_repeatable_domain`, `local_profilefield_repeatable_item` with proper indexing
- Privacy compliance: null provider (no personal data storage)
- Comprehensive unit tests for Manager and Resolver classes
- Support for Moodle 4.5+ (PostgreSQL and MySQL/MariaDB)

### Notes
- Alpha maturity: API subject to change before 1.0 release
- Requires dependency: profilefield_repeatable plugin (for actual field integration)
- Reference data storage only (no personal information)

---

## Version History

| Version | Release Date | Status | Notes |
|---------|--------------|--------|-------|
| 0.3.0   | 2026-06-11   | ALPHA  | Read capability, legacy fallback removal, Behat |
| 0.2.0   | 2026-06-10   | ALPHA  | Real error messages, chunked imports, hardening |
| 0.1.1   | 2026-04-02   | ALPHA  | Documentation updates (no version.php bump) |
| 0.1.0   | 2026-04-01   | ALPHA  | Initial release |

