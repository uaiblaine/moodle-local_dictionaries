# Review context for local_dictionaries

`local_dictionaries` holds reference dictionaries (domain, then code, then label) that other
plugins use to turn stored codes into display labels and to offer codes in autocomplete
fields. Its first consumer is the user profile field plugin `profilefield_repeatable`. It has
an administrator page for domains and CSV import, a static resolver and a search API with a
cache, three web service functions and one AMD module (an autocomplete transport). It has two
tables of its own and stores **no personal data**: the rows are dictionaries entered by
administrators. It supports Moodle 4.5 through 5.3 on one branch and is declared alpha.

## Who is trusted

- Site administrators are fully trusted.
- `local/dictionaries:manage` (system context, `RISK_CONFIG`, default to `manager`) writes
  domains and items: the admin page and `upsert_reference_items`.
- `local/dictionaries:view` (system context, read, default to `manager`) reads **restricted**
  domains through the two read web services.
- Each domain records a `visibility`: 0 restricted (needs `view`), 1 readable by any
  logged-in user who is not the guest user. The read web services decide on that recorded
  value, never on what the caller claims to be editing; an unknown domain is treated as
  restricted, so it cannot be told apart from a restricted one.
- Everyone else is untrusted. Codes and labels are markup-bearing input: labels are stored
  raw as text, with only control characters removed. The plugin escapes them on its own
  page (Mustache double stashes) and in its AMD module (option labels are HTML-escaped before
  core/form-autocomplete inserts them as markup); every other consumer must escape them.

## Surfaces

- 3 web service functions, all `ajax`, in `db/services.php`:
  `local_dictionaries_upsert_reference_items` (write, `manage`, batch cap 5000),
  `local_dictionaries_get_reference_labels` (read, `readonlysession`, batch cap 5000) and
  `local_dictionaries_search_items` (read, `readonlysession`, limit capped at 100). Each
  validates parameters, calls `validate_context()` on the system context (which runs
  `require_login()`), refuses the guest user, then checks the capability or the recorded
  domain visibility. The domain is `PARAM_ALPHANUMEXT`, normalised to `^[a-z0-9_]+$`.
- `services.php` also defines a predefined service "Reference dictionaries API"
  (`restrictedusers => 0`, enabled) holding the three functions; the checks above still
  decide each call.
- Page script: `manage.php`, an `admin_externalpage` registered under `$hassiteconfig` that
  also calls `require_capability(manage)`. Two moodleforms (create or edit a domain; import
  CSV by upload or pasted text) and a Mustache domain list. Error text is escaped with `s()`
  because notifications render raw HTML; the import report is a Mustache template whose
  rejected rows show only line numbers, never file content.
- PHP API: `resolver::resolve()`, `resolve_bulk()`, `domain_exists()` and `api::search()`.
  They run no capability check: callers decide who may see a label.
- Caches: `labels` (application, one hour, key = domain id + domain revision + sha1 of the
  code key; a write bumps the revision) and `domains` (request).
- Events: `domain_created`, `domain_updated`, `items_imported` (one per batch).
- Tables: `local_dictionaries_domain` and `local_dictionaries_item` (unique on shortname and
  on domain plus lowercased code key). All queries use placeholders, `get_in_or_equal()` and
  `sql_like()` with `sql_like_escape()`. No file serving, no outbound HTTP, nothing compiled
  or evaluated from input.
- Privacy provider: `null_provider`.

## Facts that look like findings but are by design

- **`resolver` and `api` do not check capabilities.** They are lookups used while rendering
  or validating a consumer's field for users who already may see it; the gates sit on the web
  services and the admin page.
- **The read paths degrade, the manager throws.** Missing tables give "no labels" and an
  empty search; the write paths raise an error.
- **Codes are case-insensitive**: `codekey` is the lowercased code and carries the unique
  index; `code` keeps the spelling of the last write.
- **CSV imports are chunked, web service batches are capped.** Admin imports run in chunks
  of 5000, each in its own transaction, after every parent code has been checked; the web
  services refuse more than 5000 items per call.

## De-emphasise

- `lang/**`, `tests/**`, `amd/build/**` and `CHANGELOG.md` carry no production behaviour.
- The wording of notifications and form labels.
