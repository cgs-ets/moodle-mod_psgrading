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

use \mod_psgrading\utils;

class form_task extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $published = $this->_customdata['published'];
        $rubricjson = $this->_customdata['rubricjson'];
        $evidencejson = $this->_customdata['evidencejson'];
        $activities = $this->_customdata['activities'];

        /****
        * Notes:
        * - Can't use client validation with custom action buttons. Validation is done on server in tasks.php.
        ****/

        // Page title.
        $mform->addElement('header', 'general', '');
        $mform->setExpanded('general');

        // Autosave.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/taskform_autosave', []));

        /*----------------------
         *   Name.
         *----------------------*/
        $mform->addElement('text', 'taskname', get_string('taskform:name', 'mod_psgrading'), 'size="48"');
        $mform->setType('taskname', PARAM_TEXT);

        /*----------------------
         *   PYP UOI.
         *----------------------*/
        $pypuoioptions = array(
            '' => 'Select',
            'HtWW' => get_string('pypuoi:htww', 'mod_psgrading'),
        );
        $mform->addElement('select', 'pypuoi', get_string('taskform:pypuoi', 'mod_psgrading'), $pypuoioptions);

        /*----------------------
         *   Outcomes
         *----------------------*/
        $mform->addElement('textarea', 'outcomes', get_string("taskform:outcomes", "mod_psgrading"), 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('name', PARAM_TEXT);

        /*----------------------
         *   Rubric
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'rubricsection', get_string("taskform:rubric", "mod_psgrading"));
        $mform->setExpanded('rubricsection');
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'rubricjson', 'Rubric JSON');
        $mform->setType('rubricjson', PARAM_RAW);
        // Render the rubric from json.
        $rubricdata = json_decode($rubricjson);
        if (empty($rubricdata)) {
            $rubricdata = [utils::get_stub_criterion()]; // Add a default empty criterion.
        }
        $rubricdata = utils::decorate_subjectdata($rubricdata);
        $rubrichtml = $OUTPUT->render_from_template('mod_psgrading/rubric_selector', array('criterions' => $rubricdata));
        $mform->addElement('html', $rubrichtml);


        /*----------------------
         *   Evidence
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'evidencesection', get_string("taskform:evidence", "mod_psgrading"));
        $mform->setExpanded('evidencesection');
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'evidencejson', 'Evidence JSON');
        $mform->setType('evidencejson', PARAM_RAW);
        // Render the evidence from json.
        $evidencedata = json_decode($evidencejson);
        $evidencehtml = $OUTPUT->render_from_template('mod_psgrading/evidence_selector', array(
            'evidences' => $evidencedata, 
            'activities' => (array) $activities,
        ));
        $mform->addElement('html', $evidencehtml);

        // Buttons.
        $mform->addElement('header', 'actions', '');
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/taskform_buttons', array('published' => $published)));
        $mform->setExpanded('actions');


        // Hidden fields
        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);


    }


    // Perform some extra moodle validation.
    function validation($data, $files) {
        $errors = array();

        if ($data['action'] != 'publish') {
            return [];
        }

        if (empty($data['taskname'])) {
            $errors['taskname'] = get_string('required');
        }
        
        if (empty($data['pypuoi'])) {
            $errors['pypuoi'] = get_string('required');
        }
        
        if (empty($data['outcomes'])) {
            $errors['outcomes'] = get_string('required');
        }
        
        $rubric = json_decode($data['rubricjson']);
        if (empty($rubric)) {
            $errors['rubricjson'] = get_string('required');
        } else {
            foreach ($rubric as $criterion) {
                if (empty($criterion->description)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level2)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level3)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level4)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->subject)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->weight)) {
                    $errors['rubricjson'] = get_string('required');
                    break;
                }
            }
        }

        return $errors;
    }

}