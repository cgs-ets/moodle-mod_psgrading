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

use mod_psgrading\persistents\task;
use mod_psgrading\utils;

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
    'qm' => '1',
);
$detailsurl = new moodle_url('/mod/psgrading/details.php', $params);
if (!utils::is_grader()) {
    redirect($detailsurl->out(false));
    exit;
}

$quickmarkurl = new moodle_url('/mod/psgrading/quickmark.php', $params);
$markurl = new moodle_url('/mod/psgrading/mark.php', $params);
$listurl = new moodle_url('/mod/psgrading/view.php', array(
    'id' => $cm->id,
));

$modulecontext = context_module::instance($cm->id);
$PAGE->set_context($modulecontext);
$PAGE->set_url($quickmarkurl);
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
    $students = utils::get_filtered_students($course->id, $userid, $moduleinstance->restrictto, $moduleinstance->excludeusers);
} else {
    // Get by group.
    $students = utils::get_filtered_students_by_group($course->id, $groupid, $userid, $moduleinstance->restrictto, $moduleinstance->excludeusers);
}
if (empty($students)) {
    redirect($listurl->out(false));
    exit;
}

// Generate mark urls for each student.
$markurls = [];
$start = 0;
foreach ($students as $x => $student) {
    $markurl->param('userid', $student);
    $markurls[] = $markurl->out(false);
    if ($student == $userid) {
        $start = $x;
    }
}

$baseurl = clone($quickmarkurl);
$baseurl->param('groupid', 0);
$baseurl->param('nav', 'all');

// Group navigation.
$groupsnav = array();
if ($groups) {
    foreach ($groups as $i => $gid) {
        $group = utils::get_group_display_info($gid);
        $group->markurl = clone($quickmarkurl);
        $group->markurl->param('groupid', $gid);
        $group->markurl = $group->markurl->out(false); // Replace markurl with string val.
        $group->iscurrent = false;
        if ($groupid == $group->id) {
            $group->iscurrent = true;
        }
        $groupsnav[] = $group;
    }
}

//echo "<pre>"; var_export($data); exit;
$PAGE->add_body_classes(['fullscreen']);

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_psgrading/group_selector', [
    'groups' => $groupsnav,
    'baseurl' => $baseurl->out(false),
]);
echo '<div id="quickmark-a"></div>';
echo '<div id="quickmark-b" class="hidden"></div>';

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/quickmark', 'init', array(
    'markurls' => $markurls,
    'start' => $start,
));

echo $OUTPUT->footer();

