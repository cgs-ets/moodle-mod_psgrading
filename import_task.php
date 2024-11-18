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
 * Version metadata for the mod_psgrading plugin.
 *
 * @package   mod_psgrading
 * @copyright 2024, Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_psgrading\forms\form_import_task;
use mod_psgrading\importingtask;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$cmid   = required_param('cmid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'psgrading');
$psgrading = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

// Check the user has the required capabilities.
require_capability('mod/psgrading:addinstance', $context);

$url = new moodle_url('/mod/psgrading/import_task.php', ['cmid' => $cmid]);

$PAGE->set_url($url);

$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_title(get_string('import_task', 'psgrading'));

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css'));

echo $OUTPUT->header();

$mform = new form_import_task(null, ['id' => $course->id, 'cmid' => $cmid]);

echo $OUTPUT->heading(get_string('import_task', 'psgrading'));

if ($mform->is_cancelled()) {
    $url = new moodle_url('/mod/psgrading/view.php',
    ['id' => $cmid, 'groupid' => 0]);
    redirect($url->out(false));
} else if ($data = $mform->get_data()) {
    $r = importingtask::copy_tasks_to_activity($data->cmid, json_decode($data->selectedtasksJSON));
    $result  = get_string('redirectmessage', 'psgrading', $r);

    $url = new moodle_url('/mod/psgrading/view.php',
                        ['id' => $data->cmid, 'groupid' => 0]);
    if (array_key_exists('e', $r)) {
        redirect($url->out(false), get_string('importsuccess', 'psgrading'), 2, \core\output\notification::NOTIFY_ERROR);
    } else {
        // Redirect to the page with the activity.
        redirect($url->out(false), get_string('importsuccess', 'psgrading'), 2);
    }

} else {
    $message = get_string('remindernoevidencecopy', 'psgrading');
    $level   = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
    $mform->display();
}

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/import_task', 'init', []);

// Finish the page.
echo $OUTPUT->footer();
