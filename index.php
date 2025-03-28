<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Display information about all the mod_psgrading modules in the requested course.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
global $DB, $PAGE, $OUTPUT, $CFG;

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

$PAGE->set_url('/mod/psgrading/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

\mod_psgrading\event\course_module_instance_list_viewed::create_from_course($course)->trigger();

$modulenameplural = get_string('modulenameplural', 'mod_psgrading');
echo $OUTPUT->heading($modulenameplural);

$psgradings = get_all_instances_in_course('psgrading', $course);
if (empty($psgradings)) {
    notice(get_string('nonewmodules', 'mod_psgrading'), new moodle_url('/course/view.php', ['id' => $course->id]));
}


$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format == 'weeks') {
    $table->head  = [get_string('week'), get_string('name')];
    $table->align = ['center', 'left'];
} else if ($course->format == 'topics') {
    $table->head  = [get_string('topic'), get_string('name')];
    $table->align = ['center', 'left', 'left', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left', 'left', 'left'];
}

foreach ($psgradings as $psgrading) {
    if (!$psgrading->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/psgrading/view.php', ['id' => $psgrading->coursemodule]),
            format_string($psgrading->name, true),
            ['class' => 'dimmed']);
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/psgrading/view.php', ['id' => $psgrading->coursemodule]),
            format_string($psgrading->name, true));
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = [$psgrading->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
