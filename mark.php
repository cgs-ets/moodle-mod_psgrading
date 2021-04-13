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
 * A student portfolio tool for CGS.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */


require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_psgrading\external\mark_exporter;
use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;

// Course_module ID, or module instance id.
$cmid = optional_param('cmid', 0, PARAM_INT);
$p  = optional_param('p', 0, PARAM_INT);

$taskid = required_param('taskid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if ($cmid) {
    $cm             = get_coursemodule_from_id('psgrading', $cmid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('psgrading', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('psgrading', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

require_login($course, true, $cm);

$markurl = new moodle_url('/mod/psgrading/mark.php', array(
    'cmid' => $cm->id,
    'taskid' => $taskid,
    'userid' => $userid,
));
$viewurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($markurl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));

// Load existing task.
$exists = task::record_exists($taskid);
if ($exists) {
    $task = new task($taskid);
}

if (!$exists || $task->get('deleted')) {
    redirect($viewurl->out(false));
    exit;
}

// Get the students in the course.
$students = utils::get_enrolled_students($course->id);
if (empty($students)) {
    redirect($viewurl->out(false));
    exit;
}

// Set a default user for marking.
if (empty($userid)) {
    $userid = $students[0];
    $markurl->param('userid', $userid);
    $PAGE->set_url($markurl);
}

// Add required styles and scripts.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

// Start building page output.
$output = $OUTPUT->header();

// Export the data for the template using the course students and task marking.
$relateds = array(
    'task' => $task,
    'students' => $students,
    'userid' => $userid,
    'usermarks' => null,
    'markurl' => $markurl,
);
$markexporter = new mark_exporter(null, $relateds);
$data = $markexporter->export($OUTPUT);

// Render the template.
$output .= $OUTPUT->render_from_template('mod_psgrading/mark', $data);

// Add amd scripts.
$PAGE->requires->js_call_amd('mod_psgrading/mark', 'init', array(
    'userid' => $userid,
    'taskid' => $taskid,
));

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;




