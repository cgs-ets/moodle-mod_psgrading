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
 * The main mod_psgrading configuration form.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_psgrading
 * @copyright  2021 Michael Vangelovski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_psgrading_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $DB, $COURSE, $PAGE;


        // NEGATED REQUIREMENT: Only allow a single instance per course.
        /*$urlparams = $PAGE->url->params();
        if (isset($urlparams['add'])) {
            $exists = $DB->get_record('psgrading', array('course' => $COURSE->id), '*', IGNORE_MULTIPLE);
            if ($exists) {
                $courseurl = new moodle_url('/course/view.php', array(
                    'id' => $COURSE->id,
                ));
                $notice = get_string("singleinstanceonly", "mod_psgrading");
                redirect(
                    $courseurl->out(),
                    '<p>'.$notice.'</p>',
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
                exit;
            }
        }*/

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('modform:name', 'mod_psgrading'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'modform:name', 'mod_psgrading');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Custom fields here.

        // Enable weights
        //$mform->addElement('selectyesno', 'enableweights', get_string('enableweights', 'mod_psgrading'));
        //$mform->setDefault('enableweights', 0);

        // Reporting period
        $options = array(
            '' => '',
            '1' => 1,
            '2' => 2,
        );
        $select = $mform->addElement('select', 'reportingperiod', get_string('reportingperiod', 'mod_psgrading'), $options);
        $select->setSelected('');
        $mform->addRule('reportingperiod', null, 'required', null, 'client');

        // Restrict to specific students
        $mform->addElement('textarea', 'restrictto', get_string("restrictto", "mod_psgrading"), 'wrap="virtual" rows="7" cols="100"');

        
        $mform->addElement('textarea', 'excludeusers', get_string("excludeusers", "mod_psgrading"), 'wrap="virtual" rows="7" cols="100"');

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }
}
