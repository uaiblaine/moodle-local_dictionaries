# Comment audit: code findings (2026-10)

Comment audit of `local_dictionaries` (fleet kit `moodle-dev/ci/comment-audit`, 2 batches, 7 agents). The audit edited comments only; the findings below are about code. Each bug or risk finding went to an independent refuter; the verdict is quoted. Line numbers are at the audit's base (main ea36d5f).

## 1. [risk] Collision merge can make the surviving item its own parent

`db/upgradelib.php:116` (batch b00)

**Evidence.** $DB->set_field('local_dictionaries_item', 'parentid', $keeper->id, ['parentid' => $item->id]); — if the keeper's parent is a deleted member of its own group (e.g. 'SAO' child of 'São' in one domain), the keeper's parentid becomes its own id. Afterwards any batch whose parent chain reaches that item is refused with errorparentcycle by manager::check_parent_cycles().

**Suggestion.** Exclude the keeper from the re-parent update (add id <> keeper via set_field_select) and give it the deleted item's parent instead, or null. Add the case to upgradelib_test.

**Refuter verdict:** REFUTED (none). The helper does mishandle a keeper whose parent is a deleted group member. In isolation `set_field('parentid', $keeper->id, ['parentid' => $item->id])` would set the keeper's own parentid to its own id. But no real input can reach that state. The only caller is the `$oldversion < 2026042001` step in db/upgrade.php, which calls `local_dictionaries_merge_codekey_collisions()` right after `local_dictionaries_backfill_item_keys()`. The legacy schema being upgraded (0.4.1, version 2026042000, commit eef8ef0) has no `parentid` column on `local_dictionaries_item`. The step adds the column itself, via the `$otherfields` loop with `add_field`, so every pre-existing row has `parentid` NULL when the merge runs. No item can have a parent, so there is nothing to re-parent and no self-parent can form. The MDL Shield commit message says the branch changed `install.xml` but kept version 2026042000. A site installed from that branch already has the unique `codekey` index, so it has no collisions to merge. The helper is not called from any runtime or write path. The plugin notes also say no production site ever ran the pre-rename plugin. The only place a populated `parentid` meets this helper is the unit test, which seeds `parentid` by hand. The proposed hardening (exclude the keeper, inherit the deleted item's parent) would be harmless defensive code, but it fixes no reachable bug.

## 2. [risk] Random domain shortnames can collide and flake the test

`tests/upgradelib_test.php:72` (batch b01)

**Evidence.** 'shortname' => 'dom' . random_int(1000, 9999), — test_collision_tie_and_other_domains() calls $this->domain() twice, and local_dictionaries_domain has a unique index on shortname (shortname_uix).

**Suggestion.** Use a per-test counter property (e.g. 'dom' . ++$this->domaincount) instead of random_int(), so the two domains can never share a shortname. Each run currently has about a 1 in 9000 chance of failing with a dml_write_exception.

**Refuter verdict:** holds (risk). The code confirms the finding. domain() in tests/upgradelib_test.php builds each shortname as 'dom' . random_int(1000, 9999), which gives 9000 possible values. test_collision_tie_and_other_domains() (line 172) calls $this->domain() twice in one test, at lines 174 and 177. db/install.xml line 22 declares `<INDEX NAME="shortname_uix" UNIQUE="true" FIELDS="shortname"/>` on local_dictionaries_domain, and the domain table is never touched by the index drop (only local_dictionaries_item is). The test inserts the rows straight through $DB->insert_record(), so nothing rewrites a duplicate shortname. When the two draws match, which has probability 1/9000 per run, the second insert throws a dml_write_exception and the test fails. resetAfterTest() clears the table between tests, so there is no carry-over from earlier tests. Only that one test makes two calls, so the collision exposure is confined to it. The failure is a real but very rare flake in test code, not a production defect. A per-test counter would remove it, as the finding suggests.

## 3. [question] change_field_default loop looks like a no-op

`db/upgrade.php:78` (batch b00)

**Evidence.** foreach ($keyfields as $field) { $field->setDefault(null); $dbman->change_field_default($item, $field); } — the fields were built with a null default already. For a NOT NULL char column with a null default, sql_generator::getDefaultValue() applies default_for_char ('') on add and on change alike (postgres/mysql getDropDefaultSQL -> getAlterFieldSQL), so the column ends as add_field left it.

**Suggestion.** Confirm on both DB families (check_database_schema in upgrade_test passes either way). If it is a no-op, drop the loop in a later version; otherwise comment what it changes.

## 4. [improvement] WS parameter description understates code matching

`classes/external/upsert_reference_items.php:47` (batch b00)

**Evidence.** 'code' => new external_value(PARAM_RAW_TRIMMED, 'Code; matched case-insensitively') — keys::code_key() also folds accents, so 'São' and 'SAO' are one code.

**Suggestion.** Change the description to 'Code; matched ignoring case and accents' (a string literal, so not changed in this audit).

## 5. [improvement] Admin-entered domain name and description are shown without format_string()

`manage.php:149` (batch b00)

**Evidence.** 'name' => (string)$domain->name, 'description' => (string)($domain->description ?? ''), — escaped by Mustache, but multilang spans and filters are not applied, unlike the WS labels, which go through util::format_string().

**Suggestion.** Pass both through format_string(..., true, ['context' => $context, 'escape' => false]) before the template, which keeps double-stash escaping.

## Facts to relocate or correct outside the code comments

- `CLAUDE.md`: States codekey = core_text::strtolower(code). keys::code_key() now also strips accents (NFD + \p{Mn} removal) before lowercasing.
- `CLAUDE.md`: States lookups filter by exact key because MariaDB utf8mb4_unicode_ci folds accents, and that an accent-only collision surfaces as errorcodecollision (MariaDB only). The code key folds accents on every DB now; the exact-key filter and errorcodecollision remain only for further collation folds such as 'ß' = 'ss'.
- `CLAUDE.md`: States one items_imported per chunked import (totals). upsert_items_chunked() fires one per committed chunk with that chunk's counts (pre-write drops added to the first); it fires one event with the totals only when nothing was written.
- `CLAUDE.md`: Code layout lists autocomplete_transport.js as reading data-domain and data-parentcode. It also reads data-parentdomain.
- `db/install.xml`: The codekey COMMENT 'Lowercased code; the unique and lookup key' is stale: the key also strips accents (outside this batch, not edited).
- `CLAUDE.md`: 'Every key comes from local\keys' says codekey = core_text::strtolower(code). keys::code_key() now also removes accents (NFD decomposition, combining marks dropped, then lowercase): tests/local/manager_test.php asserts 'AÇÃO' -> 'acao'.
- `CLAUDE.md`: The same bullet says the exact-key PHP filter exists because MariaDB folds accents, and that an accent-only collision raises errorcodecollision (MariaDB only). Both are stale: accents are folded in the key, so errorcodecollision and the exact-key filter now cover only collation foldings the key does not do, e.g. 'ß' = 'ss' under utf8mb4_unicode_ci (tests/resolver_test.php, the lang string, CHANGELOG).
