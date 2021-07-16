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
 * Prints an instance of mod_psgrading.
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

if ($id) {
    $cm             = get_coursemodule_from_id('psgrading', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('psgrading', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('psgrading', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

// If a non-staff, redirect them to the overview page instead.
$isstaff = utils::is_cgs_staff();
if (!$isstaff) {
	$url = new moodle_url('/mod/psgrading/overview.php', array('cmid' => $cm->id));
	redirect($url->out(false));
	exit;
} 

$viewurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
    'groupid' => $groupid,
    'nav' => $nav,
));

$coursecontext = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/psgrading:addinstance', $coursecontext, $USER->id); 

$PAGE->set_url($viewurl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);


// Get groups in the course.
$groups = utils::get_course_groups($course->id);
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
    $students = utils::get_filtered_students($course->id);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid);
}
if (empty($students)) {
    if ($groupid) {
        // Try redirecting to top.
        $viewurl->param('groupid', 0);
        $viewurl->param('nav', 'all'); 
        redirect($viewurl->out(false));
    }
    echo "No students in course";
    exit;
}

// Get the tasks.
$taskdata = task::get_for_coursemodule($cm->id);
$relateds = array(
    'cmid' => (int) $cm->id,
    'groups' => $groups,
    'groupid' => $groupid,
    'students' => $students,
	'tasks' => $taskdata,
);
$listexporter = new list_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $listexporter->export($output);

// Add css and vendor js.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/Sortable.min.js'), true );

$output = $OUTPUT->header();

// Render the announcement list.
$output .= $OUTPUT->render_from_template('mod_psgrading/list', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/list', 'init');

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;
