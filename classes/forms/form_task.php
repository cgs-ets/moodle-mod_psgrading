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
        $published = (isset($this->_customdata['published'])) ? $this->_customdata['published'] : 0;
        $criteriondata = (isset($this->_customdata['criteriondata'])) ? $this->_customdata['criteriondata'] : [];
        $evidencedata = (isset($this->_customdata['evidencedata'])) ? $this->_customdata['evidencedata'] : [];
        $enableweights = (isset($this->_customdata['enableweights'])) ? $this->_customdata['enableweights'] : 0;

        /****
        * Notes:
        * - Can't use client validation when using custom action buttons. Validation is done on server in task.php.
        ****/

        //Some students have already been graded. Changing the rubric may require tasks to be regraded.

        // Page title.
        $mform->addElement('header', 'general', '');
        $mform->setExpanded('general', true, true);

        // Autosave.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/task_autosave', []));

        /*----------------------
         *   Name.
         *----------------------*/
        $mform->addElement('text', 'taskname', get_string('task:name', 'mod_psgrading'), 'size="48"');
        $mform->setType('taskname', PARAM_TEXT);

        /*----------------------
         *   PYP UOI.
         *----------------------*/
        $pypuoioptions = array(
            '' => 'Select',
            'wwa' => get_string('pypuoi:wwa', 'mod_psgrading'),
            'wwaipat' => get_string('pypuoi:wwaipat', 'mod_psgrading'),
            'hweo' => get_string('pypuoi:hweo', 'mod_psgrading'),
            'htww' => get_string('pypuoi:htww', 'mod_psgrading'),
            'hwoo' => get_string('pypuoi:hwoo', 'mod_psgrading'),
            'stp' => get_string('pypuoi:stp', 'mod_psgrading'),
        );
        $mform->addElement('select', 'pypuoi', get_string('task:pypuoi', 'mod_psgrading'), $pypuoioptions);

        /*----------------------
         *   Outcomes
         *----------------------*/
        $mform->addElement('textarea', 'outcomes', get_string("task:outcomes", "mod_psgrading"), 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('outcomes', PARAM_RAW);

        /*----------------------
         *   Criterion
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'criterionsection', get_string("task:criteria", "mod_psgrading"));
        $mform->setExpanded('criterionsection', true, true);
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'criterionjson', 'Criterion JSON');
        $mform->setType('criterionjson', PARAM_RAW);
        // Render the criterion from json.
        $criterionhtml = $OUTPUT->render_from_template('mod_psgrading/criterion_selector', array('criterions' => $criteriondata, 'enableweights' => $enableweights));
        $mform->addElement('html', $criterionhtml);

        /*----------------------
         *   Evidence
         *----------------------*/
        // A custom JS driven component.
        // Section title
        $mform->addElement('header', 'evidencesection', get_string("task:evidence", "mod_psgrading"));
        $mform->setExpanded('evidencesection', true, true);
        // The hidden value field. The field is a text field hidden by css rather than a hidden field so that we can attach validation to it. 
        $mform->addElement('text', 'evidencejson', 'Evidence JSON');
        $mform->setType('evidencejson', PARAM_RAW);
        // Render the evidence from json.
        $evidencehtml = $OUTPUT->render_from_template('mod_psgrading/evidence_selector', array(
            'evidences' => (array) $evidencedata,
        ));
        $mform->addElement('html', $evidencehtml);

        // Buttons.
        $mform->addElement('header', 'actions', '');
        $mform->setExpanded('actions', true, true);
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/task_buttons', array('published' => $published)));

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
        
        $criterion = json_decode($data['criterionjson']);
        if (empty($criterion)) {
            $errors['criterionjson'] = get_string('required');
        } else {
            foreach ($criterion as $criterion) {
                if (empty($criterion->description)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level4)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level3)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level2)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->subject)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                //if (empty($criterion->weight)) {
                //    $errors['criterionjson'] = get_string('required');
                //    break;
                //}
            }
        }

        return $errors;
    }

}