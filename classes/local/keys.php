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

use core_text;
use local_dictionaries\resolver;

/**
 * The one place where domain shortnames, code keys and search keys are derived.
 *
 * Writes, lookups and searches must all go through these methods: the unique index on
 * (domainid, codekey) and the search index on (domainid, searchkey) only hold if every path
 * derives the keys the same way.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class keys {
    /** @var int Width of the code, codekey and searchkey columns, in characters. */
    public const MAX_KEY_LENGTH = 255;

    /**
     * Normalise a domain shortname, or return '' when it is empty or does not match the pattern.
     *
     * @param string $shortname
     * @return string
     */
    public static function domain(string $shortname): string {
        $shortname = core_text::strtolower(trim($shortname));
        if ($shortname === '' || !preg_match(resolver::DOMAIN_PATTERN, $shortname)) {
            return '';
        }
        return $shortname;
    }

    /**
     * Clean a code as written: trim it and strip ASCII control characters.
     *
     * @param string $code
     * @return string
     */
    public static function clean_code(string $code): string {
        return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $code) ?? '');
    }

    /**
     * Derive the lookup key of a code: codes are equal ignoring case and accents on every database family.
     *
     * Folding here, rather than trusting the column collation, is what makes PostgreSQL
     * (case-sensitive, accent-sensitive) agree with MariaDB (utf8mb4_unicode_ci, which folds
     * both). The accents are removed by canonical decomposition and dropping the combining
     * marks, so characters without a decomposition (non-Latin scripts) are kept as they are.
     *
     * @param string $code A code already cleaned by {@see self::clean_code()}.
     * @return string
     */
    public static function code_key(string $code): string {
        $decomposed = \Normalizer::normalize($code, \Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $code = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
        }
        return core_text::strtolower($code);
    }

    /**
     * Derive the search key of a label or a query: lowercase, accents removed, at most 255 characters.
     *
     * @param string $text
     * @return string
     */
    public static function search_key(string $text): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        $ascii = core_text::specialtoascii($text);
        // Transliteration can drop every character of a script it cannot map; keep the original then.
        if (!is_string($ascii) || trim($ascii) === '') {
            $ascii = $text;
        }
        $key = core_text::strtolower(trim($ascii));
        return core_text::substr($key, 0, self::MAX_KEY_LENGTH);
    }
}
