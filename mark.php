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

use \mod_psgrading\forms\form_mark;
use \mod_psgrading\external\mark_exporter;
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
$listurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($markurl);
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

// Get the students in the course.
$students = utils::get_enrolled_students($course->id);
if (empty($students)) {
    redirect($listurl->out(false));
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
$output = $PAGE->get_renderer('core');
$data = $markexporter->export($output);

// Add task edit, and overview to nav.
$PAGE->navbar->add($data->task->taskname, $data->task->editurl);
$PAGE->navbar->add($data->currstudent->fullname, $data->currstudent->overviewurl);

// Instantiate empty form so that we can "get_data" with minimal processing.
$formmark = new form_mark($markurl->out(false), array('data' => []), 'post', '', []);
$formdata = $formmark->get_data();
if (empty($formdata)) { 
    // Editing (not submitted).
    // Set up draft evidences file manager.
    $draftevidence = file_get_submitted_draft_itemid('evidences');
    $evidenceoptions = form_mark::evidence_options();
    $uniqueid = sprintf( "%d%d", $taskid, $userid ); // Join the taskid and userid to make a unique itemid.
    file_prepare_draft_area($draftevidence, $modulecontext->id, 'mod_psgrading', 
        'evidences', $uniqueid, $evidenceoptions);

    // Reinstantiate the form with the data.
    $formmark = new form_mark($markurl->out(false), array('data' => $data),'post', '', array('data-form' => 'psgrading-mark'));

    // Set the form values.
    $formmark->set_data(array(
        'evidences' => $draftevidence,
        'engagement' => isset($data->gradeinfo->engagement) ? $data->gradeinfo->engagement : '',
        'comment' => isset($data->gradeinfo->comment) ? $data->gradeinfo->comment : '',
    ));

    // Run get_data again to trigger validation and set errors.
    $formdata = $formmark->get_data();

} else {
    // Add some goodies to the submitted data.
    $formdata->taskid = $taskid;
    $formdata->userid = $userid;
    // The form was submitted.
    if ($formdata->action == 'save' || $formdata->action == 'saveshownext') {
        $result = task::save_task_grades_for_student($formdata);

        if ($result) {
            $redirecturl = $listurl->out();
            if ($formdata->action == 'saveshownext') {
                $redirecturl = $data->nextstudenturl;
            }
            $notice = get_string("mark:savesuccess", "mod_psgrading", $data->currstudent->fullname);
            redirect(
                $redirecturl,
                $notice,
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $notice = get_string("mark:savefail", "mod_psgrading", $data->currstudent->fullname);
            redirect(
                $markurl->out(),
                $notice,
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    if ($formdata->action == 'reset') {
        task::reset_task_grades_for_student($formdata);
        $notice = get_string("mark:resetsuccess", "mod_psgrading", $data->currstudent->fullname);
        redirect(
            $markurl->out(),
            $notice,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    
}











// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_psgrading/mark_header', $data);

$formmark->display();

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/mark', 'init', array(
    'userid' => $userid,
    'taskid' => $taskid,
));

echo $OUTPUT->footer();
