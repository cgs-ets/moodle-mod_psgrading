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

use \mod_psgrading\forms\form_task;
use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;

// Course_module ID, or module instance id.
$cmid = optional_param('cmid', 0, PARAM_INT);
$p  = optional_param('p', 0, PARAM_INT);

$create = optional_param('create', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);

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

$modulecontext = context_module::instance($cm->id);

$viewurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));
$taskediturl = new moodle_url('/mod/psgrading/tasks.php', array(
    'cmid' => $cm->id,
    'edit' => $edit,
));

$PAGE->set_url($taskediturl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
//$PAGE->navbar->add(get_string('title', 'mod_psgrading'), $taskediturl);


// Determine if creating new / editing / or viewing list.
if ($create) {
    // Create a new empty task.
    $data = new \stdClass();
    $data->creatorusername = $USER->username;
    $data->cmid = $cm->id;
    $task = new task(0, $data);
    $task->save();

    // Redirect to edit.
    $taskediturl->param('edit', $task->get('id'));
    redirect($taskediturl->out(false));
    exit;

} elseif ($edit) {
    $title = get_string('tasksetup', 'mod_psgrading');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    // Load existing task.
    $exists = task::record_exists($edit);
    if ($exists) {
        $task = new task($edit);
    }

    if (!$exists || $task->get('deleted')) {
        redirect($viewurl->out(false));
        exit;
    }

    // Instantiate the form.
    $formtask = new form_task(
        $taskediturl->out(false), 
        array('rubricdata' => [], 'evidencedata' => [], 'published' => 0),
        'post', '', []
    );
    $formdata = $formtask->get_data();

    // Check whether loading page or submitting page.
    if (empty($formdata)) {
        // Editing (not submitted).

        //echo "<pre>"; var_export($activities); var_export(array_column($activities, 'name')); exit;

        // Get existing task data.
        $taskname = $task->get('taskname');
        $pypuoi = $task->get('pypuoi');
        $outcomes = $task->get('outcomes');
        $rubricjson = $task->get('rubricjson');
        $evidencejson = $task->get('evidencejson');
        $published = $task->get('published');

        // Override with draft data if it is present.
        if ($draft = $task->get('draftjson')) {
            $draft = json_decode($draft);
            $taskname = $draft->taskname ? $draft->taskname : $taskname;
            $pypuoi = $draft->pypuoi ? $draft->pypuoi : $pypuoi;
            $outcomes = $draft->outcomes ? $draft->outcomes : $outcomes;
            $rubricjson = $draft->rubricjson ? $draft->rubricjson : $rubricjson;
            $evidencejson = $draft->evidencejson ? $draft->evidencejson : $evidencejson;
        }

        // Course activities that can be selected as evidence.
        $evidencedata = utils::get_evidencedata($course, $evidencejson);

        // Get and decorate criterion data.
        $rubricdata = json_decode($rubricjson);
        if (empty($rubricdata)) {
            $rubricdata = [utils::get_stub_criterion()]; // Add a default empty criterion.
        }
        $rubricdata = utils::decorate_subjectdata($rubricdata);

        // Reinstantiate the form with needed data.
        $formtask = new form_task($taskediturl->out(false), 
            array(
                'rubricdata' => $rubricdata,
                'evidencedata' => $evidencedata,
                'published' => $published,
            ), 
            'post', '', array('data-form' => 'psgrading-task')
        );

        // Set the form values.
        $formtask->set_data(
            array(
                'general' => get_string('taskform:create', 'mod_psgrading'),
                'edit' => $edit,
                'taskname' => $taskname,
                'pypuoi' => $pypuoi,
                'outcomes' => $outcomes,
                'rubricjson' => $rubricjson,
                'evidencejson' => $evidencejson,
            )
        );
    } else {
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
            $data->rubricjson = $formdata->rubricjson;

            $result = task::save_from_data($data);
            if ($result) {
                $notice = get_string("taskform:createsuccess", "mod_psgrading");
                if ($edit) {
                    $notice = get_string("taskform:editsuccess", "mod_psgrading");
                }
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

    $formtask->display();

    // Add scripts.
    $PAGE->requires->js_call_amd('mod_psgrading/taskform', 'init');

    echo $OUTPUT->footer();

}
exit;



