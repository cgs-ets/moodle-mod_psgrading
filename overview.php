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

use \mod_psgrading\external\overview_exporter;
use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;

// Course_module ID, or module instance id.
$cmid = optional_param('cmid', 0, PARAM_INT);
$p  = optional_param('p', 0, PARAM_INT);
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

$overviewurl = new moodle_url('/mod/psgrading/overview.php', array(
    'cmid' => $cm->id,
    'userid' => $userid,
));
$listurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($overviewurl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));

// Get the students in the course.
$students = utils::get_filtered_students($course->id, $userid);
if (empty($students)) {
    redirect($listurl->out(false));
    exit;
}

// Set a default user.
if (empty($userid)) {
    $userid = $students[0];
    $overviewurl->param('userid', $userid);
    $PAGE->set_url($overviewurl);
}

// Export the data for this page.
$relateds = array(
    'cmid' => (int) $cm->id,
    'students' => $students,
    'userid' => $userid,
    'overviewurl' => $overviewurl,
    'isstaff' => utils::is_cgs_staff(),
);
$overviewexporter = new overview_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $overviewexporter->export($output);

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

$output = $OUTPUT->header();

//echo "<h3>Render me!</h3>";
//echo "<pre>";
//var_export($data);
//exit;

$output .= $OUTPUT->render_from_template('mod_psgrading/overview', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/overview', 'init');

$output .= $OUTPUT->footer();
echo $output;
