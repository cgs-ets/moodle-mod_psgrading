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
 * Provides {@link mod_psgrading\external\apicontrol} trait.
 *
 * @package   mod_psgrading
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace mod_psgrading\external;

defined('MOODLE_INTERNAL') || die();

use \mod_psgrading\persistents\task;
use \mod_psgrading\utils;
use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->libdir.'/externallib.php');

/**
 * Trait implementing the external function mod_psgrading_apicontrol.
 */
trait apicontrol {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function apicontrol_parameters() {
        return new external_function_parameters([
            'action' =>  new external_value(PARAM_RAW, 'Action'),
            'data' => new external_value(PARAM_RAW, 'Data to process'),
        ]);
    }

    /**
     * API Controller
     *
     * @param int $query The search query
     */
    public static function apicontrol($action, $data) {
        global $USER, $OUTPUT, $PAGE;

        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::apicontrol_parameters(), compact('action', 'data'));

        if ($action == 'save_comment') {
            $data = json_decode($data);
            return task::save_comment_and_reload($data->taskid, $data->comment);
        }

        if ($action == 'delete_comment') {
            $commentid = json_decode($data);
            return task::delete_comment($commentid);
        }

        if ($action == 'load_next_myconnect_posts') {
            $data = json_decode($data);
            $myconnect = utils::get_myconnect_data($data->username, intval($data->page));
            $html = '';
            foreach ($myconnect->posts as $post) {
                $html .= $OUTPUT->render_from_template('mod_psgrading/myconnect_post', $post);
            }
            return $html;
        }

        return 0;
    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function apicontrol_returns() {
         return new external_value(PARAM_RAW, 'Result');
    }

}