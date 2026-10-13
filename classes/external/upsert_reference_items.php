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

namespace local_dictionaries\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_dictionaries\local\access;
use local_dictionaries\local\manager;

/**
 * Insert or update the items of one domain.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upsert_reference_items extends external_api {
    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'domain' => new external_value(PARAM_ALPHANUMEXT, 'Domain shortname'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'code' => new external_value(PARAM_RAW_TRIMMED, 'Code; matched ignoring case and accents'),
                    'label' => new external_value(PARAM_RAW_TRIMMED, 'Label, stored raw'),
                    'parentcode' => new external_value(
                        PARAM_RAW_TRIMMED,
                        'Code of the parent item in parentdomain; empty removes the parent; omitted keeps it',
                        VALUE_OPTIONAL
                    ),
                    'sortorder' => new external_value(
                        PARAM_INT,
                        'Sort order; omitted keeps it (0 for new items)',
                        VALUE_OPTIONAL
                    ),
                    'active' => new external_value(
                        PARAM_BOOL,
                        'Offered by search; omitted keeps it (true for new items)',
                        VALUE_OPTIONAL
                    ),
                ])
            ),
            'parentdomain' => new external_value(
                PARAM_ALPHANUMEXT,
                'Domain holding the parent items; empty means the same domain',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Upsert reference items.
     *
     * @param string $domain
     * @param array $items
     * @param string $parentdomain
     * @return array ['inserted' => int, 'updated' => int, 'ignored' => int]
     * @throws invalid_parameter_exception When the parameters are invalid or the batch is too large.
     * @throws \moodle_exception When the user is not logged in or is the guest user, or on an
     *     unknown domain or parent, a self-parent, a parent cycle, an over-long code or a code collision.
     * @throws \required_capability_exception When the user lacks local/dictionaries:manage.
     */
    public static function execute(string $domain, array $items, string $parentdomain = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'domain' => $domain,
            'items' => $items,
            'parentdomain' => $parentdomain,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        access::require_not_guest();
        require_capability('local/dictionaries:manage', $context);

        if (count($params['items']) > manager::MAX_BATCH_SIZE) {
            throw new invalid_parameter_exception(
                get_string('maxbatchsizeexceeded', 'local_dictionaries', manager::MAX_BATCH_SIZE)
            );
        }

        $parent = $params['parentdomain'] === '' ? null : $params['parentdomain'];
        return (new manager())->upsert_items($params['domain'], $params['items'], $parent);
    }

    /**
     * Describe return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'inserted' => new external_value(PARAM_INT, 'Inserted rows count'),
            'updated' => new external_value(PARAM_INT, 'Updated rows count'),
            'ignored' => new external_value(PARAM_INT, 'Unchanged, empty-code or superseded rows count'),
        ]);
    }
}
