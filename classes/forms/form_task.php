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
 * Form definition for posting.
 * *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\forms;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class form_task extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $rubrichtml = $this->_customdata['rubrichtml'];
        $evidencehtml = $this->_customdata['evidencehtml'];

        // Page title.
        $mform->addElement('header', 'general', '');

        // Autosave status.
        $mform->addElement('html', '<div id="savestatus"></div>');

        /*----------------------
         *   Name.
         *----------------------*/
        $mform->addElement('text', 'taskname', get_string('taskform:name', 'mod_psgrading'), 'size="48"');
        $mform->setType('taskname', PARAM_TEXT);
        $mform->addRule('taskname', get_string('required'), 'required', null, 'client');
        $mform->addRule('taskname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        /*----------------------
         *   PYP UOI.
         *----------------------*/
        $pypuoioptions = array(
            '' => 'Select',
            'HtWW' => get_string('pypuoi:htww', 'mod_psgrading'),
        );
        $mform->addElement('select', 'pypuoi', get_string('taskform:pypuoi', 'mod_psgrading'), $pypuoioptions);
        $mform->addRule('pypuoi', get_string('required'), 'required', null, 'client');

        /*----------------------
         *   Outcomes
         *----------------------*/
        $mform->addElement('textarea', 'outcomes', get_string("taskform:outcomes", "mod_psgrading"), 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('outcomes', get_string('required'), 'required', null, 'client');

        /*----------------------
         *   Rubric
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'rubricsection', get_string("taskform:rubric", "mod_psgrading"));
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'rubricjson', 'Rubric JSON');
        $mform->setType('rubricjson', PARAM_RAW);
        $mform->addRule('rubricjson', get_string('required'), 'required', null, 'client');
        // The custom component html.
        $mform->addElement('html', $rubrichtml);


        /*----------------------
         *   Evidence
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'evidencesection', get_string("taskform:evidence", "mod_psgrading"));
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'evidencejson', 'Evidence JSON');
        $mform->setType('evidencejson', PARAM_RAW);
        $mform->addRule('evidencejson', get_string('required'), 'required', null, 'client');
        // The custom component html.
        $mform->addElement('html', $evidencehtml);

        // Buttons.
        $this->add_action_buttons(true, get_string('taskform:publish', 'mod_psgrading'));

        // Hidden fields
        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);
    }

}