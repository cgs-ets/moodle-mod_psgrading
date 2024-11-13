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
 * Form to import tasks from another activity
 *
 * @package   mod_psgrading
 * @copyright 2024, Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\forms;

use moodleform;
use mod_psgrading\importingtask;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
/**
 * Form to import tasks from one activity no another
 */
class form_import_task extends moodleform {
    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->settype('id', PARAM_INT); // To be able to pre-fill the form.

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->settype('cmid', PARAM_INT); // To be able to pre-fill the form.

        // Get the courses with PS grading modules.
        $courses = importingtask::get_courses_with_psgrading_modules();
        $courseswithpsgrading = [];
        $cmidsincourse = [];

        foreach ($courses as $course) {
            $courseswithpsgrading[$course->courseid] = $course->coursename;
            $cmidsincourse[$course->courseid][] = $course->cmid; // This will connect to the psgrading_task table.
        }

        $mform->addElement('select',
                            'courseswithpsgrading',
                            get_string('courseswithpsgrading', 'psgrading'),
                            $courseswithpsgrading,
                            ['class' => 'psgrading-import-task-course-selection']);
        $mform->getElement('courseswithpsgrading')->setMultiple(true);

        // Generate a drop down  with the activities available in the course(s) selected.
        $mform->addElement('text', 'selectedcourseJSON', 'Select course(s) JSON');
        $mform->settype('selectedcourseJSON', PARAM_RAW);
        $mform->setDefault('selectedcourseJSON', '[]');

        $mform->addElement('html', '<div class="psgrading-import-task-modules-in-selected-course"></div>');

        $mform->addElement('text', 'selectedtasksJSON', 'Select task(s) JSON');
        $mform->settype('selectedtasksJSON', PARAM_RAW);
        $mform->setDefault('selectedtasksJSON', '[]');

        $mform->addElement('html', '<div class="psgrading-import-task-tasks-in-selected-activity"></div>');

        $buttonarray = [];

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('import', 'psgrading'));
        $buttonarray[] = &$mform->createElement('cancel', 'canceltbutton', get_string('cancel', 'psgrading'));

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        $mform->closeHeaderBefore('buttonar');
    }

}

