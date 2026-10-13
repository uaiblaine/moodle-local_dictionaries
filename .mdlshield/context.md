# Review context for local_dictionaries

`local_dictionaries` holds reference dictionaries (domain, then code, then label)
that the companion user profile field plugin `profilefield_repeatable` uses to turn stored
codes into display labels. It offers an administrator page for domains and CSV import, a
static resolver class with a cache, and two web service functions. It has two tables of its
own and stores **no personal data**: the rows are dictionaries entered by administrators.
It supports Moodle 4.5 through 5.2 on one branch and is declared alpha.

## Who is trusted

- Site administrators are fully trusted.
- `local/dictionaries:manage` (system context, `RISK_CONFIG`, default
  to `manager`) writes domains and items: the admin page and `upsert_reference_items`.
- `local/dictionaries:view` (system context, read, default to `manager`,
  cloned from `managereference`) reads labels: `get_reference_labels`.
- Everyone who can reach the profile field is untrusted. Codes and labels are
  markup-bearing input: labels are stored raw as text, with only control characters removed,
  and the plugin never prints them to a page itself, so each consumer must escape them.

## Surfaces

- 2 web service functions, both `ajax`, session-based, in `db/services.php`:
  `local_dictionaries_upsert_reference_items` (write, requires `managereference`)
  and `local_dictionaries_get_reference_labels` (read, requires `viewreference`).
  Each validates parameters, calls `validate_context()` on the system context, then
  `require_capability()`, and rejects batches above 5000 entries. The domain is
  `PARAM_ALPHANUMEXT` and normalised to `^[a-z0-9_]+$`.
- `services.php` also defines a predefined service "Profilefield Repeatable Reference API"
  (`restrictedusers => 0`, enabled) holding those two functions; the capabilities above
  still decide each call.
- Page script: `manage.php`, an `admin_externalpage` registered under `$hassiteconfig`
  that also calls `require_capability(managereference)`. It holds two moodleforms (create a
  domain, import CSV by upload or pasted text) and renders domains through a Mustache template
  with double stashes. Error text is escaped with `s()` because notifications render raw HTML.
- `resolver::resolve()`, `resolve_bulk()` and `domain_exists()` are the public API used by
  the companion plugin. They run no capability check: callers decide who may see a label.
  Results are cached in one application cache (`labels`, one hour); writes purge it.
- Tables: `local_dictionaries_domain` and `local_dictionaries_item`
  (unique indexes on shortname and on domain plus code), dropped on uninstall. All
  queries use placeholders and `get_in_or_equal()`. There is no file serving, no outbound
  HTTP and nothing compiled or evaluated from input.
- Privacy provider: `null_provider`.

## Facts that look like findings but are by design

- **`resolver` does not check capabilities.** It is a lookup used while rendering profile
  fields for users who already may see the field; the capability gates sit on the web
  services and the admin page only. The labels of one domain are not per-user data.
- **The resolver degrades, the manager throws.** If the tables are missing, the resolver
  returns no labels so a partial install cannot take profile rendering down, while the
  write paths raise an error.
- **The public surface is frozen** because the companion plugin probes the class at runtime
  and reads the domain table directly: the class and method names, and the domain shortname
  pattern, are duplicated there on purpose.
- **CSV imports are chunked, web service batches are capped.** Admin imports run in chunks
  of 5000, each in its own transaction, with no cap on the number of rows; the web services
  refuse more than 5000 items per call.

## De-emphasise

- `lang/**`, `tests/**` and `CHANGELOG.md` carry no production behaviour.
- The wording of notifications and form labels.
