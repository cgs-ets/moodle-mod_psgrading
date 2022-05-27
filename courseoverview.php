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
 * For staff - shows an overview for the whole course, including all psgrading instances, tasks and students.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_psgrading\persistents\task;
use mod_psgrading\external\course_exporter;
use mod_psgrading\utils;

// Course ID
$courseid = optional_param('courseid', 0, PARAM_INT);

// Custom params.
$reporting = optional_param('reporting', 1, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$nav = optional_param('nav', '', PARAM_RAW);
$refresh = optional_param('refresh', 0, PARAM_INT);

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

$coursecontext = context_course::instance($course->id);
require_login($course, true);

// If a non-staff, redirect them to the studentoverview page instead.
$isstaff = utils::is_grader();
if (!$isstaff) {
	$url = new moodle_url('/mod/psgrading/studentoverview.php', array('courseid' => $course->id));
	redirect($url->out(false));
	exit;
} 

$courseoverviewurl = new moodle_url('/mod/psgrading/courseoverview.php', array(
    'courseid' => $course->id,
    'reporting' => $reporting,
    'groupid' => $groupid,
    'nav' => $nav,
));

$PAGE->set_url($courseoverviewurl);
$title = format_string($course->fullname) . ' Grading Overview';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_context($coursecontext);
$PAGE->add_body_class('psgrading-overview-page');

// Get groups in the course.
$groups = utils::get_course_groups($courseid);
// If group is not specified, check if preference is set.
if (empty($groupid) && $nav != 'all') {
    // custom pref db as the pref needs to be per cm instance.
    $groupid = intval(utils::get_user_preferences($courseid, 'mod_psgrading_course_groupid', 0));
    if ($groupid) {
        $courseoverviewurl->param('groupid', $groupid);
        $PAGE->set_url($courseoverviewurl);
    }
} else {
    utils::set_user_preference($courseid, 'mod_psgrading_course_groupid', $groupid);
}

if ($refresh) {
    utils::invalidate_cache($courseid, 'list-course-' . $reporting . '-' . $groupid);
	redirect($courseoverviewurl->out(false));
	exit;
}

// Get the students in the course.
if (empty($groupid)) {
    // Groupid = 0, get all students in course.
    $students = utils::get_filtered_students($course->id);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid);
}
if (empty($students)) {
    if ($groupid) {
        // Try redirecting to top.
        $courseoverviewurl->param('groupid', 0);
        $courseoverviewurl->param('nav', 'all'); 
        redirect($courseoverviewurl->out(false));
    }
    echo "No students in course";
    exit;
}

$relateds = array(
    'courseid' => (int) $course->id,
    'reportingperiod' => $reporting,
    'groups' => $groups,
    'groupid' => $groupid,
    'students' => $students,
);
$listexporter = new course_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $listexporter->export($output);

//echo "<pre>"; var_export($data); exit; 

// Add css and vendor js.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));
// Maybe do not allow sorting as this is something that should happen in instance context??
//$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/Sortable.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/dragscroll.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/listjs/1.5.0/list.min.js'), true );

$output = $OUTPUT->header();

// Render the announcement list.
$output .= $OUTPUT->render_from_template('mod_psgrading/list', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/classlist', 'init');

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;
