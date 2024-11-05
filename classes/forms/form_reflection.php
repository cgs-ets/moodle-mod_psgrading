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

use \mod_psgrading\reporting;

class form_reflection extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $type = $this->_customdata['type'];


        if ($type == 'editor') {
            /*----------------------
            *   Reflection editor
            *----------------------*/
            $type = 'editor';
            $name = 'reflection';
            $title = '';
            $mform->addElement($type, $name, $title, null, static::editor_options());
            $mform->setType($name, PARAM_RAW);
        }


        if ($type == 'form') {
            /*----------------------
            *   Reflection textarea
            *----------------------*/
            $type = 'textarea';
            $name = 'reflection';
            $title = 'Text';
            $mform->addElement($type, $name, $title, 'wrap="virtual" rows="10" cols="50"');
            $mform->setType($name, PARAM_RAW);

            /*----------------------
            *   Reflection image file
            *----------------------*/
            $mform->addElement('filemanager', 'reflectionimage', 'Image upload', null, self::image_options());
        }

        /*----------------------
        *   Buttons
        *----------------------*/
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'save', get_string('task:save', 'mod_psgrading'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

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
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
        );
    }


    /**
     * Returns the options array to use for the evidence filemanager
     *
     * @return array
     */
    public static function image_options() {
        global $CFG;

        return array(
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => array('.jpeg', '.jpg', '.png'),
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK,
        );
    }

}