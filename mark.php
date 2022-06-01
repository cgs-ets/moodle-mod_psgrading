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
$groupid = optional_param('groupid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$nav = optional_param('nav', '', PARAM_RAW);

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

$params = array(
    'cmid' => $cm->id,
    'taskid' => $taskid,
    'groupid' => $groupid,
    'userid' => $userid,
);
$detailsurl = new moodle_url('/mod/psgrading/details.php', $params);
if (!utils::is_grader()) {
    redirect($detailsurl->out(false));
    exit;
}

$markurl = new moodle_url('/mod/psgrading/mark.php', $params);
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

// Check if PS Grading activity is locked.
if ($moduleinstance->timelocked && $moduleinstance->timelocked < time()) {
    $message = get_string('activitylocked', 'mod_psgrading');
    \core\notification::error($message);
}

$groups = [];
// If there are restrictions do not offer group nav.
if (!$moduleinstance->restrictto) {
    // Get groups in the course.
    $groups = utils::get_course_groups($course->id);
}

// If group is not specified, check if preference is set.
if (empty($groupid) && $nav != 'all') {
    $groupid = intval(utils::get_user_preferences($cm->id, 'mod_psgrading_groupid', 0));
    if ($groupid) {
        $markurl->param('groupid', $groupid);
        $PAGE->set_url($markurl);
    }
} else {
    utils::set_user_preference($cm->id, 'mod_psgrading_groupid', $groupid);
}

// Get the students in the course.
if (empty($groupid)) {
    // Groupid = 0, get all students in course.
    $students = utils::get_filtered_students($course->id, $userid, $moduleinstance->restrictto);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid, $userid, $moduleinstance->restrictto);
}
if (empty($students)) {
    redirect($listurl->out(false));
    exit;
}

// Set a default user for marking.
if (empty($userid) || !in_array($userid, $students)) {
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
    'groups' => $groups,
    'groupid' => $groupid,
);
$markexporter = new mark_exporter(null, $relateds);
$output = $PAGE->get_renderer('core');
$data = $markexporter->export($output);


if ( ! $data->task->published) {
    $message = get_string('taskhidden', 'mod_psgrading');
    $notice = \core\notification::error($message);
    redirect(
        $listurl->out(false),
        $notice,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

//echo "<pre>"; var_export($data); exit;

// Add task edit, and overview to nav.
$PAGE->navbar->add($data->task->taskname, $data->task->editurl);
$PAGE->navbar->add($data->currstudent->fullname, $data->currstudent->overviewurl);

// Instantiate empty form so that we can "get_data" with minimal processing.
    //Cannot instantiate empty as data needed for replacegrader checkbox to register properly.
    //$formmark = new form_mark($markurl->out(false), array('data' => []), 'post', '', []);
// Instantiate the form with data.
$formmark = new form_mark($markurl->out(false), array('data' => $data),'post', '', array('data-form' => 'psgrading-mark'));
$formdata = $formmark->get_data();
if (empty($formdata)) {
    // Editing (not submitted).
    // Set up draft evidences file manager.
    $draftevidence = file_get_submitted_draft_itemid('evidences');
    $evidenceoptions = form_mark::evidence_options();
    $uniqueid = sprintf( "%d%d", $taskid, $userid ); // Join the taskid and userid to make a unique itemid.
    file_prepare_draft_area($draftevidence, $modulecontext->id, 'mod_psgrading', 
        'evidences', $uniqueid, $evidenceoptions);

    // Set the form values.
    $didnotsubmit = isset($data->gradeinfo->didnotsubmit) && $data->gradeinfo->didnotsubmit ? 1 : 0;
    $formmark->set_data(array(
        'evidences' => $draftevidence,
        'didnotsubmit' => $didnotsubmit,
        'engagement' => isset($data->gradeinfo->engagement) ? $data->gradeinfo->engagement : '',
        'comment' => isset($data->gradeinfo->comment) ? $data->gradeinfo->comment : '',
        'myconnectevidencejson' => $data->task->myconnectevidencejson,
        'selectedmyconnectjson' => $data->task->myconnectevidencejson,
    ));

    // Run get_data again to trigger validation and set errors.
    $formdata = $formmark->get_data();

    if ($didnotsubmit) {
      $PAGE->add_body_class('didnotsubmit');
    }

} else {
    // Add some goodies to the submitted data.
    $formdata->taskid = $taskid;
    $formdata->userid = $userid;
    $formdata->didnotsubmit = isset($formdata->didnotsubmit) ? 1 : 0;
    $formdata->replacegrader = isset($formdata->replacegrader) ? 1 : 0;

    // The form was submitted.
    if ($formdata->action == 'save' || $formdata->action == 'saveshownext') {
        $result = task::save_task_grades_for_student($formdata);
        if ($result) {
            $redirecturl = $data->currstudent->overviewurl;;
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

    if ($formdata->action == 'cancel') {
        redirect($listurl->out(false));
    }

    /*if ($formdata->action == 'reset') {
        task::reset_task_grades_for_student($formdata);
        $notice = get_string("mark:resetsuccess", "mod_psgrading", $data->currstudent->fullname);
        redirect(
            $markurl->out(),
            $notice,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }*/
    
}

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));
// Add vendor js.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/masonry.pkgd.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/psgrading/js/imagesloaded.pkgd.min.js'), true );

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_psgrading/myconnect_selector', array('formattedattachments' => $data->myconnectattachments));

echo $OUTPUT->render_from_template('mod_psgrading/mark_header', $data);

$formmark->display();

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/mark', 'init', array(
    'userid' => $userid,
    'taskid' => $taskid,
));

echo $OUTPUT->footer();
