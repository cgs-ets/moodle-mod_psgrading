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
 * A framework for CGS's Primary School assessment grading model.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_psgrading\forms\form_mark;
use mod_psgrading\external\task_exporter;
use mod_psgrading\persistents\task;
use mod_psgrading\utils;

// Course_module ID, or module instance id.
$cmid = optional_param('cmid', 0, PARAM_INT);
$p  = optional_param('p', 0, PARAM_INT);

$taskid = required_param('taskid', PARAM_INT);

if ($cmid) {
    $cm             = get_coursemodule_from_id('psgrading', $cmid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('psgrading', ['id' => $n], '*', MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('psgrading', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

require_login($course, true, $cm);

$printurl = new moodle_url('/mod/psgrading/print.php', [
    'cmid' => $cm->id,
    'taskid' => $taskid,
]);

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($printurl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($moduleinstance->name));

// Load existing task.
$exists = task::record_exists($taskid);
if ($exists) {
    $task = new task($taskid);
    $title = $task->get('taskname') . ' (' . $task->get('pypuoi') . ')';
    $PAGE->set_title(format_string($title));
    $PAGE->set_heading(format_string($title));
}

if (!$exists || $task->get('deleted')) {
    redirect($listurl->out(false));
    exit;
}

// Export the task.
$taskexporter = new task_exporter($task);
$output = $PAGE->get_renderer('core');
$task = $taskexporter->export($output);


// Get and decorate criterion data.
$task->criterions = json_decode($task->criterionjson);
$task->criterions = utils::decorate_subjectdata($task->criterions);
$task->criterions = utils::decorate_weightdata($task->criterions);
$task->showmeta = true;

// Get and decorate engagement data.
$task->engagements = json_decode($task->engagementjson);
$task->engagements = utils::decorate_subjectdata($task->engagements);
$task->engagements = utils::decorate_weightdata($task->engagements);
$task->showmeta = true;

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', ['nocache' => rand()]));

$output = $OUTPUT->header();

// echo "<h3>Render me!</h3>";
// echo "<pre>";
// var_export($task);
// exit;

$output .= $OUTPUT->render_from_template('mod_psgrading/print', ['task' => $task]);

$output .= $OUTPUT->footer();
echo $output;


