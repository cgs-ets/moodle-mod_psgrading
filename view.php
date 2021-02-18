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

use \mod_psgrading\persistents\task;
use \mod_psgrading\external\task_exporter;

// Course_module ID, or
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$p  = optional_param('p', 0, PARAM_INT);

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

$coursecontext = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/psgrading:addinstance', $coursecontext, $USER->id); 

$PAGE->set_url('/mod/psgrading/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$taskcreateurl = new moodle_url('/mod/psgrading/tasks.php', array(
    'cmid' => $cm->id,
    'create' => 1,
));

$corerenderer = $PAGE->get_renderer('core');
$taskdata = task::get_for_coursemodule($cm->id);
$tasks = array();
foreach ($taskdata as $task) {
	$taskexporter = new task_exporter($task);
	$tasks[] = $taskexporter->export($corerenderer);
}
//echo "<pre>"; var_export($tasks); exit;

$data = array(
	'tasks' => $tasks,
	'taskcreateurl' => $taskcreateurl,
);

$output = $OUTPUT->header();

// Render the announcement list.
$output .= $OUTPUT->render_from_template('mod_psgrading/view', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

