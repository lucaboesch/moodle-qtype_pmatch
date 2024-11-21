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

namespace qtype_pmatch\local\spell;

/**
 * Implements the {@see qtype_pmatch_spell_checker} by saying that any string is a
 * correctly spelled word. This can be used when there is no back-end installed.
 *
 * @package qtype_pmatch
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_pmatch_null_spell_checker extends qtype_pmatch_spell_checker {

    /**
     * {@inheritdoc}
     *
     * @param string $word The word to check.
     * @return bool whether the word is in the dictionary.
     */
    public function is_in_dictionary($word) {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return string translated name of this back-end, for use in the UI.
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('spellcheckernull', 'qtype_pmatch');
    }

    /**
     * {@inheritdoc}
     *
     * @return bool whether the necessary libraries are installed for this back-end to work.
     */
    public static function is_available() {
        return true;
    }

}
