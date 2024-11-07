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
use \mod_psgrading\persistents\task;

class form_task extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $edit = (isset($this->_customdata['edit'])) ? $this->_customdata['edit'] : 0;
        $criteriondata = (isset($this->_customdata['criteriondata'])) ? $this->_customdata['criteriondata'] : [];
        $engagementdata = (isset($this->_customdata['engagementdata'])) ? $this->_customdata['engagementdata'] : [];
        $evidencedata = (isset($this->_customdata['evidencedata'])) ? $this->_customdata['evidencedata'] : [];
        $enableweights = (isset($this->_customdata['enableweights'])) ? $this->_customdata['enableweights'] : 0;
        $oldorder = (isset($this->_customdata['oldorder'])) ? $this->_customdata['oldorder'] : 0;
        $hasgrades = (isset($this->_customdata['hasgrades'])) ? $this->_customdata['hasgrades'] : 0;


        /****
        * Notes:
        * - Can't use client validation when using custom action buttons. Validation is done on server in task.php.
        ****/

        // Page title.
        $mform->addElement('header', 'details', 'Details');
        $mform->setExpanded('details', true, true);

        // Print preview.
        if ($edit) {
            $mform->addElement('html', '<a class="btn-print" data-toggle="tooltip" data-placement="right" title="Print preview" href="' . task::get_printurl($edit) . '"><i class="fa fa-print" aria-hidden="true"></i></a>');
        }

        /*----------------------
         *   Name.
         *----------------------*/
        $mform->addElement('text', 'taskname', get_string('task:name', 'mod_psgrading'), 'size="48"');
        $mform->setType('taskname', PARAM_TEXT);

        /*----------------------
         *   PYP UOI.
         *----------------------*/
        $mform->addElement('select', 'pypuoi', get_string('task:pypuoi', 'mod_psgrading'), utils::PYPUOIOPTIONS);

        /*----------------------
         *   Outcomes
         *----------------------*/
        $mform->addElement('textarea', 'outcomes', get_string("task:outcomes", "mod_psgrading"), 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('outcomes', PARAM_RAW);

        /*----------------------
        *   Visible
        *----------------------*/
        $type = 'advcheckbox';
        $name = 'published';
        $label = get_string("task:visibility", "mod_psgrading");
        $desc = get_string("task:visibledesc", "mod_psgrading");
        $options = array('');
        list($released, $countdown) = task::get_release_info($edit);
        if ($released) {
            $options = array('disabled' => 'disabled');
        }
        $values = array(0, 1);
        $mform->addElement($type, $name, $label, $desc, $options, $values);

        /*----------------------
        *   Proposed release date
        *----------------------*/
        $mform->addElement('date_time_selector', 'proposedrelease', get_string('task:proposedrelease', 'mod_psgrading'));

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
        $criterionhtml = $OUTPUT->render_from_template('mod_psgrading/criterion_selector', array(
            'criterions' => $criteriondata,
            'enableweights' => $enableweights,
            'criterionstub' => htmlentities(json_encode(utils::get_stub_criterion()), ENT_QUOTES, 'UTF-8'),
            'oldorder' =>  $oldorder,
            'hasgrades' => $hasgrades,
        ));
        $mform->addElement('html', $criterionhtml);

        /*----------------------
         *   Engagement
         *----------------------*/
        // A custom JS driven component.
        // Section title.
        $mform->addElement('header', 'engagementsection', get_string("mark:engagement", "mod_psgrading"));
        $mform->setExpanded('engagementsection', true, true);
         // The hidden value field. The field is a text field hidden
         // by css rather than a hidden field so that we can attach validation to it.
         $mform->addElement('text', 'engagementjson', 'Engagement Criterion JSON');
         $mform->setType('engagementjson', PARAM_RAW);
        $engagementhtml = $OUTPUT->render_from_template('mod_psgrading/engagement_selector', array(
            'engagements' => $engagementdata,
            'engagementstub' => htmlentities(json_encode(utils::get_stub_criterion()), ENT_QUOTES, 'UTF-8'),
        ));
        $mform->addElement('html', $engagementhtml);

        /*----------------------
         *   Evidence
         *----------------------*/
        // A custom JS driven component.
        // Section title.
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

        /*----------------------
        *   Notes
        *----------------------*/
        // Section title.
        $mform->addElement('header', 'othersection', 'Other');
        $mform->setExpanded('othersection', true, true);
        $type = 'editor';
        $name = 'notes';
        $title = get_string('task:notes', 'mod_psgrading');
        $mform->addElement($type, $name, $title, null, static::editor_options());
        $mform->setType($name, PARAM_RAW);

        /*----------------------
         *   Buttons.
         *----------------------*/
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit','save',get_string('task:save', 'mod_psgrading'));
        $buttonarray[] = &$mform->createElement('cancel');
        if (!$released && $edit) {
            $buttonarray[] = &$mform->createElement(
                'submit',
                'delete',
                get_string('task:delete', 'mod_psgrading'),
            );
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);


        // Hidden fields
        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);
    }


    // Perform some extra moodle validation.
    /*function validation($data, $files) {
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
                // Add level 5 and level 1.
                if (empty($criterion->level5)) {
                    $errors['criterionjson'] = get_string('required');
                    break;
                }
                if (empty($criterion->level1)) {
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
    }*/

    /**
     * Returns the options array to use in editor
     *
     * @return array
     */
    public static function editor_options() {
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
        );
    }

}
