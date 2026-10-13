# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- `\local_dictionaries\api::lookup()`: looks up many codes of one domain for server-side
  validation (stored code, raw label, active flag, parent code and parent domain), matching like
  `resolve_bulk()`. It runs no capability check and returns inactive items too.
- `\local_dictionaries\api::list_domains()`: a public listing of the domains (shortname, raw name
  and description, visibility, active item count, parent domain) for consumer plugins' definition
  forms. It runs no capability check.

### Fixed
- The manage page applies `format_string()` to the domain name and description, so multilang
  spans resolve (the template still escapes once).
- The `code` parameter of `local_dictionaries_upsert_reference_items` and the CSV help now say
  that codes are matched ignoring case and accents.
- The upgrade to 2026042001 no longer runs a `change_field_default` loop that changed nothing.
- The `codekey` column comment in `install.xml` says what the key is.

## [0.5.0] - 2026-10-12 (ALPHA)

First release under the name `local_dictionaries`. The plugin was born from
`local_profilefield_repeatable` 0.4.0, the reference dictionaries of the
`profilefield_repeatable` user profile field, renamed because nothing in it is tied to profile
fields. It is a new plugin: no site had the old one in production, so nothing is migrated
from it, and the old `local_pfr_*` upgrade step and uninstall cleanup are gone.

A site that already installed the 0.4.1 rename (version 2026042000) is upgraded by
`db/upgrade.php` (version 2026042001): it adds the domain `description`, `visibility` and
`revision` and the item `codekey`, `searchkey`, `sortorder`, `active` and `parentid` columns,
fills both keys from the existing rows, merges items whose codes are equal ignoring case and
accents inside one domain (the most recently modified row survives, the others are deleted and
each merge is printed), then replaces the `(domainid, code)` unique index with
`(domainid, codekey)` and adds the search and parent indexes.

The predefined service is renamed from "Profilefield Repeatable Reference API" to "Reference
dictionaries API". Core matches a plugin's services by name, so the upgrade from 0.4.1 deletes
the old service with its tokens and authorised users: issue the tokens again after upgrading.

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
