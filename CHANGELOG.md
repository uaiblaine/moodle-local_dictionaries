# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.5.0] - 2026-10-12 (ALPHA)

First release under the name `local_dictionaries`. The plugin was born from
`local_profilefield_repeatable` 0.4.0, the reference dictionaries of the
`profilefield_repeatable` user profile field, renamed because nothing in it is tied to profile
fields. It is a new plugin: no site had the old one in production, so nothing is migrated
from it, and the old `local_pfr_*` upgrade step and uninstall cleanup are gone.

### Changed
- Component, namespaces, tables (`local_dictionaries_domain`, `local_dictionaries_item`),
  capabilities (`local/dictionaries:manage`, `local/dictionaries:view`), web services
  (`local_dictionaries_*`, service `local_dictionaries_api`), admin page and URL
  (`/local/dictionaries/manage.php`) and lang files carry the new name.
- Supports Moodle 4.5 to 5.3 (`$plugin->supported = [405, 503]`).
- Codes are case-insensitive on every database: a lowercased `codekey` carries the unique
  index, `code` keeps the spelling of the last write, and `resolve_bulk()` keys its result by
  the caller's spelling. Before, MariaDB collided `ABC` with `abc` and failed the batch while
  PostgreSQL stored two items.
- Codes are also accent-insensitive on every database (`são` and `sao` are one item): the
  `codekey` folds accents by canonical decomposition, so PostgreSQL agrees with MariaDB.
  `errorcodecollision` now covers only the foldings the key does not do (`ß`/`ss`).
- `\local_dictionaries\api::can_read()` and `require_read()` are the public read gate; the web
  services use them. `api::search()` takes `?string $parentdomain` and constrains the parent
  to that domain (default: the searched domain), and `search_items` has a `parentdomain`
  parameter whose domain needs read access too.
- The read web services return labels with multilang resolved and tags stripped
  (`core_external\util::format_string`, no entity escaping); the client still escapes.
- `upsert_items_chunked()` fires one `items_imported` event per committed chunk.
- The admin page is registered for holders of `local/dictionaries:manage` without
  `moodle/site:config`.
- The label cache keys on a per-domain revision bumped by every write, instead of purging the
  whole cache.
- The read web services refuse the guest user and decide on the domain's recorded visibility;
  both read functions use a read-only session.
- The CSV import parses quoted multi-line fields, strips a UTF-8 byte order mark, detects
  comma, semicolon and tab delimiters, and reports rejected rows (invalid UTF-8, too few
  columns, empty or over-long code) with line numbers instead of dropping them; duplicates
  are counted, last row wins.
- Codes longer than 255 characters are refused instead of being cut.

### Added
- Search data model: `searchkey`, `sortorder`, `active` and `parentid` on items;
  `description`, `visibility` and `revision` on domains.
- `\local_dictionaries\api::search()`, the `local_dictionaries_search_items` web service and
  the `local_dictionaries/autocomplete_transport` AMD module for `core/form-autocomplete`.
- `resolver::DOMAIN_PATTERN`, public, for consumers to read instead of copying.
- Events `domain_created`, `domain_updated` and `items_imported` (one per batch, counts in
  `other`).
- Domain list shows item count and visibility, with an edit link; the domain form edits the
  description and visibility; the import form takes an optional parent domain.
- Test data generator (`create_domain`, `create_item`).

### Removed
- Dead lang strings `wsgetlabelsdesc` and `wsupsertdesc`.
