# Claude instructions for `local_dictionaries`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. It captures the **Moodle development standards** this plugin
follows so future edits stay in the same style and pass CI on the first try.

Plugin context: a Moodle **local** plugin holding reference dictionaries
(domain → code → label) used by the companion user-profile-field plugin
**profilefield_repeatable** to resolve stored codes into display labels.
Provides an admin management page (domains + CSV import), a static resolver
API with MUC caching, and two web-service functions. Supports Moodle **4.5
through 5.2** (`$plugin->requires = 2024100100`,
`$plugin->supported = [405, 502]`), **MATURITY_ALPHA**. CI is the
**moodle-an-hochschulen/moodle-workflows** reusable workflow, called once per
supported Moodle branch in `.github/workflows/ci.yml`; releases to the Moodle
plugins directory are automated by `.github/workflows/moodle-release.yml`
(triggered on `v*` tags).

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

Run from the Moodle root (`/Volumes/N1TB/dev/github/moodle`; this plugin
lives at `public/local/dictionaries`):

Repo layout (Moodle 5.x split): the webroot is `public/` (plugin code +
`public/config.php`), but **core CLI scripts live in `admin/cli/` at the
repo root, outside `public/`** — e.g. `php admin/cli/purge_caches.php`.
The plugin itself also installs on pre-split Moodle 4.5 layouts; never
hardcode `public/` inside plugin code (`manage.php` resolves config via
`__DIR__ . '/../../config.php'`, which works in both layouts).

| Command | What it does |
|---------|--------------|
| `xmllint --noout --schema public/lib/xmldb/xmldb.xsd public/local/dictionaries/db/install.xml` | Validate the XMLDB schema. (Core libs live under `public/` in this 5.x split layout; the bare `lib/xmldb/...` path does not exist.) |

This plugin has **no AMD/JS**. CI (moodle-an-hochschulen/moodle-workflows,
full `moodle-plugin-ci install` per job) gates on: a static leg (`phplint`,
`phpmd`, `phpcs --max-warnings 0`, `phpdoc --max-warnings 0`, a
**development-leftover checker that fails on stray to-do markers, test-me
annotations, or merge-conflict markers in ANY file — docs included; never
write any of those tokens literally, not even
here**, `validate`, `savepoints`, `mustache`, `grunt`) plus
runtime legs running **PHPUnit (`--fail-on-warning`) and Behat
(`--profile chrome`) on every PHP × DB combination**; Behat faildumps upload
as artifacts on failure. `ci.yml` calls the workflow once per supported
Moodle branch (5.02 full matrix; 5.01/5.00/4.05 `one-db-only`) — **update those
calls when `$plugin->supported` changes**. It runs on every push/PR (no
protected-ref gate; concurrent runs cancel superseded ones). There is no
committed local wrapper — run checks through your own `moodle-plugin-ci`
checkout before pushing. To read a failed job's raw log (the MAH
reusable-workflow jobs often return empty from `gh run view --log-failed`):
`gh api repos/uaiblaine/moodle-local_dictionaries/actions/jobs/<jobid>/logs`
(get `<jobid>` from `gh run view <runid> --json jobs`).

Behat locator note: the manage page has two forms with identical "Domain
shortname" labels — always scope with
`in the "Create or update domain" / "Import CSV" "fieldset"`.

Local raw PHPUnit (if wired up) reads `phpunit.xml` at the **repo root**
(`$CFG->root`, above `public/`), generated by
`php public/admin/tool/phpunit/cli/init.php` — not `public/phpunit.xml`.

## Git / repos

This plugin is its **own git repo**
(`uaiblaine/moodle-local_dictionaries`, single branch `main`)
nested inside the Moodle checkout (branch `MOODLE_501_STABLE`): run git from
the plugin dir or use `git -C public/local/dictionaries ...` —
git from the Moodle root won't see plugin changes. Note `cd` inside a
compound Bash command doesn't persist (cwd resets between calls); prefer
absolute paths or `git -C`.

