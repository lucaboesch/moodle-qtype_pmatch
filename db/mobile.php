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
 * Mobile plugin.
 *
 * @package qtype_pmatch
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'qtype_pmatch' => [
        'handlers' => [
            'pmatch' => [
                'delegate' => 'CoreQuestionDelegate',
                'method' => 'pmatch_view',
                'styles' => [
                    'url' => $CFG->wwwroot . '/question/type/pmatch/mobileapp.css',
                    'version' => 2019032204,
                ],
                'init' => 'pmatch_view',
            ],
        ],
        'lang' => [ // Language strings to be used.
            ['err_ousupsubnotsupportedonmobile', 'qtype_pmatch'],
        ],
    ],
];
