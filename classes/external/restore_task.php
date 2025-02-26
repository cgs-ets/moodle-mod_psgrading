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
 * @copyright 2025 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

 namespace mod_psgrading\external;

 defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_psgrading\persistents\task;

 require_once($CFG->libdir.'/externallib.php');

 /**
 * Trait implementing the external function mod_psgrading_get_activities_in_course.
 */

 trait restore_task {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function restore_task_parameters() {
        return new external_function_parameters([
            'taskid' => new external_value(PARAM_RAW, 'Task to restore'),
        ]);
    }

     /**
     * API Controller
     *
     * @param int $query The search query
     */
    public static function restore_task($taskid) {
        global $COURSE;

        // Setup context.
        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::restore_task_parameters(), ['taskid' => $taskid]);
        $r = task::restore_task($taskid);
        return ['status' => $r];
    }

        /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function restore_task_returns() {
        return new external_single_structure([ 'status' =>
                                                new external_value(PARAM_RAW, 'Status of the restore'), ]
        );
    }


 }