Versioning: bump `$plugin->version` + `$plugin->release` in `version.php`
and add the matching `CHANGELOG.md` entry in the same commit.

## Companion-plugin contract (profilefield_repeatable)

The field plugin (`public/user/profile/field/repeatable`, repo
`uaiblaine/moodle-profilefield_repeatable`) consumes this plugin as a
**soft dependency** — it probes `class_exists(...resolver)` and
`table_exists(...)` at runtime. Consequences on this side:

- **The public surface is frozen:** class `\local_dictionaries\resolver`
  with static `resolve(string $domain, string $code): ?string`,
  `resolve_bulk(string $domain, array $codes): array`, and
  `domain_exists(string $domain): bool`. The field plugin also reads the
  table `local_dictionaries_domain` **directly** (its
  `define.class.php::get_missing_reference_domains()`). Renaming the class,
  the methods, or that table breaks the field plugin.
- **Domain shortname pattern** `/^[a-z0-9_]+$/` is duplicated here
  (`resolver`, `manager`) and in the field plugin (`define.class.php`,
  `display_renderer.php`). Keep all copies in lockstep.
- **CI cross-installs the other repo's `main`** via the `plugin-dependencies`
  input in `.github/workflows/ci.yml`. A breaking change pushed to either
  `main` turns the other repo's CI red — coordinate contract changes across
  both repos.

## Code layout

```
manage.php                   Admin page: domain CRUD + CSV import (admin_externalpage)
settings.php                 Registers the external admin page (no settings)
classes/
  resolver.php               Static code→label resolution + MUC caching (public API)
  local/manager.php          Domain/item CRUD, CSV parsing, batch upsert
  external/                  get_reference_labels (read), upsert_reference_items (write)
  form/                      create_domain_form, import_csv_form (moodleform)
  privacy/provider.php       null_provider (reference data only, no personal data)
db/
  install.xml                _domain + _item tables
  access.php                 local/dictionaries:manage
  caches.php                 'labels' application cache
  services.php               WS functions + custom service
  upgrade.php / uninstall.php
templates/manage.mustache    Domain listing table
lang/en/ + lang/pt_br/       Both maintained in lockstep (same keys)
tests/                       manager_test, resolver_test, external/*_test,
                             form/import_csv_form_test, behat/
```

## Architecture gotchas

### Table availability — two failure philosophies

