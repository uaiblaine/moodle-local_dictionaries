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
 * Data generator for local_dictionaries.
 *
 * @package    local_dictionaries
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_dictionaries_generator extends component_generator_base {
    /** @var int Number of domains created, for default shortnames. */
    protected $domaincount = 0;

    /**
     * Reset the counters between tests.
     *
     * @return void
     */
    public function reset() {
        $this->domaincount = 0;
    }

    /**
     * Create a domain through the manager, so events and normalisation run as in production.
     *
     * @param array $record shortname, name, description, visibility (all optional).
     * @return stdClass The stored domain record.
     */
    public function create_domain(array $record = []): stdClass {
        $this->domaincount++;
        $shortname = (string)($record['shortname'] ?? 'domain' . $this->domaincount);
        return (new \local_dictionaries\local\manager())->upsert_domain(
            $shortname,
            (string)($record['name'] ?? 'Domain ' . $this->domaincount),
            isset($record['description']) ? (string)$record['description'] : null,
            isset($record['visibility']) ? (int)$record['visibility'] : null
        );
    }

    /**
     * Create or update one item through the manager.
     *
     * @param array $record domain (shortname, required), code (required), label (defaults to the
     *     code), sortorder, active, parentcode, parentdomain (all optional).
     * @return stdClass The stored item record.
     * @throws coding_exception When the domain or the code is missing.
     */
    public function create_item(array $record): stdClass {
        global $DB;

        if (empty($record['domain']) || !isset($record['code']) || (string)$record['code'] === '') {
            throw new coding_exception('create_item() needs a domain shortname and a code.');
        }
        $item = [
            'code' => (string)$record['code'],
            'label' => (string)($record['label'] ?? $record['code']),
        ];
        foreach (['sortorder', 'active', 'parentcode'] as $optional) {
            if (array_key_exists($optional, $record)) {
                $item[$optional] = $record[$optional];
            }
        }

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_items((string)$record['domain'], [$item], $record['parentdomain'] ?? null);

        $domain = $manager->get_domain((string)$record['domain']);
        return $DB->get_record(
            'local_dictionaries_item',
            [
                'domainid' => $domain->id,
                'codekey' => \local_dictionaries\local\keys::code_key(\local_dictionaries\local\keys::clean_code($item['code'])),
            ],
            '*',
            MUST_EXIST
        );
    }
}
