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
 * For staff - Overview of a single ps grading instance, including only tasks in that instance and all students.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_psgrading\persistents\task;
use mod_psgrading\external\list_exporter;
use mod_psgrading\utils;

// Course_module ID, or
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$p  = optional_param('p', 0, PARAM_INT);

// Custom params.
$groupid = optional_param('groupid', 0, PARAM_INT);
$nav = optional_param('nav', '', PARAM_RAW);
$refresh = optional_param('refresh', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('psgrading', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('psgrading', ['id' => $n], '*', MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('psgrading', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

$coursecontext = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);
require_login($course, true, $cm);

// If a non-staff, redirect them to the studentoverview page instead.
$isstaff = utils::is_grader();
if (!$isstaff) {
    $url = new moodle_url('/mod/psgrading/studentoverview.php', ['courseid' => $course->id]);
    redirect($url->out(false));
    exit;
}

$viewurl = new moodle_url('/mod/psgrading/view.php', [
    'id' => $cm->id,
    'groupid' => $groupid,
    'nav' => $nav,
]);

if ($refresh) {
    utils::invalidate_cache($cm->id, 'list-' . $groupid);
    redirect($viewurl->out(false));
    exit;
}

$PAGE->set_url($viewurl);
$PAGE->set_title(format_string($moduleinstance->name));
// $PAGE->set_heading(format_string($course->fullname));
$PAGE->set_heading(format_string($moduleinstance->name));
$PAGE->set_context($modulecontext);
$PAGE->add_body_class('psgrading-overview-page');

$groups = [];
// If there are restrictions do not offer group nav.
if (!$moduleinstance->restrictto) {
    // Get groups in the course.
    $groups = utils::get_course_groups($course->id);
}
// If group is not specified, check if preference is set.
if (empty($groupid) && $nav != 'all') {
    // custom pref db as the pref needs to be per cm instance.
    $groupid = intval(utils::get_user_preferences($cm->id, 'mod_psgrading_groupid', 0));
    if ($groupid) {
        $viewurl->param('groupid', $groupid);
        $PAGE->set_url($viewurl);
    }
} else {
    utils::set_user_preference($cm->id, 'mod_psgrading_groupid', $groupid);
}

// Get the students in the course.
if (empty($groupid)) {
    // Groupid = 0, get all students in course.
    $students = utils::get_filtered_students($course->id, 0, $moduleinstance->restrictto, $moduleinstance->excludeusers);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid, 0, $moduleinstance->restrictto, $moduleinstance->excludeusers);
}
if (empty($students)) {
    if ($groupid) {
        // Try redirecting to top.
        $viewurl->param('groupid', 0);
        $viewurl->param('nav', 'all');
        redirect($viewurl->out(false));
    }
    // echo "No students in course";
    // exit;
}

// Get the tasks.
$relateds = [
    'courseid' => (int) $course->id,
    'cmid' => (int) $cm->id,
    'groups' => $groups,
    'groupid' => $groupid,
    'students' => $students,
    'moduleinstance' => $moduleinstance,
];
$listexporter = new list_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $listexporter->export($output);
// echo '<pre>';
// echo print_r($data, true);
// echo '</pre>'; exit;

// Add css and vendor js.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', ['nocache' => rand()]));
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/Sortable.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/dragscroll.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/listjs/1.5.0/list.min.js'), true );

$output = $OUTPUT->header();

// Render the overview list.
$output .= $OUTPUT->render_from_template('mod_psgrading/list', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/classlist', 'init');

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;
