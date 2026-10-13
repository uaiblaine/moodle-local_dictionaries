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

/**
 * Web service definitions for local_dictionaries.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_dictionaries_upsert_reference_items' => [
        'classname' => 'local_dictionaries\\external\\upsert_reference_items',
        'methodname' => 'execute',
        'description' => 'Insert or update the code-label items of one reference dictionary domain.',
        'type' => 'write',
        'capabilities' => 'local/dictionaries:manage',
        'ajax' => true,
    ],
    'local_dictionaries_get_reference_labels' => [
        'classname' => 'local_dictionaries\\external\\get_reference_labels',
        'methodname' => 'execute',
        'description' => 'Resolve the labels of codes in one domain. Restricted domains need local/dictionaries:view.',
        'type' => 'read',
        'capabilities' => 'local/dictionaries:view',
        'ajax' => true,
        'readonlysession' => true,
    ],
    'local_dictionaries_search_items' => [
        'classname' => 'local_dictionaries\\external\\search_items',
        'methodname' => 'execute',
        'description' => 'Search the active items of one domain by label or code. Restricted domains need local/dictionaries:view.',
        'type' => 'read',
        'capabilities' => 'local/dictionaries:view',
        'ajax' => true,
        'readonlysession' => true,
    ],
];

$services = [
    'Reference dictionaries API' => [
        'functions' => [
            'local_dictionaries_upsert_reference_items',
            'local_dictionaries_get_reference_labels',
            'local_dictionaries_search_items',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_dictionaries_api',
    ],
];
