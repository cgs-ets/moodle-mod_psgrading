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
    protected $courseid = null;

    /**
     * @var stdClass The reporting period.
     */
    protected $reportingperiod = null;

    /**
     * @var array Existing staged grades.
     */
    protected $existinggrades = [];

    /**
     * @var array Grades to be stored.
     */
    protected $grades = [];

    /**
     * @var moodle_database.
     */
    protected $externaldb = null;

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
        return get_string('crontask_gradesync', 'mod_psgrading');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $this->courseid = $data[0];
        $this->reportingperiod = $data[1];
        $this->log_start("Processing grade sync for course {$this->courseid}");

        $course = $DB->get_record('course', ['id' => $this->courseid]);
        if (empty($course)) {
            $this->log("Error - course record not found.", 1);
            return;
        }
        $this->log("Course record found: $course->fullname", 1);

        // Load in all of the existing staged grades for this course.
        $this->log("Caching existing grades for this course.", 1);
        $this->cache_existing_grades();

        // Get/compute the grades for each student.
        $students = utils::get_enrolled_students($this->courseid, []);
        foreach ($students as $studentid) {
            $this->get_grades($studentid);
        }

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
                 WHERE courseid = ?
                   AND reportingperiod = ?";
        $grades = $DB->get_records_sql($sql, [$this->courseid, $this->reportingperiod]);
        foreach ($grades as $grade) {
            $key = "{$grade->fileyear}-{$grade->reportingperiod}-{$grade->courseid}-{$grade->subject}-{$grade->username}";
            $this->log("Caching existing staged grade: {$grade->fileyear}-{$grade->reportingperiod}-{$grade->courseid}-{$grade->subject}-{$grade->username}", 2);
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
        $relateds = [
            'courseid' => (int) $this->courseid,
            'userid' => $studentid,
            'isstaff' => true, // Only staff can view the report grades.
            'includehiddentasks' => true,
            'reportingperiod' => (int) $this->reportingperiod,
        ];

        $gradeexporter = new grade_exporter(null, $relateds);
        $output = $PAGE->get_renderer('core');
        $gradedata = $gradeexporter->export($output); // --> Here it will call the get_other_values function.

        if (empty($gradedata->reportgrades)) {
            return;
        }

        $username = $DB->get_field('user', 'username', ['id' => $studentid]);

        foreach($gradedata->reportgrades as $reportgrade) {
            $reportgrade = (object) $reportgrade;
            $fileyear = date("Y");
            $subject = strtolower($reportgrade->subjectsanitised);
            $key = "{$fileyear}-{$this->reportingperiod}-{$this->courseid}-{$subject}-{$username}";
            //  $grade = $subject == 'engagement' ? $reportgrade->gradelang : $reportgrade->grade;
            if ($subject == 'engagement') continue; // This is treated in the second foreach.
            $grade =  $reportgrade->grade;
            if (empty($grade)) {
                $this->log("Grade empty for course id {$this->courseid} / subject {$subject} / user {$username}", 2);
                continue;
            }
            $this->log("Caching grade: {$fileyear}-{$this->reportingperiod}-{$this->courseid}-{$subject}-{$username}", 2);
            $gradeobj = new \stdClass();
            $gradeobj->courseid = $this->courseid;
            $gradeobj->username    = $username;
            $gradeobj->fileyear = $fileyear;
            $gradeobj->reportingperiod = (int) $this->reportingperiod;
            $gradeobj->subject = $subject;
            $gradeobj->grade = $grade;
            $gradeobj->type = 'grade';
            $this->grades[$key] = $gradeobj;
        }

        foreach($gradedata->reportengagementgrades as $reportengagementgrade) {
            $reportengagementgrade = (object) $reportengagementgrade;


            $fileyear = date("Y");
            $subject = strtolower($reportengagementgrade->subjectsanitised);
            $engkey = "{$fileyear}-{$this->reportingperiod}-{$this->courseid}-{$subject}-{$username}";
            $grade = $reportengagementgrade->grade;
            if (empty($grade)) {
                $this->log("Grade empty for course id {$this->courseid} / subject {$subject} / user {$username}", 2);
                continue;
            }
            $this->log("Caching grade: {$fileyear}-{$this->reportingperiod}-{$this->courseid}-{$subject}-{$username}", 2);
            $gradeengobj = new \stdClass();
            $gradeengobj->courseid = $this->courseid;
            $gradeengobj->username    = $username;
            $gradeengobj->fileyear = $fileyear;
            $gradeengobj->reportingperiod = (int) $this->reportingperiod;
            $gradeengobj->subject = $subject;
            $gradeengobj->grade = $grade;
            $gradeengobj->type = 'effort';
            $t = json_encode($gradeengobj);
                $this->log("Caching grade: {$t}", 2);

            $this->grades[$engkey] = $gradeengobj;
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
            $params = [
                'fileyear' => $grade->fileyear,
                'reportingperiod' => $grade->reportingperiod,
                'courseid' => $grade->courseid,
                'subject' => $grade->subject,
                'username' => $grade->username,
            ];
            if ($stagedgrade = $DB->get_record('psgrading_gradesync', $params)) {
                // Update the existing record.
                $grade->id = $stagedgrade->id;
                $DB->update_record('psgrading_gradesync', $grade);
                $this->log("Updated grade {$grade->fileyear}/{$grade->reportingperiod}/{$grade->subject} for {$grade->username}", 2);
            } else {
                // Insert a new grade.
                $DB->insert_record('psgrading_gradesync', $grade);
                $this->log("Inserted grade {$grade->fileyear}/{$grade->reportingperiod}/{$grade->subject} for {$grade->username}", 2);
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
                $DB->delete_records('psgrading_gradesync', ['id' => $grade->id]);
                $this->log("Deleted old grade {$grade->fileyear}/{$grade->reportingperiod}/{$grade->subject} for {$grade->username}", 2);
            }
        }
    }

}
