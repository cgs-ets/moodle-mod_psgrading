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
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

use mod_psgrading\reporting;

class form_treflection extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $customedata = $this->_customdata;

        /*----------------------
        *   Reflection editor
        *----------------------*/
        $mform->addElement('textarea', 'reflection', 'Teacher reflection', 'wrap="virtual" rows="7" cols="50"');
        $mform->addElement('textarea', 'reflection2', '<strong>' . $customedata['name'] . '’s</strong> Learning Achievements', 'wrap="virtual" rows="7" cols="50"');
        $mform->addElement('textarea', 'reflection3', 'Ongoing Learning Focus Learning Focus for <strong>' . $customedata['name'] . '</strong>', 'wrap="virtual" rows="7" cols="50"');
        $mform->addElement('textarea', 'reflection4', 'Strategies that we as educators are currently using to support learning focus for <strong>' . $customedata['name'] . '</strong>', 'wrap="virtual" rows="7" cols="50"');
        $mform->addElement('textarea', 'reflection5', 'Possible ways parents can nurture growth at home', 'wrap="virtual" rows="7" cols="50"');

        /*----------------------
        *   Buttons
        *----------------------*/
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'save', get_string('task:save', 'mod_psgrading'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        /*----------------------
        *   Hidden fields
        *----------------------*/
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);

    }

    /**
     * Returns the options array to use in editor
     *
     * @return array
     */
    public static function editor_options() {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
        ];
    }


}
