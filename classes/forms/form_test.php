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

class form_test extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;

        /****
        * Notes:
        * - Can't use client validation with custom action buttons. Validation is done on server in tasks.php.
        ****/

        // Page title.
        $mform->addElement('header', 'general', '');
        $mform->setExpanded('general', true, true);


        /*----------------------
         *   Name.
         *----------------------*/
        $mform->addElement('text', 'test', 'test', 'size="48"');
        //$mform->addRule('test', get_string('required'), 'required', null, 'server');
        $mform->setType('test', PARAM_TEXT);

      
        $this->add_action_buttons(false);


    }


    // Perform some extra moodle validation.
    function validation($data, $files) {
        $errors = array();

        if (empty($data['test'])) {
            $errors['test'] = get_string('required');
        }

        return $errors;
    }

}