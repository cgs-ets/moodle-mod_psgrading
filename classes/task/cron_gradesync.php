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
 * The main scheduled task to set up syncing of grades to the staging table.
 * The effort is divided into independent adhoc tasks that process the sync for a single course.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\task;

defined('MOODLE_INTERNAL') || die();


class cron_gradesync extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * A list of courses that have instances of the ps grading activity.
     */
    protected $courseswithinstances = [];

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask_gradesync', 'mod_psgrading');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $this->log_start("Starting gradesync.");

        // Get all psgrading instances.
        $sql = "SELECT DISTINCT course
                FROM {psgrading}";
        $this->courseswithinstances = $DB->get_records_sql($sql);
        if (empty($this->courseswithinstances)) {
            return;
        }

        // call sync grads function.
        $this->sync_grades();

        $this->log_finish("Done");
    }

    /**
     * The main syncing process.
     *
     */
    protected function sync_grades() {
        global $DB;

        // Create an adhoc task for each course.
        foreach ($this->courseswithinstances as $coursewithinstance) {
            // Look up the course, skip if not visible or ended.
            $sql = "SELECT *
                      FROM {course}
                     WHERE id = ?
                       AND visible = 1
                       AND (enddate = 0 OR enddate > ?)";
            $params = [$coursewithinstance->course, time()];
            if ($course = $DB->get_record_sql($sql, $params)) {
                // Reporting period 1.
                $this->log("Creating adhoc gradesync task for $course->fullname ($course->id) for reporting period 1", 1);
                $task = new \mod_psgrading\task\adhoc_gradesync();
                $task->set_custom_data([$course->id, 1]);
                $task->set_component('mod_psgrading');
                \core\task\manager::queue_adhoc_task($task);

                // Reporting period 2.
                $this->log("Creating adhoc gradesync task for $course->fullname ($course->id) for reporting period 2", 1);
                $task = new \mod_psgrading\task\adhoc_gradesync();
                $task->set_custom_data([$course->id, 2]);
                $task->set_component('mod_psgrading');
                \core\task\manager::queue_adhoc_task($task);
            }
        }
    }



}
