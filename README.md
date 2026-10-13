moodle-local_dictionaries
=========================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_dictionaries/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_dictionaries/actions/workflows/ci.yml?query=branch%3Amain)

Reference dictionaries for Moodle: named code lists (domain, code, label) that other plugins
resolve and search.

A *domain* is one code list, such as the ISO 639 languages, the Brazilian states or a
company's regional boards. Each *item* has a code (what a consumer stores), a label (what
people read), a sort order, an active flag and, optionally, a parent item, possibly in another
domain (a municipality's state, for example). Plugins store codes and ask this plugin for
labels when they display them, and for matching items when someone types in an autocomplete
field. The first consumer is the user profile field `profilefield_repeatable`; the plugin
itself knows nothing about profile fields.

The plugin owns two tables and stores no personal data.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.3)


Installation
------------

Install the plugin like any other plugin to folder `/local/dictionaries`.

See http://docs.moodle.org/en/Installing_plugins for details on installing
Moodle plugins.

No configuration is needed. Domains are created on the management page or through the web
services.


Usage
-----

### Managing domains

Site administration > Plugins > Local plugins > Manage reference dictionaries.

- **Create or update domain**: a shortname (lowercase letters, digits and underscore; it
  never changes), a name, a description and who can look up its items through the web
  services. The domain list shows the item count and the visibility, with an edit link per
  domain.
- **Import CSV**: upload a file or paste text into a domain. Columns are `code,label`; a
  first row naming the columns is a header and may add `parentcode`, `sortorder` and `active`
  (0 or 1) in any order. The delimiter (comma, semicolon or tab) is detected from the first
  line, a UTF-8 byte order mark is ignored and quoted fields may span lines. Rows that are not
  valid UTF-8, have fewer than two columns, an empty code or a code longer than 255 characters
  are rejected and listed with their line numbers (the first 20); they are never dropped
  silently. A code repeated in the file keeps its last row. Parent codes are looked up in the
  optional parent domain, or in the imported domain itself.

Codes are case-insensitive and accent-insensitive on every database: `ABC`, `abc` and `ábc` are
one item, which keeps the spelling of the last write. Inactive items still resolve, so stored codes keep displaying, but search no longer
offers them.

### PHP API (for consumer plugins)

```php
\local_dictionaries\resolver::resolve(string $domain, string $code): ?string;
\local_dictionaries\resolver::resolve_bulk(string $domain, array $codes): array; // Keyed by the caller's spelling.
\local_dictionaries\resolver::domain_exists(string $domain): bool;
\local_dictionaries\resolver::DOMAIN_PATTERN; // '/^[a-z0-9_]+$/'
\local_dictionaries\api::search(
    string $domain,
    string $query,
    int $limit = 20,
    ?string $parentcode = null,
    ?string $parentdomain = null
): array;
// ['items' => [['code' => ..., 'label' => ...], ...], 'hasmore' => bool]
\local_dictionaries\api::lookup(string $domain, array $codes): array;
// Keyed by the caller's spelling: ['code', 'label', 'active', 'parentcode', 'parentdomain']; unknown codes absent.
\local_dictionaries\api::list_domains(): array;
// [['shortname', 'name', 'description', 'visibility', 'itemcount', 'parentdomain'], ...] by name.
\local_dictionaries\api::can_read(string $domain, ?int $userid = null): bool;
\local_dictionaries\api::require_read(string $domain): void;
```

The API returns raw labels: escape them when you print them. `resolver` and `api::search()` run
no capability check and return nothing, instead of failing, when the plugin tables are missing.
Probe them with `class_exists('\local_dictionaries\resolver')` when the dependency is optional.

Who may read a domain is decided by `api::can_read()` / `api::require_read()`, the one
implementation the web services use too: a domain open to logged-in users is readable by any
logged-in non-guest user; any other domain, an unknown one included, needs
`local/dictionaries:view`. Resolving codes the viewer already sees (stored values in a record the
viewer may read) needs no gate. Browsing or searching a domain does: call `require_read()`
before `api::search()`, and again for `$parentdomain` when you pass a parent code. The schema
records a parent by item id only, so `$parentdomain` names the domain holding the parents; null
means the searched domain itself.

`api::list_domains()` feeds a select in a definition form or an admin page. It runs no capability
check, like `resolver`: it exposes the name, description and visibility of every domain, restricted
ones included, so show it only to users who configure fields. `name` and `description` are raw
(apply `format_string()`); `itemcount` counts active items; `parentdomain` is the shortname of the
domain holding the parents of the domain's items when they all sit in one domain (itself
included), and null when no item has a parent or the parents are in more than one domain.

`api::lookup()` is for server-side validation of values a consumer has already decided to accept
from the user (a form the user may submit): it runs no capability check, returns inactive items
too (`active` false) with their stored code, raw label and parent (`parentcode` with the domain
holding it, `parentdomain`), and matches codes like `resolve_bulk()`. It reads the database on
every call (one query, plus one for the parents), so validation sees the current state. Never
show its result as a listing; browsing goes through `require_read()` and `api::search()`.

Search order: an item whose code equals the query, then labels starting with the query, then
labels containing it; case and accents are ignored; inside each group by sort order, then
label.

### Autocomplete fields

`local_dictionaries/autocomplete_transport` is an AJAX transport for
`core/form-autocomplete`. Put `data-domain="<shortname>"` (and, optionally,
`data-parentcode="<code>"` and `data-parentdomain="<shortname>"`, the domain holding the
parents, when it is not the searched one) on the select element and pass the module as the `ajax` option.
Option values are codes; option labels read "label (code)".

### Web services

| Function | Type | Who may call it |
|---|---|---|
| `local_dictionaries_get_reference_labels` | read | Any logged-in non-guest user for a domain open to logged-in users; `local/dictionaries:view` otherwise |
| `local_dictionaries_search_items` | read | Same as above; with a parent code, also for the parent domain (`parentdomain`, default the searched domain) |
| `local_dictionaries_upsert_reference_items` | write | `local/dictionaries:manage` |

All three are available to AJAX and belong to the predefined service "Reference dictionaries
API" (`local_dictionaries_api`). Batches are capped at 5000 items per call.


Capabilities
------------

- `local/dictionaries:manage` (manager by default; `RISK_CONFIG`): the management page and
  the write web service.
- `local/dictionaries:view` (manager by default): reading restricted domains through the read
  web services.


Privacy
-------

The plugin stores code lists entered by administrators and no personal data. Its privacy
provider is a null provider.


Troubleshooting
---------------

- **An autocomplete field returns an error for ordinary users**: the domain is restricted.
  Open it to logged-in users on the management page, or grant `local/dictionaries:view`.
- **An import reports "errorcodecollision" on MySQL or MariaDB**: the database collation
  treats two codes as equal that the plugin keeps apart (for example `ß` and `ss`). Rename
  one of them.
- **Labels look stale right after a change made directly in the database**: the label cache
  is invalidated by the plugin's own writes only. Purge the caches.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
