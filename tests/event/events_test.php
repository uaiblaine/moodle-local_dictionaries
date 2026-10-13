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

use local_dictionaries\api;
use local_dictionaries\local\manager;

/**
 * Tests for the events fired by the manager.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\event\domain_created
 * @covers     \local_dictionaries\event\domain_updated
 * @covers     \local_dictionaries\event\items_imported
 * @covers     \local_dictionaries\local\manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events_test extends \advanced_testcase {
    /**
     * Creating a domain fires domain_created; changing it fires domain_updated; saving it unchanged fires nothing.
     */
    public function test_domain_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new manager();

        $sink = $this->redirectEvents();
        $domain = $manager->upsert_domain('uf', 'States');
        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(domain_created::class, $events[0]);
        $this->assertSame((int)$domain->id, (int)$events[0]->objectid);
        $this->assertSame('local_dictionaries_domain', $events[0]->objecttable);
        $this->assertSame('uf', $events[0]->other['shortname']);
        $this->assertEquals(\context_system::instance(), $events[0]->get_context());

        $manager->upsert_domain('uf', 'States');
        $this->assertCount(0, $sink->get_events());

        $manager->upsert_domain('uf', 'States', null, api::VISIBILITY_LOGGEDIN);
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(domain_updated::class, $events[0]);
        $this->assertSame((int)$domain->id, (int)$events[0]->objectid);
    }

    /**
     * A chunked import whose items were all dropped before any write still fires one event with the dropped count.
     */
    public function test_items_imported_event_when_every_item_is_dropped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new manager();
        $domain = $manager->upsert_domain('codes', 'Codes');

        $sink = $this->redirectEvents();
        $counts = $manager->upsert_items_chunked('codes', [
            ['code' => '', 'label' => 'No code'],
            ['label' => 'No code key'],
        ], 1);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(['inserted' => 0, 'updated' => 0, 'ignored' => 2], $counts);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(items_imported::class, $events[0]);
        $this->assertSame((int)$domain->id, (int)$events[0]->objectid);
        $this->assertSame(0, $events[0]->other['inserted']);
        $this->assertSame(0, $events[0]->other['updated']);
        $this->assertSame(2, $events[0]->other['ignored']);
    }

    /**
     * One items_imported event per batch, with the counts in other; the chunked import fires one per committed chunk.
     */
    public function test_items_imported_event_per_batch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new manager();
        $domain = $manager->upsert_domain('codes', 'Codes');

        $sink = $this->redirectEvents();
        $manager->upsert_items('codes', [
            ['code' => 'a', 'label' => 'A'],
            ['code' => 'b', 'label' => 'B'],
        ]);
        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(items_imported::class, $events[0]);
        $this->assertSame((int)$domain->id, (int)$events[0]->objectid);
        $this->assertSame('local_dictionaries_domain', $events[0]->objecttable);
        $this->assertSame(2, $events[0]->other['inserted']);
        $this->assertSame(0, $events[0]->other['updated']);
        $this->assertSame(0, $events[0]->other['ignored']);

        $manager->upsert_items_chunked('codes', [
            ['code' => 'a', 'label' => 'A2'],
            ['code' => 'b', 'label' => 'B'],
            ['code' => 'c', 'label' => 'C'],
        ], 1);
        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(3, $events);
        // Chunk 1 updates 'a', chunk 2 finds 'b' unchanged, chunk 3 inserts 'c'.
        $counts = [];
        foreach ($events as $event) {
            $counts[] = [$event->other['inserted'], $event->other['updated'], $event->other['ignored']];
        }
        $this->assertSame([[0, 1, 0], [0, 0, 1], [1, 0, 0]], $counts);

        // An empty batch is not an import.
        $manager->upsert_items('codes', []);
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * The event refuses to be created without every count.
     */
    public function test_items_imported_requires_counts(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        items_imported::create([
            'context' => \context_system::instance(),
            'objectid' => 1,
            'other' => ['shortname' => 'codes', 'inserted' => 1],
        ]);
    }
}
