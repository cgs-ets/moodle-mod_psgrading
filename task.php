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

use \mod_psgrading\forms\form_task;
use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;
use \mod_psgrading\external\task_exporter;

// Course_module ID, or module instance id.
$cmid = required_param('cmid', PARAM_INT);

//$create = optional_param('create', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);

if ($cmid) {
    $cm             = get_coursemodule_from_id('psgrading', $cmid, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', array('id' => $cm->instance), '*', MUST_EXIST);
}

require_login($course, true, $cm);

$editurl = new moodle_url('/mod/psgrading/task.php', array(
    'cmid' => $cm->id,
    'edit' => $edit,
));
$listurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($editurl);
$title = get_string('tasksetup', 'mod_psgrading');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$task = new task($edit);

// Check task exists.
if (!empty($edit)) { 
    $exists = task::record_exists($edit);
    if (!$exists || $task->get('deleted')) {
        redirect($listurl->out(false));
        exit;
    }
}
        
// Instantiate the form.
$formtask = new form_task($editurl->out(false), array(),'post', '', []);

$formdata = $formtask->get_data();

// Check whether loading page or submitting page.
if (empty($formdata)) { // loading page for edit (not submitted).

    // Export the task.
    $taskexporter = new task_exporter($task);
    $output = $PAGE->get_renderer('core');
    $exported = $taskexporter->export($output);
    if ($exported->hasgrades) {
        $message = get_string('taskalreadygraded', 'mod_psgrading');
        \core\notification::error($message);
    }

    // Get existing task data.
    $taskname = $task->get('taskname');
    $pypuoi = $task->get('pypuoi');
    $outcomes = $task->get('outcomes');
    $criterionjson = $task->get('criterionjson');
    $evidencejson = $task->get('evidencejson');
    $published = $task->get('published');
    $notestext = $task->get('notes');

    // Course activities that can be selected as evidence.
    $evidencedata = utils::get_evidencedata($course, $evidencejson);

    // Get and decorate criterion data.
    $criteriondata = json_decode($criterionjson);
    if (empty($criteriondata)) {
        $criteriondata = array(utils::get_stub_criterion()); // Add a default empty criterion.
    }
    $criteriondata = utils::decorate_subjectdata($criteriondata);
    $criteriondata = utils::decorate_weightdata($criteriondata);

    // Reinstantiate the form with needed data.
    $formtask = new form_task($editurl->out(false), 
        array(
            'edit' => $edit,
            'criteriondata' => $criteriondata,
            'evidencedata' => $evidencedata,
            'published' => $published,
            'enableweights' => $moduleinstance->enableweights,
        ), 
        'post', '', array('data-form' => 'psgrading-task')
    );

    // Set up notes editor.
    $draftideditor = file_get_submitted_draft_itemid('notes');
    $editoroptions = form_task::editor_options();
    $notestext = file_prepare_draft_area($draftideditor, $modulecontext->id, 'mod_psgrading', 'notes', $edit, $editoroptions, $notestext);
    $notes = array(
        'text' => $notestext,
        'format' => editors_get_preferred_format(),
        'itemid' => $draftideditor
    );

    // Set the form values.
    $formtask->set_data(
        array(
            'general' => '',//$edit ? get_string('task:edit', 'mod_psgrading') : get_string('task:create', 'mod_psgrading'),
            'edit' => $edit,
            'taskname' => $taskname,
            'pypuoi' => $pypuoi,
            'outcomes' => $outcomes,
            'published' => $published,
            'criterionjson' => $criterionjson,
            'evidencejson' => $evidencejson,
            'notes' => $notes,
        )
    );

    // Run get_data again to trigger validation and set errors.
    $formdata = $formtask->get_data();

} else {
    // Invalidate list html cache.
    utils::invalidate_cache($cm->id, 'list-%');

    if ($formdata->action == 'cancel') {
        redirect($listurl->out());
        exit;
    }

    if ($formdata->action == 'delete') {
        task::soft_delete($edit);
        redirect($listurl->out());
        exit;
    }

    if ($formdata->action == 'save') {
        $result = task::save_from_data($edit, $cm->id, $formdata);
        if ($result) {
            $notice = get_string("task:savesuccess", "mod_psgrading");
            redirect(
                $listurl->out(),
                '<p>'.$notice.'</p>',
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $notice = get_string("task:savefail", "mod_psgrading");
            redirect(
                $listurl->out(),
                '<p>'.$notice.'</p>',
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
    
}

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));
// Add vendor js.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/Sortable.min.js'), true );

echo $OUTPUT->header();

$formtask->display();

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/task', 'init');

echo $OUTPUT->footer();


exit;
