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

/**
 * Tests for the management page's domain rows.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\local\domain_rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class domain_rows_test extends \advanced_testcase {
    /**
     * Build the rows for the domains the manager lists.
     *
     * @return array
     */
    private function rows(): array {
        $context = \context_system::instance();
        $domains = (new manager())->list_domains();
        return domain_rows::build($domains, $context, new \moodle_url('/local/dictionaries/manage.php'));
    }

    /**
     * An ampersand reaches the page escaped once, not twice.
     */
    public function test_ampersand_is_escaped_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        (new manager())->upsert_domain('plain', 'Ham & Eggs', 'Salt & Pepper');

        $rows = $this->rows();
        $this->assertSame('Ham & Eggs', $rows[0]['name']);
        $this->assertSame('Salt & Pepper', $rows[0]['description']);

        $html = $PAGE->get_renderer('core')->render_from_template('local_dictionaries/manage', [
            'hasdomains' => true,
            'domains' => $rows,
        ]);
        $this->assertStringContainsString('Ham &amp; Eggs', $html);
        $this->assertStringContainsString('Salt &amp; Pepper', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * Multilang spans in the name and description are resolved to the user's language.
     */
    public function test_multilang_spans_are_filtered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        \filter_manager::reset_caches();
        set_config('stringfilters', 'multilang');
        set_config('filterall', 1);
        $name = '<span lang="en" class="multilang">English name</span><span lang="pt_br" class="multilang">Nome</span>';
        $description = '<span lang="en" class="multilang">English text</span><span lang="pt_br" class="multilang">Texto</span>';
        (new manager())->upsert_domain('multi', $name, $description);

        $rows = $this->rows();
        $this->assertSame('English name', $rows[0]['name']);
        $this->assertSame('English text', $rows[0]['description']);
    }
}
