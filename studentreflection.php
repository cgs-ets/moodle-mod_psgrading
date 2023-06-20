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
// https://moodle.local/mod/psgrading/reporting.php?courseid=13

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use \mod_psgrading\utils;
use \mod_psgrading\reporting;
use \mod_psgrading\forms\form_reflection;

// Course ID
$courseid = required_param('courseid', PARAM_INT);
$year = required_param('year', PARAM_INT);
$period = required_param('period', PARAM_INT);
$username = required_param('user', PARAM_INT);
$type = optional_param('type', 'image', PARAM_TEXT);

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

$passedp1 = time() > strtotime( $year . '-07-15' );

// Lock previous periods.
$locked = false;
if ($year < date('Y')) {
    $locked = true;
} else if ( $period == 1 && $passedp1 ) {
    $locked = true;
}

if ($locked) {
    exit;
}

$coursecontext = context_course::instance($course->id);
require_login($course, true);

if (!utils::is_grader()) {
    exit;
}

$url = new moodle_url('/mod/psgrading/studentreflection.php', array(
    'courseid' => $course->id,
    'year' => $year,
    'period' => $period,
    'user' => $username,
    'type' => $type,
));
$PAGE->set_url($url);
$title = 'Student Reflection';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_context($coursecontext);

$reportingurl = new moodle_url('/mod/psgrading/reporting.php', array(
    'courseid' => $course->id,
    'year' => $year,
    'period' => $period,
));

// Check to see if the page was submitted for a student reflection before continuing.
$formreflection = new form_reflection(
    $url->out(false), 
    array('type' => $type), 
    'post', 
    '', 
    array('data-form' => 'psgrading-studentreflection')
);

if ($formreflection->is_cancelled()) {
    redirect($reportingurl->out());
    exit;
}
$formdata = $formreflection->get_data();
if (!empty($formdata)) {
    if ($formdata->action == 'save') {
        if ($type == 'editor') {
            reporting::save_reportelement_editor($coursecontext, $course->id, $year, $period, $username, 'studentreflection', 'form', $formdata->reflection);
        } else {
            reporting::save_reportelement_form($coursecontext, $course->id, $year, $period, $username, 'studentreflection', 'form', $formdata);
        }
    }
    redirect($reportingurl->out());
    exit;
}

$user = \core_user::get_user_by_username($username);
if (!empty($user)) {
    utils::load_user_display_info($user);
}

// Load in existing reflection.
$conds = array (
    'courseid' => $course->id,
    'fileyear' => $year,
    'reportingperiod' => $period,
    'studentusername' => $username,
    'elementname' => 'studentreflection',
    'elementtype' => 'form',
);
if ($existing = $DB->get_record('psgrading_reporting', $conds, '*', IGNORE_MULTIPLE)) {
    if ($type == 'editor') {
        // Set up reflection editor.
        $draftideditor = file_get_submitted_draft_itemid('reflection');
        $editoroptions = form_reflection::editor_options();
        $reflectiontext = file_prepare_draft_area($draftideditor, $coursecontext->id, 'mod_psgrading', 'reflection', $year . $period . $user->id, $editoroptions, $existing->reflection);
        $reflection = array(
            'text' => $reflectiontext,
            'format' => editors_get_preferred_format(),
            'itemid' => $draftideditor
        );
        $formreflection->set_data(array('reflection' => $reflection));
    }
    
    if ($type == 'image') {
        // Set up draft image file manager.
        $draftimage = file_get_submitted_draft_itemid('reflectionimage');
        $imageoptions = form_reflection::image_options();
        $uniqueid = sprintf( "%d%d%d", $year, $period, $user->id );
        file_prepare_draft_area($draftimage, $coursecontext->id, 'mod_psgrading', 
            'reflectionimage', $uniqueid, $imageoptions);
        $formreflection->set_data(array(
            'reflection' => $existing->reflection,
            'reflectionimage' => $draftimage
        ));
    }
}

$data = array(
    'courseid' => $course->id,
    'year' => $year,
    'period' => $period,
    'user' => $user,
    'form' => $formreflection->render(),
    'reportingurl' => $reportingurl->out(false),
);

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_psgrading/studentreflection', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/studentreflection', 'init');

echo $OUTPUT->footer();