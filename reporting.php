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

// Course ID
$courseid = required_param('courseid', PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$period = optional_param('period', 1, PARAM_INT);

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_psgrading'));
}

if (empty($year)) {
    $year = date('Y');
}

$coursecontext = context_course::instance($course->id);
require_login($course, true);

if (!utils::is_grader()) {
    exit;
}

$url = new moodle_url('/mod/psgrading/reporting.php', array(
    'courseid' => $course->id,
    'year' => $year,
    'period' => $period,
));
$PAGE->set_url($url);
$title = 'Primary School Reporting';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_context($coursecontext);


// Get classes based on user.
$classes = reporting::get_staff_classes($USER->username, $year, $period);

// Get students from classes.
$students = array();
foreach ($classes as &$class) {
    $class->students = reporting::get_class_students($class->classcode, $year, $period);
    $students = array_merge($students, array_column($class->students, 'id'));
}
$students = array_unique($students);
$students = array_combine($students, $students);

//echo "<pre>";
//var_export($students);
//exit;

array_walk($students, function(&$value, $key) { 
    $user = \core_user::get_user_by_username($value);
    if (!empty($user)) {
        utils::load_user_display_info($user);
        $value = array (
            'user' => $user,
            'assesscodes' => array(),
            'reportelements' => array(),
        );
    }
});

// Cache reporting requirements for each student.
foreach ($classes as $class) {
    foreach ($class->students as $classstudent) {
        if (!isset($students[$classstudent->id]['assesscodes'])) {
            unset($students[$classstudent->id]);
            continue;
        }
        if (!in_array($class->assesscode, $students[$classstudent->id]['assesscodes'])) {
            $students[$classstudent->id]['assesscodes'][] = $class->assesscode;
            // Add the reportelements based on the assesscode.
            $students[$classstudent->id]['reportelements'] = array_merge(
                $students[$classstudent->id]['reportelements'], 
                reporting::get_reportelements($class->assesscode, $classstudent->yearlevel)
            );
        }
    }
}

// Get existing reporting values.
reporting::populate_existing_reportelements($courseid, $year, $period, $students);

$data = array(
    'students' => array_values($students),
);

//echo "<pre>";
//var_export($data);
//exit;

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/psgrading/psgrading.css', array('nocache' => rand())));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_psgrading/reporting', $data);

// Add scripts.
$PAGE->requires->js_call_amd('mod_psgrading/reporting', 'init', array(
    'courseid' => $courseid,
    'year' => $year,
    'period' => $period,
));

echo $OUTPUT->footer();
echo '<div class="invisible-underlay"></div>';