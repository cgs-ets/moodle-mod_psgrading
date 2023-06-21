<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Debugger.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(__DIR__.'/lib.php');

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/mod/psgrading/debug.php');
$PAGE->set_title('mod_psgrading debugger');
$PAGE->set_heading('mod_psgrading debugger');

require_login();
require_capability('moodle/site:config', $context, $USER->id);

/*$task = new \mod_psgrading\task\cron_grade_release;
$task->execute();
exit;*/
/*
$task = new \mod_psgrading\task\adhoc_gradesync();
$task->set_custom_data([2318,1]);
$task->set_component('mod_psgrading');
$task->execute();
exit;
*/


use mod_psgrading\utils;
use mod_psgrading\external\grade_exporter;
use \mod_psgrading\persistents\task;
echo "<pre>";
// Use the grade exporter to get grades for this student.
/*$relateds = array(
    'courseid' => (int) 2318,
    'userid' => 9682,
    'isstaff' => true, // Only staff can view the report grades.
    'includehiddentasks' => true,
    'reportingperiod' => (int) 1,
);
$gradeexporter = new grade_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$gradedata = $gradeexporter->export($output);*/

$output = $PAGE->get_renderer('core');
$tasks = task::compute_grades_for_course(
    2318, //courseid
    9682, //userid
    true, //includehiddentasks
    true, //isstaff
    1, //reportingperiod
);
foreach ($tasks as $task) {
	foreach ($task->subjectgrades as $subjectgrade) {
		$subjectgrade = (object) $subjectgrade;
		if ($subjectgrade->subjectsanitised == 'Mathsmeasurementandgeometry' && $subjectgrade->grade > 0) {
			var_export($task->taskname);
			var_export($subjectgrade);
		}
	}
}

exit;
