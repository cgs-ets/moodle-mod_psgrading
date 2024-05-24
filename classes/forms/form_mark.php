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
        $quickmark = $this->_customdata['quickmark'];

        /*if (empty($data)) {
            // create a stub so that the fields can be setup properly.
            $data = new \stdClass();
            $data->task = new \stdClass();
            $data->task->id = -1;
            $data->task->criterions = [];
            $data->myconnectattachments = null;
            $data->currstudent = null;
        }*/

        /****
        * Notes:
        * - Can't use client validation when using custom action buttons. Validation is done on server in mark.php.
        ****/

        $mform->addElement('checkbox', 'didnotsubmit', get_string('mark:didnotsubmit', 'mod_psgrading'), '');

        // Critions.
        $mform->addElement('text', 'criterionjson', 'Criterion JSON');
        $mform->setType('criterionjson', PARAM_RAW);
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/mark_criterions', 
            array('criterions' => $data->task->criterions))
        );

        // Evidence.
        $mform->addElement('html', 
            $OUTPUT->render_from_template('mod_psgrading/mark_evidence', array(
                'task' => $data->task,
                'myconnectattachments' => $data->myconnectattachments,
                'currstudent' => $data->currstudent,
            ))
        );
        // Evidences filemanager.
        $mform->addElement('filemanager', 'evidences', '', null, self::evidence_options());

        // Engagement.
        $mform->addElement('select', 'engagement', get_string("mark:engagement", "mod_psgrading"), utils::ENGAGEMENTOPTIONS);
        $mform->setType('engagement', PARAM_RAW);

        // Comment.
        $mform->addElement('textarea', 'comment', get_string("mark:comment", "mod_psgrading", $data->currstudent->firstname . ' ' . $data->currstudent->lastname) . '<a title="Save to comment bank" id="save-to-comment-bank" href="#"><i class="fa fa-floppy-o" aria-hidden="true"></i></a>', 'wrap="virtual" rows="4" cols="51"');
        $mform->setType('comment', PARAM_RAW);
        $comments = task::get_comment_bank($data->task->id);
        $mform->addElement('html', $OUTPUT->render_from_template('mod_psgrading/mark_commentbank', array('comments' => $comments)));

        // Replace grader.
        if (isset($data->gradeinfo) && isset($data->gradeinfo->graderisdiff)) {
            $grader = \core_user::get_user_by_username($data->gradeinfo->graderusername);
            $mform->addElement('checkbox', 'replacegrader', 'Grading teacher', get_string('mark:replacegrader', 'mod_psgrading', fullname($grader)));
        }

        // Buttons.
        if ($quickmark) {
            $mform->addElement('html', '<a class="btn btn-primary" href="#" id="quickmarksave">Save and show next</a>');
        } else {
            $buttonarray = array();
            $buttonarray[] = &$mform->createElement('submit','save', get_string('mark:save', 'mod_psgrading'));
            $buttonarray[] = &$mform->createElement('submit','saveshownext', get_string('mark:saveshownext', 'mod_psgrading'));
            $buttonarray[] = &$mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        }

        // Hidden.
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);

        // MyConnect evidence.
        $mform->addElement('hidden', 'selectedmyconnectjson');
        $mform->setType('selectedmyconnectjson', PARAM_RAW);
        $mform->addElement('hidden', 'myconnectevidencejson');
        $mform->setType('myconnectevidencejson', PARAM_RAW);
    }
}