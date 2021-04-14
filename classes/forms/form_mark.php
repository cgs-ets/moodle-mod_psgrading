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
require_once($CFG->dirroot . '/repository/lib.php');

use \mod_psgrading\utils;

class form_mark extends \moodleform {

    /**
     * Returns the options array to use for the evidence filemanager
     *
     * @return array
     */
    public static function evidence_options() {
        global $CFG;

        return array(
            'subdirs' => 0,
            'maxfiles' => 30,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => '*',
            'return_types'=> FILE_INTERNAL | FILE_CONTROLLED_LINK,
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $data = $this->_customdata['data'];

        if (empty($data)) {
            return;
        }

        /****
        * Notes:
        * - Can't use client validation when using custom action buttons. Validation is done on server in mark.php.
        ****/

        // Header.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/markform_header', $data));

        // Critions.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/markform_criterions', 
            array('criterions' => $data->task->criterions))
        );

        // Evidence.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/markform_evidence', 
            array('evidences' => $data->task->evidences))
        );
        // Evidences filemanager.
        $mform->addElement('filemanager', 'evidences', '', null, self::evidence_options());


        // Comment.


        // Hidden fields
        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

    }


}