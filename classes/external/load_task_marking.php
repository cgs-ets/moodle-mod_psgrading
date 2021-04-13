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
 * Provides {@link mod_psgrading\external\post_reply} trait.
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
use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->libdir.'/externallib.php');

/**
 * Trait implementing the external function mod_psgrading_load_task_marking.
 */
trait load_task_marking {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function load_task_marking_parameters() {
        return new external_function_parameters([
            'taskid' => new external_value(PARAM_INT, 'Task ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    /**
     * load_grading the form data.
     *
     * @param int $query The search query
     */
    public static function load_task_marking($taskid, $userid) {
        global $USER, $PAGE;

        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::load_task_marking_parameters(), compact('taskid', 'userid'));

        // Load existing task.
        $exists = task::record_exists($taskid);
        if ($exists) {
            $task = new task($taskid);
        }
        if (!$exists || $task->get('deleted')) {
            return '';
        }

        // Load the marks for this task and user.
        $grades = task::get_grades($taskid, $userid);
        if (empty($grades)) {
            return '';
        }

        // Export the data for the template using the course students and task marking.
        $output = $PAGE->get_renderer('core');
        $relateds = array(
            'task' => $task,
            'students' => null,
            'userid' => $userid,
            'usergrades' => $grades,
            'markurl' => null,
        );
        $exporter = new mark_exporter(null, $relateds);
        $data = $exporter->export($output);

        //var_export($data); exit;

        $html = $output->render_from_template('mod_psgrading/marking', $data);

        return $html;
    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function load_task_marking_returns() {
        return new external_value(PARAM_RAW, 'HTML');
    }

}