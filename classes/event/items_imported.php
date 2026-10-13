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

namespace local_dictionaries\event;

/**
 * Event fired per batch of items written into one domain: once per web service call, once per
 * committed chunk of a CSV import ({@see \local_dictionaries\local\manager::upsert_items_chunked()}).
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items_imported extends \core\event\base {
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_dictionaries_domain';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventitemsimported', 'local_dictionaries');
    }

    /**
     * Return the non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' wrote items into the reference dictionary domain with id '$this->objectid': " .
            "{$this->other['inserted']} inserted, {$this->other['updated']} updated, {$this->other['ignored']} ignored.";
    }

    /**
     * Return the page where the domain is managed.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/dictionaries/manage.php');
    }

    /**
     * Check the data the event needs.
     *
     * @return void
     * @throws \coding_exception When the domain id, the shortname or a count is missing.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
        if (!isset($this->other['shortname'])) {
            throw new \coding_exception('The \'shortname\' value must be set in other.');
        }
        foreach (['inserted', 'updated', 'ignored'] as $count) {
            if (!isset($this->other[$count])) {
                throw new \coding_exception("The '$count' value must be set in other.");
            }
        }
    }

    /**
     * Domains live in system context and are not part of course backups.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_dictionaries_domain', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * The values in other are a shortname and counts, none of which needs mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
