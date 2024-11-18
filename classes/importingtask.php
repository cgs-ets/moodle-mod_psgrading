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
 *
 * @package   mod_psgrading
 * @copyright 2024, Veronica Bermegui <>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading;

use dml_read_exception;
use Exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 *  Utility class to manage the import of tasks
 */
class importingtask {
    /**
     * Get the PSgrading activities for a given course
     *
     * @param mixed $courseid
     */
    public static function get_psgrading_activities($courseids) {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'psgrading']);

        $sql = "SELECT DISTINCT pg.name,
                pg.id AS activityid,
                pg.course AS course,
                cm.id AS cmid
                FROM {psgrading} pg
                JOIN {course_modules} cm ON pg.id = cm.instance
                JOIN {psgrading_tasks} pt ON pt.cmid = cm.id
                JOIN {psgrading_task_criterions} ptc ON ptc.taskid = pt.id
                WHERE pg.course IN ($courseids)
                AND cm.module = :moduleid
               ORDER BY pg.name;";

        $params = ['moduleid' => $moduleid];
        $results = $DB->get_records_sql($sql, $params);
        $activities = [];
        $cmids = [];

        foreach ($results as $result) {
            $activity = new stdClass();
            $activity->activityid = $result->activityid;
            $activity->activityname = $result->name;
            $activities['activities'][] = $activity;
            $cmids[$result->activityid][] = $result->cmid;
        }

        $activities['size']  = count($results);
        $activities['cmids'] = json_encode($cmids);

        return $activities;
    }

    /**
     * Undocumented function
     *
     * @param mixed $activityid
     * @param mixed $includeunpublished
     */
    public static function get_activity_tasks($cmid) {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'psgrading']);

        $sql = "SELECT t.*
                FROM {psgrading_tasks} t
                JOIN {course_modules} cm ON t.cmid = cm.id
                JOIN {psgrading} ps ON ps.id = cm.instance
                WHERE cm.id IN ($cmid)
                AND cm.module = :moduleid
                AND t.deleted = 0
                ORDER BY ps.name";

        $results = $DB->get_records_sql($sql, ['moduleid' => $moduleid]);
        $tasks = [];

        foreach ($results as $result) {
            $task = new stdClass();
            $task->id = $result->id;
            $task->taskname = $result->taskname;
            $tasks['tasks'][] = $task;
        }

        $tasks['size']  = count($results);

        return $tasks;

    }

    /**
     * Get courses with psgrading modules
     */
    public static function get_courses_with_psgrading_modules() {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'psgrading']);

        $sql = "SELECT cm.id AS cmid, c.id AS courseid, c.shortname AS coursename
                FROM {course_modules} cm
                JOIN {course} c ON cm.course = c.id
                WHERE cm.module = :moduleid
                ORDER BY c.shortname";

        $params = ['moduleid' => $moduleid];
        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }

    /**
     * Call a SP that  creates a copy of the  tasks (MariaDB)
     *
     * @param mixed $cmid
     * @param mixed $selectedtasks
     * @return void
     */
    // public static function copy_tasks_to_activity($cmid, $selectedtasks) {
    //     global $DB;
    //     $selectedtasks = implode(',', $selectedtasks);

    //     // Prepare the SQL call.
    //     $sql = "CALL mod_psgrading_copy_tasks(:cmid, :selectedtasks, @result_message)";
    //     // Execute the stored procedure.
    //     $DB->execute($sql, ['cmid' => $cmid, 'selectedtasks' => $selectedtasks]);

    //     // Fetch the result message.
    //     $resultmessage = $DB->get_field_sql("SELECT @result_message AS result_message");

    //     return $resultmessage;

    // }

    /**
     * Call a SP that creates a copy of the tasks (SQL SERVER)
     *
     * @param mixed $cmid
     * @param mixed $selectedtasks
     * @return array
     */
    public static function copy_tasks_to_activity($cmid, $selectedtasks) {
        global $DB;
        $selectedtasks = implode(',', $selectedtasks);

        // Prepare the SQL call to execute the stored procedure.
        $sql = "EXEC mod_psgrading_copy_tasks :cmid, :selectedtasks";

        // Execute the stored procedure.
        $params = [
            'cmid' => $cmid,
            'selectedtasks' => $selectedtasks,
        ];
        $message = [];
        try {

            $DB->get_record_sql($sql, $params);
            $message['s'] = 1;
        } catch (Exception $e) {
            $message['e'] = 1;
        }

        return $message;
    }

}

