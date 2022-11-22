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
 * For non-staff - an overview for a single student including all tasks from all activities in the course. 
 * Also the students landing page when a single ps grading instance is accessed.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use \mod_psgrading\external\overview_exporter;
use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;

// Course_module ID, or module instance id.
$courseid = optional_param('courseid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$nav = optional_param('nav', '', PARAM_RAW);
$viewas = optional_param('viewas', '', PARAM_RAW);
$reporting = optional_param('reporting', 1, PARAM_INT);

if ($courseid) {
    $course         = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

require_login($course, true);

$overviewurl = new moodle_url('/mod/psgrading/studentoverview.php', array(
    'courseid' => $courseid,
    'groupid' => $groupid,
    'userid' => $userid,
    'nav' => $nav,
    'reporting' => $reporting,
));
$listurl = new moodle_url('/mod/psgrading/courseoverview.php', array(
    'courseid' => $courseid,
));

$coursecontext = context_course::instance($courseid);
$PAGE->set_context($coursecontext);
$PAGE->set_url($overviewurl);
$PAGE->set_title(format_string($course->shortname) . ' Student Grades');
$PAGE->set_heading(format_string($course->shortname) . ' Student Grades');

// Get groups in the course.
//$groups = utils::get_users_course_groups($USER->id, $courseid);
$groups = utils::get_course_groups($courseid);

// If group is not specified, check if preference is set.
if (empty($groupid) && $nav != 'all') {
    $groupid = intval(utils::get_user_preferences($courseid, 'mod_psgrading_groupid', 0));
    if ($groupid) {
        $overviewurl->param('groupid', $groupid);
        $PAGE->set_url($overviewurl);
    }
} else {
    utils::set_user_preference($courseid, 'mod_psgrading_groupid', $groupid);
}

// Get the students in the course.
if (empty($groupid)) {
    // Groupid = 0, get all students in course.
    $students = utils::get_filtered_students($course->id, $userid);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid, $userid);
}
if (empty($students)) {
    // If no students and also not a grader (teacher in course) then the user is not going to be able to see anything.
    $isstaff = utils::is_grader();
    if (!$isstaff) {
        $courseurl = new moodle_url('/course/view.php', array(
            'id' => $courseid,
        ));
        $notice = \core\notification::error('Access Primary School Grading module denied because you are neither a parent, student, nor a teacher in this course.');
        redirect(
            $courseurl->out(false),
            $notice,
            null,
            \core\output\notification::NOTIFY_ERROR
        );
        exit;
    } 
    redirect($listurl->out(false));
    exit;
}

// Set a default user.
if (empty($userid) || (!in_array($userid, $students))) {
    $userid = $students[0];
    $overviewurl->param('userid', $userid);
    $PAGE->set_url($overviewurl);
}

// Export the data for this page.
$relateds = array(
    'courseid' => (int) $courseid,
    'groups' => $groups,
    'students' => $students,
    'userid' => $userid,
    'groupid' => $groupid,
    'isstaff' => $viewas ? false : utils::is_grader(),
    'reportingperiod' => $reporting,
);
$overviewexporter = new overview_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $overviewexporter->export($output);
if (empty($data->tasks)) {
    //echo "TODO: No graded tasks for this user."; exit;
}
// Make some adjustments if viewing as.
if ($viewas) {
    $data->isstaff = utils::is_grader();
    $data->viewas = $viewas;
}

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

$output = $OUTPUT->header();
$output .= $OUTPUT->render_from_template('mod_psgrading/overview', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/overview', 'init');

$output .= $OUTPUT->footer();
echo $output;