Tables are `local_dictionaries_domain` / `_item`, addressed by
private class constants. An early release shipped `local_pfr_*` names; that
is handled **only at upgrade time** (`db/upgrade.php` renames them; the
runtime fallback was removed in 0.3.0 — don't reintroduce it) and
`db/uninstall.php` drops stray legacy tables. Both `resolver` and `manager`
gate on table existence: `resolver::tables_available()` memoises in a static
(only caches `true`) and **degrades to "no labels"** so a partial install
never takes profile rendering down; `manager::ensure_tables_available()`
**throws** a `coding_exception` (admin/write paths want loud failures).
Keep new read paths on the silent side and write paths on the loud side.

### MUC 'labels' cache

- Application cache, `simplekeys => false`, ttl 3600, static acceleration.
  Keys are `"{domain}:{code}"` — the `:` is only safe **because**
  `simplekeys` is false (keys get hashed). Don't flip `simplekeys` without
  changing the key format.
- `resolver::resolve_bulk()` reads `get_many()`, backfills misses from the
  DB, and caches per pair. `manager::upsert_items()` **purges the whole
  cache** after any insert/update — labels may be stale up to the purge, by
  design.
- All cache I/O goes through `cache_get_many_safe()` / `cache_set_safe()` /
  `purge_label_cache()`, which swallow cache-layer failures with a
  `debugging()` notice — the DB is the source of truth and a broken cache
  store must never take label resolution down. Keep new cache calls behind
  the same guards (and remember `debugging()` output fails PHPUnit's
  `--fail-on-warning` if triggered in tests).

### Batch limits and transactions

`manager::MAX_BATCH_SIZE` (5000) is enforced in **both** web services before
touching the manager. The admin CSV import (`manage.php`) instead goes
through `manager::upsert_items_chunked()` — bounded per-chunk transactions,
no total cap (admins may legitimately import huge dictionaries).
`upsert_items()` pre-fetches existing rows with one `get_in_or_equal` query
and wraps the write loop in a delegated transaction — keep bulk paths to
O(1) queries for reads + per-row writes inside the transaction, and
normalise items via `normalise_item()` (control-character stripping,
255-char code truncation) before comparing/writing.

### Exceptions and notifications

User-facing business errors (unknown domain, missing shortname) throw
`moodle_exception` with the specific lang string — **never**
`invalid_parameter_exception`, whose constructor buries the text in
`$debuginfo` and shows users a generic "Invalid parameter value detected".
Reserve `invalid_parameter_exception` for true parameter-shape violations
(e.g. the WS batch-size cap). `manage.php` renders caught exception messages
through `s()` because core notifications output their message as raw HTML.

## Web services

- Function classes under `classes/external/` extend
  `\core_external\external_api`; parameters via `execute_parameters()`,
  shape via `execute_returns()`.
- Both functions `validate_parameters()` first, then
  `validate_context(context_system::instance())` + `require_capability()`.
  **Two capabilities, by direction:** reads require
  `local/dictionaries:view` (read captype; cloned from
  managereference on upgrade via `clonepermissionsfrom`), writes require
  `local/dictionaries:manage` (write, `RISK_CONFIG`).
  Both are system-context, `manager` archetype. New read functions take
  viewreference; new write functions take managereference + the batch cap.
- Registered in `db/services.php` (with the custom service
  `local_dictionaries_api`); **registering or changing a function
  there requires a `version.php` bump** — services install only on upgrade.

## Forms (moodleform)

Form class files sit under `classes/form/` and start with:

```php
namespace local_dictionaries\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');   // moodleform is not autoloaded.
```

Custom validation in `validation($data, $files)` returns
`field => errormsg` arrays. `manage.php` is the page-controller pattern:
`admin_externalpage_setup()` + capability check, `get_data()` handling with
`redirect()` on success, then render through
`$OUTPUT->render_from_template()` — zero `html_writer` calls in plugin code.

## Coding style

### File header

Every PHP file starts with the GPL block, then:

```php
/**
 * <One-line file description>.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```

`defined('MOODLE_INTERNAL') || die();` in procedural files and files with
side-effects. Pure namespaced class files with no side-effects (single
class, no `require_once`/globals — e.g. `resolver.php`, `manager.php`) must
**omit** the guard; the sniff
`moodle.Files.MoodleInternal.MoodleInternalNotNeeded` fires otherwise.

### PHPDoc

Moodle's `phpdoc --max-warnings 0` enforces:

- Every class, method, property, and constant has a `/** */` docblock
- `@param`, `@return`, `@throws` declared explicitly (even when types are
  fully implied by signatures)
- Type hints in PHPDoc use `int|null`, `?int`, `array<int, string>` —
  match the actual PHP type
- File-level docblock has `@package`, `@copyright`, `@license`
- No `@author` tags (Moodle convention)

### Naming

- Classes: `lower_snake_case` (Moodle convention, not PSR-4 PascalCase)
- Methods: `lower_snake_case`
- Constants: `UPPER_SNAKE_CASE`
- Properties: `lowercase` (no camel/snake mixing — single lowercase word
  where possible)
- Frankenstyle prefix on globals: `local_dictionaries_*`

### CodeSniffer rules that bite

Rules from `moodle.*` / `PSR2.*` / `PSR12.*` standards that routinely break
CI. Pre-empt them at write time.

**1. Variables are lower-case only.** No camelCase, no snake_case — a single
lower-case word (concatenated if needed). Sniff:
`moodle.NamingConventions.ValidVariableName.VariableNameLowerCase`.

```php
// ✘ $domainA, $itemTable, $domain_a, $item_table
// ✓ $domaina, $itemtable
```

**2. PSR-2 multi-line function call layout.** When a call spans lines:
opening `(` is the last content on its line; one argument per line; closing
`)` on its own line at the call's indent level. Sniffs:
`PSR2.Methods.FunctionCallSignature.{ContentAfterOpenBracket,MultipleArguments,Indent,CloseBracketLine}`.

```php
// ✘ Two args on the wrap line:
$DB->get_records_select($itemtable, "domainid = :domainid AND code $insql",
    $params, '', 'id, code, label');

// ✓ One arg per line, ) on its own line:
$DB->get_records_select(
    $itemtable,
    "domainid = :domainid AND code $insql",
    $params,
    '',
    'id, code, label'
);
```

**3. Inline `//` comments — one space, capital first letter, terminal
punctuation.** The sniff fires on the **first line** of any `//` comment
block that starts with a lowercase letter, a version tag (`v1.0`), or lacks
terminal punctuation. Need multi-line or version-tagged commentary? Use a
block comment (`/* ... */`) — the sniff does not apply inside `/* */`.
Sniffs: `moodle.Commenting.InlineComment.{NotCapital,InvalidEndChar,SpacingBefore}`.

**4. Property docblocks need `@var`.** A `/** ... */` on a class property
*must* declare the type even when PHP's typed-property syntax already does.
Sniff: `moodle.Commenting.VariableComment.MissingVar`.

```php
/** @var bool|null Static cache for table availability (only caches true). */
private static ?bool $tablesavailable = null;
```

**5. Operator spacing: exactly one space.** Aligning columns with extra
spaces before `!==`, `===`, `?`, `:`, `=>` breaks
`Squiz.WhiteSpace.OperatorSpacing.SpacingBefore`. No column alignment —
one space everywhere, including inside array literals.

**6. PSR-12 multi-line `if` layout.** When an `if` condition spans lines, the
first expression goes on the line **after** `(` and the closing `)` on the
line **after** the last expression. Sniffs:
`PSR12.ControlStructures.ControlStructureSpacing.{FirstExpressionLine,CloseParenthesisLine}`.

```php
// ✓
if (
    $code !== ''
    && $label !== ''
) {
```

**7. Line-length limits.** Hard max **180** characters (error); soft max
**132** (warning) — and the warning count feeds `phpdoc --max-warnings 0`,
so both matter. Wrap long PHPDoc type annotations at a comma.

**8. Squiz "commented-out code" false positive.** A trailing `//` comment
containing PHP-looking text (e.g. `// enabled=1`) triggers
`Squiz.PHP.CommentedOutCode.Found`. Remove trivial trailing comments, or
rephrase to avoid `=` inside the comment text.

**9. `\R` line splitting.** User-entered multi-line content (CSV import) is
split with `preg_split('/\R/u', ...)` — never `explode("\n")`; Windows CRLF
input must keep working.

## Database (XMLDB)

- Every `<FIELD>` element needs `SEQUENCE="true"` or `SEQUENCE="false"`
  explicitly. Missing → XSD validation fails.
- Validate with the xmllint command in the Commands table.
- Upgrade steps end with
  `upgrade_plugin_savepoint(true, <version>, 'local', 'dictionaries');`
  — match `<version>` to the `version.php` bump.
- Uniqueness is schema-enforced: `shortname_uix` on `_domain`,
  `domainid_code_uix` on `_item`. Upserts rely on these — keep reads
  (`get_record` by shortname / `get_records_select` by domainid+code)
  aligned with them.

### Cross-DB SQL

CI runs against both PostgreSQL and MariaDB. This plugin is deliberately
**vendor-neutral** (Moodle DB abstraction only — no raw vendor SQL; that
lives in the companion field plugin). Keep it that way. Also:

- `\moodle_database::get_records()` returns string values for numeric
  columns under both drivers. Cast to `(int)` / `(float)` when typing
  matters (`(int)$domain->id`, `(int)$existing->id`).
- `ORDER BY col ASC NULLS FIRST` is PG-only; use `COALESCE(col, 0) ASC`.

## Lang strings

`lang/en/local_dictionaries.php` strings are **alphabetically
sorted** (CI validate enforces ordering) and
`lang/pt_br/local_dictionaries.php` carries the **same keys** —
add/remove strings in both files in the same commit. Required strings: one
per capability (`dictionaries:manage` — the `local/`
prefix is dropped in the lang key), one per cache definition
(`cachedef_labels`), `pluginname`, and `privacy:metadata`.

Don't build string IDs dynamically (`get_string('error' . $kind, ...)`);
use literal switches so the string checker can verify them.

## Mustache templates

Every `templates/*.mustache` file must include an `Example context (json):`
block in its docblock — Mustache lint renders the template against that
context and validates the HTML. When a template renders a table loop, the
example context must supply non-empty rows or the validator rejects the
empty render.

## PHPUnit tests

- Test files in `tests/<area>/<thing>_test.php`, namespace matching the
  directory (`local_dictionaries` /
  `local_dictionaries\external`), `final class <thing>_test
  extends \advanced_testcase`, `@covers` annotation on the class docblock.
- Call `$this->resetAfterTest()` in every test that touches the DB.
- External-function tests call the WS class statically after
  `$this->setAdminUser()` (capability is manager-archetype) and assert the
  declared return structure via `external_api::clean_returnvalue()`.
- DB rows and generator results carry **string** ids — cast to `(int)`
  when passing to typed signatures.

## MDL Shield reviews

Pull request reviews by MDL Shield are **manual only**. Comment `!mdlshield review` (what is
new since the last review) or `!mdlshield review full` (the whole pull request) on a pull
request; the commands work for the repository owner and the `trusted_users` of
`.mdlshield/config.yml`, and a forced command ignores the filters and a draft's silence.
Nothing runs on its own because `include.branches` names a branch no pull request targets
(a non-empty include list is a requirement; an empty one was measured NOT to stop automatic
reviews).

- **A review is a counted resource, so ask for one on purpose.** The monthly quota is shared by every
  repository, and only a completed review uses one. The owner posts the command; a session never
  does without being told to for that pull request. Keep a pull request at or under **3,000 effective
  changed lines** (additions plus deletions after `exclude.paths`; the limit is MDL Shield's own
  and 12,000 is the hard one, forced reviews included): `mdl mdlshield size <repo-dir>` estimates it.
  One `review full` per pull request, after the local matrix is green. The rule and its reasons are
  in the fleet CLAUDE.md (section 4, "MDL Shield review budget").

- `.mdlshield/config.yml` and `.mdlshield/context.md` are read **from the default branch
  only** (`main`), so a pull request cannot change the rules that judge it, and the pull
  request that first adds them is judged by the website settings instead. Both are
  `export-ignore`d and never reach the release zip. The file overrides the website for each
  key it names; an invalid file stops reviews for the repository until it is fixed, and an
  unknown key only raises a warning on the summary comment.
- **One Moodle version per repository.** There is no per-branch mapping, so the file pins
  `moodle.versions: ["5.2"]`. The plugin also runs on older cores, so read a finding about a core API with that in mind.
- `fail_on.severity` is `high`: the check fails on an open finding at or above it. Code
  quality findings never block, only security findings do.
- The context file is project knowledge that **influences** the reviewer and is not a
  filter. Keep it in step with the code it states: capabilities, the web service list and
  the facts it calls deliberate. The website's own review context is replaced entirely by
  this file, not merged.
- On the website the repository still needs *Enable pull request reviews* and a granted
  preview access; the file cannot do either.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it doesn't match any existing
shape, that's a signal to re-examine the approach.
