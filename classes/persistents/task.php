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
 * Provides the {@link mod_psgrading\persistents\task} class.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\persistents;

defined('MOODLE_INTERNAL') || die();
;
use \core\persistent;
use \core_user;
use \context_user;
use \context_course;

/**
 * Persistent model representing a single task.
 */
class task extends persistent {

    /** Table to store this persistent model instances. */
    const TABLE = 'psgrading_tasks';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            "courseid" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "creatorusername" => [
                'type' => PARAM_RAW,
            ],
            "taskname" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "pypuoi" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "outcomes" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "rubricjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "evidencejson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "published" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "deleted" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    public static function save_from_formdata($formdata) {

        // Some validation.
        if (empty($formdata->id)) {
            return;
        }

        $task = new static($formdata->id, $formdata);
        $task->save();

    }


}
