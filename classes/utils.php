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
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class utils {

    const SUBJECTOPTIONS = array (
        array (
            'txt' => 'Select',
            'val' => '',
        ),
        array (
            'txt' => 'English',
            'val' => 'English',
        ),
        array (
            'txt' => 'HAAS',
            'val' => 'HAAS',
        ),
        array (
            'txt' => 'Science',
            'val' => 'Science',
        ),
        array (
            'txt' => 'Technology',
            'val' => 'Technology',
        ),
        array (
            'txt' => 'Maths',
            'val' => 'Maths',
        ),
        array (
            'txt' => 'H&PE',
            'val' => 'H&PE',
        ),
        array (
            'txt' => 'VisArts',
            'val' => 'VisArts',
        ),
        array (
            'txt' => 'Language',
            'val' => 'Language',
        ),
        array (
            'txt' => 'Music',
            'val' => 'Music',
        ),
    );

    public static function decorate_subjectdata($rubricdata) {
        foreach ($rubricdata as $i => $row) {
            $rubricdata[$i]->subject = array(
                'value' => $row->subject,
                'options' => static::get_subject_options_with_selected($row->subject),
            );
        }
        return $rubricdata;
    }

    public static function get_subject_options_with_selected($selected) {
        $options = array();
        foreach (static::SUBJECTOPTIONS as $i => $option) {
            if ($option['val'] === $selected) {
                $option['sel'] = true;
            }
            $options[] = $option;
        };
        return $options;
    }

    public static function get_stub_criterion() {
        $criterion = new \stdClass();
        $criterion->subject = '';
        return $criterion;
    }
}