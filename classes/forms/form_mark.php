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
use \mod_psgrading\persistents\task;

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
            // create a stub so that the fields can be setup properly.
            $data = new \stdClass();
            $data->task = new \stdClass();
            $data->task->id = -1;
            $data->task->criterions = [];
            $data->task->evidences = [];
            $data->myconnect = null;
        }

        /****
        * Notes:
        * - Can't use client validation when using custom action buttons. Validation is done on server in mark.php.
        ****/

        // Critions.
        $mform->addElement('text', 'criterionjson', 'Criterion JSON');
        $mform->setType('criterionjson', PARAM_RAW);
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/mark_criterions', 
            array('criterions' => $data->task->criterions))
        );

        // Evidence.
        $mform->addElement('html', 
            $OUTPUT->render_from_template('mod_psgrading/mark_evidence', array(
                'evidences' => $data->task->evidences,
                'myconnect' => $data->myconnect,
            ))
        );
        // Evidences filemanager.
        $mform->addElement('filemanager', 'evidences', '', null, self::evidence_options());

        // Engagement.
        $mform->addElement('select', 'engagement', get_string("mark:engagement", "mod_psgrading"), utils::ENGAGEMENTOPTIONS);
        $mform->setType('engagement', PARAM_RAW);
        $mform->addRule('engagement', get_string('required'), 'required', null, 'client');


        // Comment.
        $mform->addElement('textarea', 'comment', get_string("mark:comment", "mod_psgrading") . '<a title="Save to comment bank" id="save-to-comment-bank" href="#"><i class="fa fa-floppy-o" aria-hidden="true"></i></a>', 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('comment', PARAM_RAW);
        $comments = task::get_comment_bank($data->task->id);
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/mark_commentbank', array('comments' => $comments)));

        // Buttons.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/mark_buttons', array()));

        // Hidden.
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);

        // MyConnect evidence.
        $mform->addElement('hidden', 'myconnectevidencejson');
        $mform->setType('myconnectevidencejson', PARAM_RAW);
        
    }


}