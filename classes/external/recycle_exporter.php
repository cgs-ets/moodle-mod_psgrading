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
 * Provides {@link mod_psgrading\external\list_exporter} class.
 *
 * @package   mod_psgrading
 * @copyright 2025 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\external;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use core\external\exporter;

class recycle_exporter extends exporter {

    protected static function define_other_properties() {

        return [
            'deletedtasks' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'isempty' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ]
            ];
    }


    /**
     * Returns a list of objects that are related.
     *
     * Data needed to generate "other" properties.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'cmid' => 'int',
        ];
    }

    protected function get_other_values(renderer_base $output) {
        global $DB;


        $sql = "SELECT pt.id as taskid, u.id, pt.taskname, pt.pypuoi, pt.published
                FROM {psgrading_tasks} pt
                JOIN {user} u ON pt.creatorusername = u.username
                WHERE pt.cmid = :id AND pt.deleted = :deleted AND engagementjson <> ''
                ORDER by pt.taskname";

       $params = ['id' => $this->related['cmid'], 'deleted' => 1];

       $tasks =  $DB->get_records_sql($sql, $params);
       $deleted = [];
       $isempty = false;
       foreach($tasks as $task) {
            $t = new \stdClass();
            $t->taskname = $task->taskname;
            $t->pypuoi = $task->pypuoi;
            $t->creator = $output->user_picture($task, array('course' => $this->related['courseid'],'includefullname' => true, 'class' => 'userpicture'
     ));
            $t->published = $task->published == 1 ? 'Yes' :  'No';
            $t->taskid = $task->taskid;
            $deleted['tasks'][] = $t;

       }

        if (count($deleted) == 0) {
            $isempty = true;
        }

       return ['deletedtasks' => $deleted,
               'isempty' => $isempty
              ];

    }

}