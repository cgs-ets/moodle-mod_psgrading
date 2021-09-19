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
 * Adhoc task to sync grades for a single course.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\task;

defined('MOODLE_INTERNAL') || die();

use mod_psgrading\utils;
use mod_psgrading\external\grade_exporter;

class adhoc_gradesync extends \core\task\adhoc_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * @var stdClass The mod id and course id for this task.
     */
    protected $mod = null;

    /**
     * @var array Existing staged grades.
     */
    protected $existinggrades = array();

    /**
     * @var array Grades to be stored.
     */
    protected $grades = array();

    /**
     * @var moodle_database.
     */
    protected $externalDB = null;

    /**
     * @var stdClass plugin conig.
     */
    protected $config = null;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask_gradesync', 'local_gradesync');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $this->mod = $this->get_custom_data();
        $this->log_start("Processing grade sync for psgrading mod {$this->mod->id}");
        $course = $DB->get_record('course', array('id' => $this->mod->course));
        if (empty($course)) {
            $this->log("Error - course record not found.", 1);
            return;
        }
        $this->log("Course record found: $course->fullname", 1);

        // Load in all of the existing staged grades for this course.
        $this->log("Caching existing grades for this mod.", 1);
        $this->cache_existing_grades();

        // Get the cmid for this mod instance.
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE instance = ?
                   AND course = ?
                   AND module = ?";
        $moduleid = $DB->get_field('modules', 'id', array('name'=> 'psgrading'));
        $this->mod->cmid = $DB->get_field_sql($sql, array(
            'instance' => $this->mod->id,
            'course' => $this->mod->course,
            'module' => $moduleid,
        ));

        // Cache grade for each student.
        $students = utils::get_enrolled_students($this->mod->course);
        foreach ($students as $studentid) {
            $this->get_grades($studentid);
        }
        //echo "<pre>"; var_export($this->grades); exit;

        // Save student grades.
        $this->log("Saving grades to database.", 1);
        $this->save_grades();

        // Delete remaining grades to complete the sync.
        $this->log("Deleting old grades.", 1);
        $this->delete_grades();
        
        $this->log_finish("Done");
    }

    /**
     * Load in all of the existing staged grades for the course.
     */
    protected function cache_existing_grades() {
        global $DB;

        $sql = "SELECT *
                  FROM {psgrading_gradesync}
                 WHERE id = ?";
        $grades = $DB->get_records_sql($sql, array('id' => $this->mod->id));
        foreach ($grades as $grade) {
            $key = $grade->psgradingid . '-' . $grade->subject  . '-' . $grade->username;
            $this->log("Caching existing staged grades {$grade->psgradingid}/{$grade->subject} for {$grade->username}", 2);
            $this->existinggrades[$key] = $grade;
        }
    }

    /**
     * Get grade for a student.
     *
     * @param int studentid
     */
    protected function get_grades($studentid) {
        global $DB, $PAGE;

        // Use the grade exporter to get grades for this student.
        $relateds = array(
            'cmid' => (int) $this->mod->cmid,
            'userid' => $studentid,
            'isstaff' => true, // Only staff can view the report grades.
            'includehiddentasks' => true,
        );
        $gradeexporter = new grade_exporter(null, $relateds);
        $output = $PAGE->get_renderer('core');
        $gradedata = $gradeexporter->export($output);
        //echo "<pre>"; var_export($gradedata); exit;
        if (empty($gradedata->reportgrades)) {
            return;
        }

        $username = $DB->get_field('user', 'username', array('id' => $studentid));
        foreach($gradedata->reportgrades as $reportgrade) {
            $reportgrade = (object) $reportgrade;
            $key = $this->mod->id . '-' . $reportgrade->subjectsanitised  . '-' . $username;
            $grade = $reportgrade->subjectsanitised == 'engagement' ? $reportgrade->gradelang : $reportgrade->grade;
            if (empty($grade)) {
                $this->log("Grade empty for psgrading id {$this->mod->id} / subject {$reportgrade->subjectsanitised} / user {$username}", 2);
                continue;
            }
            $this->log("Caching grade for psgrading id {$this->mod->id} / subject {$reportgrade->subjectsanitised} / user {$username}", 2);
            $gradeobj = new \stdClass();
            $gradeobj->courseid = $this->mod->course;
            $gradeobj->username	= $username;
            $gradeobj->psgradingid = $this->mod->id;
            $gradeobj->externalreportid = 0;
            $gradeobj->subject = strtolower($reportgrade->subjectsanitised);
            $gradeobj->grade = $grade;
            $this->grades[$key] = $gradeobj;
        }
        
    }

    /**
     * Save the grades for this course to the db.
     *
     */
    protected function save_grades() {
        global $DB;

        foreach ($this->grades as $key => $grade) {
            // Check if the grade already staged.
            $params = array(
                'psgradingid' => $grade->psgradingid,
                'subject' => $grade->subject,
                'username' => $grade->username,
            );
            if ($stagedgrade = $DB->get_record('psgrading_gradesync', $params)) {
                // Update the existing record.
                $grade->id = $stagedgrade->id;
                $DB->update_record('psgrading_gradesync', $grade);
                $this->log("Updated grade {$grade->psgradingid}/{$grade->subject} for {$grade->username}", 2);
            } else {
                // Insert a new grade.
                $DB->insert_record('psgrading_gradesync', $grade);
                $this->log("Inserted grade {$grade->psgradingid}/{$grade->subject} for {$grade->username}", 2);
            }
            unset($this->existinggrades[$key]);
        }
    }

    /**
     * Delete old grades.
     *
     */
    protected function delete_grades() {
        global $DB;

        foreach ($this->existinggrades as $grade) {
            if ($grade->id) {
                $DB->delete_records('psgrading_gradesync', array('id' => $grade->id));
                $this->log("Deleted old grade {$grade->psgradingid}/{$grade->subject} for {$grade->username}", 2);
            }
        }
    }

}