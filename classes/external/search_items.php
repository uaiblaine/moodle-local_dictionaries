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
use core_external\util;
use local_dictionaries\api;
use local_dictionaries\local\access;

/**
 * Search the active items of one domain, for autocomplete widgets.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_items extends external_api {
    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'domain' => new external_value(PARAM_ALPHANUMEXT, 'Domain shortname'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Text typed by the user; matches labels and codes'),
            'limit' => new external_value(PARAM_INT, 'Maximum number of items', VALUE_DEFAULT, api::DEFAULT_SEARCH_LIMIT),
            'parentcode' => new external_value(
                PARAM_RAW_TRIMMED,
                'Only items whose parent item has this code; empty for no filter',
                VALUE_DEFAULT,
                ''
            ),
            'parentdomain' => new external_value(
                PARAM_ALPHANUMEXT,
                'Domain holding the parent items; empty for the searched domain itself',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Search one domain.
     *
     * validate_context() runs require_login() but lets the guest user through, so guests are
     * refused on top of it; the domain is gated by {@see api::require_read()}. When a parent code
     * is given, the parent domain is gated the same way, so a search cannot probe the codes of a
     * domain the user may not read.
     *
     * @param string $domain
     * @param string $query
     * @param int $limit
     * @param string $parentcode
     * @param string $parentdomain
     * @return array ['items' => list of ['code', 'label'], 'hasmore' => bool]
     * @throws \invalid_parameter_exception When the parameters are invalid.
     * @throws \moodle_exception When the user is not logged in or is the guest user.
     * @throws \required_capability_exception When the domain is restricted and the user lacks the view capability.
     */
    public static function execute(
        string $domain,
        string $query,
        int $limit = api::DEFAULT_SEARCH_LIMIT,
        string $parentcode = '',
        string $parentdomain = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'domain' => $domain,
            'query' => $query,
            'limit' => $limit,
            'parentcode' => $parentcode,
            'parentdomain' => $parentdomain,
        ]);

        self::validate_context(context_system::instance());
        access::require_not_guest();
        access::require_domain_read($params['domain']);

        $parentcode = $params['parentcode'] === '' ? null : $params['parentcode'];
        $parentdomain = $params['parentdomain'] === '' ? null : $params['parentdomain'];
        if ($parentcode !== null && $parentdomain !== null) {
            api::require_read($parentdomain);
        }

        $result = api::search($params['domain'], $params['query'], $params['limit'], $parentcode, $parentdomain);
        $context = context_system::instance();
        foreach ($result['items'] as &$item) {
            $item['label'] = util::format_string($item['label'], $context, true, ['escape' => false]);
        }
        unset($item);
        return $result;
    }

    /**
     * Describe return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'code' => new external_value(PARAM_RAW, 'Reference code'),
                    'label' => new external_value(PARAM_RAW, 'Formatted label (multilang resolved, tags stripped)'),
                ])
            ),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more items match than were returned'),
        ]);
    }
}
