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
use mod_psgrading\external\manage_exporter;
use mod_psgrading\utils;

// Course_module ID
$cmid = required_param('cmid', PARAM_INT);

$cm             = get_coursemodule_from_id('psgrading', $cmid, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('psgrading', array('id' => $cm->instance), '*', MUST_EXIST);

$coursecontext = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/psgrading:addinstance', $coursecontext, $USER->id); 

// If a non-staff, redirect them to the overview page instead.
$isstaff = utils::is_grader();
if (!$isstaff) {
	$url = new moodle_url('/mod/psgrading/overview.php', array('cmid' => $cm->id));
	redirect($url->out(false));
	exit;
} 

$PAGE->set_url('/mod/psgrading/manage.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));
// Add vendor js.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/Sortable.min.js'), true );

$output = $OUTPUT->header();

$taskdata = task::get_for_coursemodule($cm->id);
$relateds = array(
    'cmid' => $cm->id,
	'tasks' => $taskdata,
);
$manageexporter = new manage_exporter(null, $relateds);
$data = $manageexporter->export($OUTPUT);

// Render the task list.
$output .= $OUTPUT->render_from_template('mod_psgrading/manage', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/manage', 'init');

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;