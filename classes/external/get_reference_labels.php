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
use invalid_parameter_exception;
use local_dictionaries\local\access;
use local_dictionaries\local\manager;
use local_dictionaries\resolver;

/**
 * Resolve the labels of codes in one domain.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_reference_labels extends external_api {
    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'domain' => new external_value(PARAM_ALPHANUMEXT, 'Domain shortname'),
            'codes' => new external_multiple_structure(
                new external_value(PARAM_RAW_TRIMMED, 'Reference code')
            ),
        ]);
    }

    /**
     * Resolve labels for codes.
     *
     * validate_context() runs require_login(); guests are refused on top of it. A domain
     * recorded as open to logged-in users needs no capability; any other needs
     * local/dictionaries:view.
     *
     * @param string $domain
     * @param array $codes
     * @return array ['items' => list of ['code', 'label']], keyed by the caller's code spelling.
     * @throws invalid_parameter_exception When the parameters are invalid or the batch is too large.
     * @throws \moodle_exception When the user is not logged in or is the guest user.
     * @throws \required_capability_exception When the domain is restricted and the user lacks the view capability.
     */
    public static function execute(string $domain, array $codes): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'domain' => $domain,
            'codes' => $codes,
        ]);

        self::validate_context(context_system::instance());
        access::require_not_guest();
        access::require_domain_read($params['domain']);

        if (count($params['codes']) > manager::MAX_BATCH_SIZE) {
            throw new invalid_parameter_exception(
                get_string('maxbatchsizeexceeded', 'local_dictionaries', manager::MAX_BATCH_SIZE)
            );
        }

        $context = context_system::instance();
        $items = [];
        foreach (resolver::resolve_bulk($params['domain'], $params['codes']) as $code => $label) {
            $items[] = [
                'code' => (string)$code,
                'label' => util::format_string((string)$label, $context, true, ['escape' => false]),
            ];
        }
        return ['items' => $items];
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
                    'code' => new external_value(PARAM_RAW, 'Reference code, as the caller spelled it'),
                    'label' => new external_value(PARAM_RAW, 'Formatted label (multilang resolved, tags stripped)'),
                ])
            ),
        ]);
    }
}
