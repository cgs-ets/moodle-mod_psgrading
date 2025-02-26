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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *  Recycle bin page.
 *
 * @package    mod_psgrading
 * @copyright  2025 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once(__DIR__.'/lib.php');

use mod_psgrading\external\recycle_exporter;
use mod_psgrading\utils;

// Course_module ID, or
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$p  = optional_param('p', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('psgrading', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('psgrading', ['id' => $n], '*', MUST_EXIST);
    $course         = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('psgrading', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

$url = new moodle_url('/mod/psgrading/recyclebin.php', [
    'id' => $cm->id,
]);

$coursecontext = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);
require_login($course, true, $cm);


$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title('Recycle Bin| ' . format_string($moduleinstance->name) );
$PAGE->set_heading(format_string('Recycle Bin| ' .$moduleinstance->name));
$PAGE->set_context($modulecontext);
$PAGE->add_body_class('psgrading-overview-page');
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', ['nocache' => rand()]));

$related = [
    'cmid' => (int) $cm->id,
    'courseid' => (int) $course->id
];

$recycleexporter = new recycle_exporter(null, $related);
$output = $PAGE->get_renderer('core');
$data = $recycleexporter->export($output);

$output = $OUTPUT->header();

$output .= $OUTPUT->render_from_template('mod_psgrading/recycle', $data);


// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/recycle', 'init');

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;