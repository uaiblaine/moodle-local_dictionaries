<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dictionaries\local;

use context;
use local_dictionaries\api;
use moodle_url;

/**
 * Builds the rows of the domain list on the management page.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class domain_rows {
    /**
     * Template context rows for the domain list.
     *
     * The name and description are admin-entered and may carry multilang spans, so they go through
     * format_string() with the escaping off: the template's double stash escapes them once.
     *
     * @param array $domains Domain records as returned by {@see manager::list_domains()}, with itemcount.
     * @param context $context Context the strings are filtered in.
     * @param moodle_url $pageurl The management page URL, base of the edit links.
     * @return array The rows for the `local_dictionaries/manage` template.
     */
    public static function build(array $domains, context $context, moodle_url $pageurl): array {
        $rows = [];
        foreach ($domains as $domain) {
            $rows[] = [
                'shortname' => (string)$domain->shortname,
                'name' => format_string((string)$domain->name, true, ['context' => $context, 'escape' => false]),
                'description' => format_string(
                    (string)($domain->description ?? ''),
                    true,
                    ['context' => $context, 'escape' => false]
                ),
                'visibility' => (int)$domain->visibility === api::VISIBILITY_LOGGEDIN
                    ? get_string('visibilityloggedin', 'local_dictionaries')
                    : get_string('visibilityrestricted', 'local_dictionaries'),
                'itemcount' => (int)$domain->itemcount,
                'timemodified' => userdate((int)$domain->timemodified),
                'editurl' => (new moodle_url($pageurl, ['edit' => (string)$domain->shortname]))->out(false),
            ];
        }
        return $rows;
    }
}
