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
 * Tests for the CSV parser of the manager.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\local\manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class csv_parser_test extends \advanced_testcase {
    /**
     * Parse content with a fresh manager.
     *
     * @param string $content
     * @return array
     */
    private function parse(string $content): array {
        return (new manager())->parse_csv_content($content);
    }

    /**
     * A header line is recognised and skipped.
     */
    public function test_header_is_skipped(): void {
        $result = $this->parse("code,label\n16,Sao Paulo\n17,Rio");

        $this->assertSame([
            ['code' => '16', 'label' => 'Sao Paulo'],
            ['code' => '17', 'label' => 'Rio'],
        ], $result['items']);
        $this->assertSame([], $result['rejected']);
    }

    /**
     * A UTF-8 byte order mark does not hide the header.
     */
    public function test_byte_order_mark_is_stripped(): void {
        $result = $this->parse("\xEF\xBB\xBFcode,label\r\n16,Sao Paulo\r\n");

        $this->assertSame([['code' => '16', 'label' => 'Sao Paulo']], $result['items']);
    }

    /**
     * Semicolon and tab delimiters are detected from the first line.
     */
    public function test_delimiter_is_detected(): void {
        $semicolon = $this->parse("code;label\n1;Um, dois\n2;Dois");
        $this->assertSame([
            ['code' => '1', 'label' => 'Um, dois'],
            ['code' => '2', 'label' => 'Dois'],
        ], $semicolon['items']);

        $tab = $this->parse("1\tOne two\n2\tTwo");
        $this->assertSame([
            ['code' => '1', 'label' => 'One two'],
            ['code' => '2', 'label' => 'Two'],
        ], $tab['items']);

        // Control: a comma file whose labels hold semicolons inside quotes stays a comma file.
        $comma = $this->parse("1,\"a; b; c\"\n2,d");
        $this->assertSame('a; b; c', $comma['items'][0]['label']);
    }

    /**
     * A quoted field may span lines; line numbers of later rows still count physical lines.
     */
    public function test_quoted_multiline_field_and_line_numbers(): void {
        $result = $this->parse("code,label\n1,\"First line\nsecond line\"\n,No code\n3,Three");

        $this->assertSame([
            ['code' => '1', 'label' => "First line\nsecond line"],
            ['code' => '3', 'label' => 'Three'],
        ], $result['items']);
        $this->assertSame([['line' => 4, 'reason' => manager::REJECT_EMPTY_CODE]], $result['rejected']);
    }

    /**
     * Invalid UTF-8, missing columns and over-long codes are rejected with their line, not dropped.
     */
    public function test_bad_rows_are_rejected_with_line_numbers(): void {
        $content = "1,One\n"
            . "2,Bad \xC3\x28 bytes\n"
            . "only-one-column\n"
            . str_repeat('x', 256) . ",Too long\n"
            . "\n"
            . "6,Six";
        $result = $this->parse($content);

        $this->assertSame(['1', '6'], array_column($result['items'], 'code'));
        $this->assertSame([
            ['line' => 2, 'reason' => manager::REJECT_INVALID_UTF8],
            ['line' => 3, 'reason' => manager::REJECT_TOO_FEW_COLUMNS],
            ['line' => 4, 'reason' => manager::REJECT_CODE_TOO_LONG],
        ], $result['rejected']);
    }

    /**
     * A code repeated in the file keeps its last row and is counted, whatever its case.
     */
    public function test_duplicates_last_wins_and_are_counted(): void {
        $result = $this->parse("a,First\nb,Bee\nA,Second");

        $this->assertSame(1, $result['duplicates']);
        $this->assertSame([
            ['code' => 'A', 'label' => 'Second'],
            ['code' => 'b', 'label' => 'Bee'],
        ], $result['items']);
    }

    /**
     * A header may reorder columns and add parentcode, sortorder and active.
     */
    public function test_header_with_optional_columns(): void {
        $result = $this->parse("label,code,active,sortorder,parentcode\nSão Paulo,3550308,1,10,SP\nOld,9,x,1,SP\nOther,8,0,y,SP");

        $this->assertSame([[
            'code' => '3550308',
            'label' => 'São Paulo',
            'sortorder' => 10,
            'active' => 1,
            'parentcode' => 'SP',
        ]], $result['items']);
        $this->assertSame([
            ['line' => 3, 'reason' => manager::REJECT_INVALID_ACTIVE],
            ['line' => 4, 'reason' => manager::REJECT_INVALID_SORTORDER],
        ], $result['rejected']);
    }

    /**
     * Empty content gives an empty result.
     */
    public function test_empty_content(): void {
        $this->assertSame(['items' => [], 'rejected' => [], 'duplicates' => 0], $this->parse(" \n\r\n"));
    }
}
