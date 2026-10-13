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

use local_dictionaries\api;
use moodle_exception;

/**
 * Who may read a domain through the web services.
 *
 * Decided on the domain's recorded visibility, never on what the caller says it is editing.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Refuse the guest user, whom require_login() lets through.
     *
     * @return void
     * @throws moodle_exception When the current user is the guest user.
     */
    public static function require_not_guest(): void {
        if (isguestuser()) {
            throw new moodle_exception('noguest');
        }
    }

    /**
     * Require the right to read one domain: delegates to {@see api::require_read()}, the one
     * implementation of the rule.
     *
     * @param string $shortname Domain shortname as received.
     * @return void
     * @throws moodle_exception When the domain is open and the user is not logged in or is the guest.
     * @throws \required_capability_exception When the domain is restricted or unknown and the
     *     user lacks local/dictionaries:view.
     */
    public static function require_domain_read(string $shortname): void {
        api::require_read($shortname);
    }
}
