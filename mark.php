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

use \mod_psgrading\forms\form_mark;
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

// Instantiate the form.
$formmark = new form_mark($markurl->out(false), array('data' => []), 'post', '', []);
$formdata = $formmark->get_data();

// Check whether loading page or submitting page.
if (empty($formdata)) { // Editing (not submitted).

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

    // Export the data.
    $relateds = array(
        'task' => $task,
        'students' => $students,
        'userid' => $userid,
        'markurl' => $markurl,
    );
    $markexporter = new mark_exporter(null, $relateds);
    $data = $markexporter->export($OUTPUT);

    // Reinstantiate the form with the data.
    $formmark = new form_mark($markurl->out(false), ['data' => $data],'post', '', array('data-form' => 'psgrading-mark'));

    // Set the form values.
    /*$formmark->set_data(
        array(
            //evidence filemanager...
        )
    );*/

    // Run get_data again to trigger validation and set errors.
    $formdata = $formmark->get_data();

} else {
    echo "<pre>"; var_export($formdata); exit;
    // The form was submitted.
    if ($formdata->action == 'savedraft') {
        redirect($viewurl->out());
        exit;
    }

    if ($formdata->action == 'discardchanges') {
        // If already published, remove draftjson.
        if ($task->get('published')) {
            $task->set('draftjson', '');
            $task->save();
        } else {
            $task->set('deleted', 1);
            $task->save();
        }

        // If not yet publised, delete the task.
        redirect($viewurl->out());
        exit;
    }

    if ($formdata->action == 'publish') {

        $data = new \stdClass();
        $data->id = $edit;
        $data->published = 1;
        $data->draftjson = '';
        $data->taskname = $formdata->taskname;
        $data->pypuoi = $formdata->pypuoi;
        $data->outcomes = $formdata->outcomes;
        $data->criterionjson = $formdata->criterionjson;
        $data->evidencejson = $formdata->evidencejson;

        $result = task::save_from_data($data);
        if ($result) {
            $notice = get_string("taskform:publishsuccess", "mod_psgrading");
            redirect(
                $viewurl->out(),
                '<p>'.$notice.'</p>',
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $notice = get_string("taskform:createfail", "mod_psgrading");
            redirect(
                $viewurl->out(),
                '<p>'.$notice.'</p>',
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
    
}











// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

echo $OUTPUT->header();

$formmark->display();

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/markform', 'init', array(
    'userid' => $userid,
    'taskid' => $taskid,
));

echo $OUTPUT->footer();
