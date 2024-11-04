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
use \mod_psgrading\reporting;
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

        if ($action == 'load_next_myconnect_attachments') {
            $data = json_decode($data);

            $myconnectattachments = utils::get_myconnect_data($data->username, $data->gradeid, intval($data->page), json_decode($data->selectedmyconnectfiles));
            $html = '';
            foreach ($myconnectattachments as $attachment) {
                $html .= '<div class="attachment-wrap">' . $OUTPUT->render_from_template('mod_psgrading/myconnect_post_attachments', $attachment) . '</div>';
            }
            return $html;
        }

        if ($action == 'reorder_tasks') {
            $taskids = json_decode($data);
            return task::reorder_all($taskids);
        }

        if ($action == 'delete_task') {
            $taskid = json_decode($data);
            return task::soft_delete($taskid);
        }

        if ($action == 'publish_task') {
            $taskid = json_decode($data);
            return task::publish($taskid);
        }

        if ($action == 'unpublish_task') {
            $taskid = json_decode($data);
            return task::unpublish($taskid);
        }

        if ($action == 'release_task') {
            $taskid = json_decode($data);
            return task::release($taskid);
        }

        if ($action == 'unrelease_task') {
            $taskid = json_decode($data);
            return task::unrelease($taskid);
        }

        if ($action == 'save_mark') {
            $data = json_decode($data);
            $data->didnotsubmit = isset($data->didnotsubmit) ? 1 : 0;
            $data->replacegrader = isset($data->replacegrader) ? 1 : 0;
            $result = task::save_task_grades_for_student($data);
            return $result;
        }

        if ($action == 'get_diff') {
            $taskid = json_decode($data);
            return task::get_diff($taskid);
        }

        if ($action == 'get_countdown') {
            $taskid = json_decode($data);
            list($released, $releasecountdown) = task::get_release_info($taskid);

            // If countdown is done invalidate the cache because the page will be refreshed.
            if (empty($releasecountdown)) {
                utils::invalidate_cache_by_taskid($taskid, 'list-%');
                return "";
            }

            return "Feedback will be released in {$releasecountdown}. Click to cancel.";
        }

        if ($action == 'grade_element') {
            $data = json_decode($data);
            if (property_exists($data, 'reflection')) {
                return reporting::save_reportelement_text(
                    $data->courseid,
                    $data->year,
                    $data->period,
                    $data->username,
                    $data->subjectarea,
                    $data->type,
                    $data->reflection
                );
            } else if (property_exists($data, 'grade')) {
                return reporting::save_reportelement_effort(
                    $data->courseid,
                    $data->year,
                    $data->period,
                    $data->username,
                    $data->subjectarea,
                    $data->type,
                    $data->grade
                );
            }
        }

        if ($action == 'reporting_help') {
            $data = json_decode($data);
            return reporting::get_reportelement_help(
                $data->courseid,
                $data->year,
                $data->period,
                $data->username,
                $data->subjectarea,
            );
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