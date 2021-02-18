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
            "cmid" => [
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
            "draftjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
        ];
    }

    public static function save_from_data($data) {
        // Some validation.
        if (empty($data->id)) {
            return;
        }
        $task = new static($data->id, $data);
        $task->save();

        return $data->id;
    }

    public static function save_draft($formjson) {
        // Some validation.
        $formdata = json_decode($formjson);
        if (empty($formdata->id)) {
            return;
        }

        $task = new static($formdata->id);
        $task->set('draftjson', $formjson);
        $task->save();
    }


    public static function get_for_coursemodule($cmid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND cmid = ?
              ORDER BY published DESC, timemodified DESC";
        $params = array($cmid);

        $records = $DB->get_records_sql($sql, $params);
        $tasks = array();
        foreach ($records as $record) {
            $tasks[] = new static($record->id, $record);
        }

        return $tasks;
    }


}
