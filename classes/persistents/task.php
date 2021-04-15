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

use \mod_psgrading\utils;
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
    const TABLE_TASK_LOGS = 'psgrading_task_logs';
    const TABLE_TASK_EVIDENCES = 'psgrading_task_evidences';
    const TABLE_TASK_CRITERIONS = 'psgrading_task_criterions';
    const TABLE_GRADES = 'psgrading_grades';
    const TABLE_GRADE_CRITERIONS = 'psgrading_grade_criterions';

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
            "criterionjson" => [
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
        global $DB, $USER;

        // Some validation.
        if (empty($data->id)) {
            return;
        }

        // Update the task.
        $task = new static($data->id, $data);
        $task->save();

        // Add a log entry.
        $log = new \stdClass();
        $log->taskid = $data->id;
        $log->username = $USER->username;
        $log->logtime = $task->get('timemodified');
        $log->formjson = json_encode($data);
        $DB->insert_record(static::TABLE_TASK_LOGS, $log);
        
        // Recreate criterions.
        $DB->delete_records(static::TABLE_TASK_CRITERIONS, array('taskid' => $data->id));
        $criterions = json_decode($data->criterionjson);
        $seq = 0;
        foreach ($criterions as $criterion) {
            $criterion->taskid = $data->id;
            $criterion->seq = $seq;
            $DB->insert_record(static::TABLE_TASK_CRITERIONS, $criterion);
            $seq++;
        }

        // Create evidences.
        $DB->delete_records(static::TABLE_TASK_EVIDENCES, array('taskid' => $data->id));
        $evidences = json_decode($data->evidencejson);
        if ($evidences) {
            foreach ($evidences as $evidence) {
                $evidence->taskid = $data->id;
                $DB->insert_record(static::TABLE_TASK_EVIDENCES, $evidence);
            }
        }

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
              ORDER BY timemodified DESC";
        $params = array($cmid);

        $records = $DB->get_records_sql($sql, $params);
        $tasks = array();
        foreach ($records as $record) {
            $tasks[] = new static($record->id, $record);
        }

        return $tasks;
    }


    public static function get_task_user_gradeinfo($taskid, $userid) {
        global $DB;

        // Check if grade for this task/user already exists.
        $student = \core_user::get_user($userid);
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADES . "}
                 WHERE taskid = ?
                   AND studentusername = ?";
        $params = array($taskid, $student->username);
        $gradeinfo = $DB->get_record_sql($sql, $params);

        if ($gradeinfo) {
            $gradeinfo->criterions = array();
            // Get the criterions.
            $sql = "SELECT *
                      FROM {" . static::TABLE_GRADE_CRITERIONS . "}
                     WHERE gradeid = ?";
            $params = array($gradeinfo->id);
            $criterionrecs = $DB->get_records_sql($sql, $params);
            foreach ($criterionrecs as $rec) {
                $gradeinfo->criterions[$rec->criterionid] = (object) array( 'gradelevel' => $rec->gradelevel );
            }
        }

        return $gradeinfo;
    }


    public static function load_criterions(&$task) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_CRITERIONS . "}
                 WHERE taskid = ?
              ORDER BY seq ASC";
        $params = array($task->id);

        $records = $DB->get_records_sql($sql, $params);
        $criterions = array();
        foreach ($records as $record) {
            $criterions[] = $record;
        }

        $task->criterions = $criterions;
    }

    public static function load_evidences(&$task) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_EVIDENCES . "}
                 WHERE taskid = ?";
        $params = array($task->id);

        $records = $DB->get_records_sql($sql, $params);
        $evidences = array();
        foreach ($records as $record) {
            $evidences[] = $record;
        }

        $task->evidences = $evidences;
    }


    public static function save_task_grades($data) {
        global $DB, $USER;

        // Some validation.
        if (empty($data->taskid) || empty($data->userid)) {
            return;
        }

        $student = \core_user::get_user($data->userid);

        // Check if grade for this task/user already exists.
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADES . "}
                 WHERE taskid = ?
                   AND studentusername = ?";
        $params = array($data->taskid, $student->username);
        $graderec = $DB->get_record_sql($sql, $params);

        if ($graderec) {
            // Update the existing grade data.
            $graderec->graderusername = $USER->username;
            $graderec->engagement = $data->engagement;
            $graderec->comment = $data->comment;
            $graderec->evidences = $data->evidences;
            $DB->update_record(static::TABLE_GRADES, $graderec);
        } else {
            // Insert new grade data.
            $graderec = new \stdClass();
            $graderec->taskid = $data->taskid;
            $graderec->studentusername = $student->username;
            $graderec->graderusername = $USER->username;
            $graderec->engagement = $data->engagement;
            $graderec->comment = $data->comment;
            $graderec->evidences = $data->evidences;
            $graderec->id = $DB->insert_record(static::TABLE_GRADES, $graderec);
        }

        // Recreate criterion grades.
        $DB->delete_records(static::TABLE_GRADE_CRITERIONS, array('gradeid' => $graderec->id));
        $criterions = json_decode($data->criterionjson);
        foreach ($criterions as $selection) {
            $criterion = new \stdClass();
            $criterion->taskid = $data->taskid;
            $criterion->criterionid = $selection->id;
            $criterion->gradeid = $graderec->id;
            $criterion->gradelevel = $selection->selectedlevel;
            $DB->insert_record(static::TABLE_GRADE_CRITERIONS, $criterion);
        }

        return $graderec->id;
    }


}
