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
     * @var int Longest code or query, in characters, a read path derives a key from.
     *
     * Four key widths: a stored code and its key are at most MAX_KEY_LENGTH characters, and the
     * control characters and accents dropped before the key cannot make a caller's spelling
     * that much longer than a code anyone stores.
     */
    public const MAX_INPUT_LENGTH = self::MAX_KEY_LENGTH * 4;

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
     * The result is recomposed to NFC: decomposition also splits characters into parts that
     * are not marks (a Hangul syllable into two or three jamo, a Tamil two-part vowel sign into
     * two signs), which would otherwise make the key longer than the code. A few characters
     * are excluded from recomposition (musical symbols such as U+1D15E), so the key can still
     * be longer than the code: writers check both lengths. Stored keys are rewritten by
     * {@see manager::rekey_items()} when this derivation changes.
     *
     * @param string $code A code already cleaned by {@see self::clean_code()}.
     * @return string
     */
    public static function code_key(string $code): string {
        $decomposed = \Normalizer::normalize($code, \Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $code = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
            $recomposed = \Normalizer::normalize($code, \Normalizer::FORM_C);
            if (is_string($recomposed)) {
                $code = $recomposed;
            }
        }
        return core_text::strtolower($code);
    }

    /**
     * Derive the lookup key of a code a caller asks for, or '' when it is not to be looked up.
     *
     * Read paths call this rather than code_key(clean_code()): a code longer than
     * MAX_INPUT_LENGTH gets no key, so one request cannot make the key derivation and the
     * query's parameters carry a text of any length. '' also covers a code that cleans to nothing.
     *
     * @param string $code A code as the caller wrote it.
     * @return string
     */
    public static function lookup_key(string $code): string {
        if (core_text::strlen($code) > self::MAX_INPUT_LENGTH) {
            return '';
        }
        return self::code_key(self::clean_code($code));
    }

    /**
     * Whether a code and its key both fit their columns.
     *
     * @param string $code A code already cleaned by {@see self::clean_code()}.
     * @param string $codekey The key {@see self::code_key()} derived from it.
     * @return bool
     */
    public static function code_fits(string $code, string $codekey): bool {
        return core_text::strlen($code) <= self::MAX_KEY_LENGTH && core_text::strlen($codekey) <= self::MAX_KEY_LENGTH;
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
