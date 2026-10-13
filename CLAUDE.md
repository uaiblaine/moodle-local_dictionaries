# Claude instructions for `local_dictionaries`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each.

Plugin context: a Moodle **local** plugin ("Reference dictionaries") that holds
named code lists (domain → code → label, with sort order, active flag and an
optional parent item) and lets other plugins resolve and search them. Own
tables `local_dictionaries_domain` and `local_dictionaries_item`; no personal
data (`null_provider`). First consumer: the user profile field
`profilefield_repeatable` (repo `uaiblaine/moodle-profilefield_repeatable`).
Born from `local_profilefield_repeatable` 0.4.0 (renamed 2026-10, no migration:
no production site had it). **One branch per Moodle version**:
`MOODLE_405_STABLE`, `MOODLE_501_STABLE`, `MOODLE_502_STABLE` (= `main`) and
`MOODLE_503_STABLE`, MATURITY_STABLE, releases `v<major>.<minor>-r<N>`. Each
branch's `version.php` sets `requires` and `supported` to its own Moodle version
and numbers itself in that version's namespace (4.5 `20241007XX`, 5.1
`20251006XX`, 5.2 `20260420XX`, 5.3 `20261005XX`), and its
`.github/workflows/ci.yml` has one moodle-an-hochschulen job for that core
branch (full PHP x DB matrix) that installs `profilefield_repeatable` from the
matching branch — **keep `version.php` and `ci.yml` per branch on a merge**.
The r1 releases dropped the pre-release upgrade step; a new step is numbered in
its own branch's namespace, and `install.xml` keeps `VERSION="2024100700"` on
every branch. Mounted into the stacks at `local/dictionaries` per
`~/dev/moodle-dev/plugins.conf` (`auto`): `mdl grunt m502 local/dictionaries`
builds AMD and `mdl phpunit <stack> local/dictionaries/...` runs tests.

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

```sh
mdl ci moodle-local_dictionaries --only phpcs,phpdoc,validate,savepoints,mustache,grunt   # static gates
mdl ci moodle-local_dictionaries --matrix --behat      # the pre-merge gate (main session only)
xmllint --noout --schema ~/dev/moodle-503/public/lib/xmldb/xmldb.xsd db/install.xml
mdl grunt m502 local/dictionaries                     # rebuild amd/build (commit with src)
mdl phpunit m502 local/dictionaries/tests/api_test.php # one test file on a stack
```

Behat locator note: the manage page has two forms with identical "Domain
shortname" labels — always scope with
`in the "Create or update domain" / "Import CSV" "fieldset"`.

## Code layout

```
manage.php                  Admin page: domain create/edit, CSV import, domain list (admin_externalpage)
classes/resolver.php        Public read API (resolve, resolve_bulk, domain_exists, DOMAIN_PATTERN)
classes/api.php             Public search, lookup and domain-list API + VISIBILITY_* constants
classes/local/keys.php      The ONLY derivation of shortnames, code keys and search keys
classes/local/domain_rows.php Rows of the manage page's domain list (names filtered, escaped once by the template)
classes/local/domains.php   Table availability + request-cached domain rows (id, visibility, revision)
classes/local/manager.php   Writes: domains, item upserts (chunked), CSV parser, events
classes/local/access.php    WS read authorisation from the recorded domain visibility
classes/external/           get_reference_labels, search_items (read), upsert_reference_items (write)
classes/event/              domain_created, domain_updated, items_imported
amd/src/autocomplete_transport.js   core/form-autocomplete transport (data-domain, data-parentcode, data-parentdomain)
tests/generator/lib.php     create_domain, create_item (through the manager)
```

## Architecture gotchas

- **The public API is a contract.** `resolver` (method names, signatures,
  `DOMAIN_PATTERN`) and `api` (`search()`, `lookup()`, `list_domains()`, `can_read()`) are probed by consumers with
  `class_exists()`; rename nothing without a consumer change in the same step.
  Consumers must read `resolver::DOMAIN_PATTERN`, not copy it.
- **Read paths degrade, write paths throw.** `resolver` and `api` return empty
  results on missing tables or cache failure (a consumer's page must never
  break); `manager` throws `moodle_exception`. Keep new read paths silent and
  new write paths loud.
- **Every key comes from `local\keys`.** `codekey` = the code with accents
  removed (NFD, combining marks dropped) and lowercased; it carries the unique
  index; `searchkey` = label transliterated (`core_text::specialtoascii`),
  lowercased, cut at 255. A path that derives them differently breaks the unique
  index or the search. Lookups still filter the returned rows by exact key in
  PHP, and an insert collision surfaces as `errorcodecollision`, but only for
  foldings the key does not do: MariaDB's `utf8mb4_unicode_ci` also folds
  `ß` = `ss`.
- **Cache invalidation is a revision, not a purge.** Label keys are
  `l<domainid>_<revision>_<sha1(codekey)>` (simple keys, no `:`). Any write
  that changes items must bump `revision` inside its transaction and call
  `domains::forget()` after it; a write that skips this serves stale labels for
  an hour.
- **Authorisation reads the recorded visibility** (`local\access`): visibility 1
  = any logged-in non-guest user, otherwise `local/dictionaries:view`; an
  unknown domain is treated as restricted. `validate_context()` runs
  `require_login()` but lets the guest user through: keep
  `access::require_not_guest()` on every function.
- **Parents are resolved after the batch's inserts** so an item may name a
  parent from the same batch; `check_parents()` runs before any write (and over
  the whole list in the chunked import), so an unknown parent leaves the domain
  untouched. The chunked import also orders parents before their children (same-domain
  hierarchy) and refuses a parent cycle up front, because a chunk resolves parents only from the
  database and its own batch.
- **Events**: one `items_imported` per `upsert_items()` call; the chunked import
  fires one per committed chunk with that chunk's counts (pre-write drops added to
  the first), or one with the totals when nothing was written; none for an empty batch.

## Testing notes

- PHPUnit metadata stays in `@covers` docblocks (4.5 is supported).
- Create data through the generator; it goes through the manager, so events,
  keys and revisions behave as in production.
- Rejected CSV rows carry the physical line where the record starts (a quoted
  multi-line field advances the count).

## When in doubt

Follow the patterns in existing files.
