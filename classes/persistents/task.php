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
 * Provides the {@link mod_psgrading\persistents\task} class.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\persistents;

defined('MOODLE_INTERNAL') || die();

use \mod_psgrading\utils;
use \core\persistent;
use \core_user;
use \context_user;
use \context_course;
use \mod_psgrading\forms\form_mark;

/**
 * Persistent model representing a single task.
 */
class task extends persistent {

    /** Table to store this persistent model instances. */
    const TABLE = 'psgrading_tasks';
    const TABLE_TASK_LOGS = 'psgrading_task_logs';
    const TABLE_TASK_EVIDENCES = 'psgrading_task_evidences';
    const TABLE_TASK_CRITERIONS = 'psgrading_task_criterions';
    const TABLE_GRADES = 'psgrading_grades';
    const TABLE_GRADE_CRITERIONS = 'psgrading_grade_criterions';
    const TABLE_GRADE_EVIDENCES = 'psgrading_grade_evidences';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            "cmid" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "creatorusername" => [
                'type' => PARAM_RAW,
            ],
            "taskname" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "pypuoi" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "outcomes" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "criterionjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "evidencejson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "published" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "deleted" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "draftjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
        ];
    }

    public static function save_from_data($data) {
        global $DB, $USER;

        // Some validation.
        if (empty($data->id)) {
            return;
        }

        // Update the task.
        $task = new static($data->id, $data);
        $task->save();

        // Add a log entry.
        $log = new \stdClass();
        $log->taskid = $data->id;
        $log->username = $USER->username;
        $log->logtime = $task->get('timemodified');
        $log->formjson = json_encode($data);
        $log->status = 1; // published log.
        $DB->insert_record(static::TABLE_TASK_LOGS, $log);
        // Create/update criterions.
        $existingcriterions = $DB->get_records(static::TABLE_TASK_CRITERIONS, array('taskid' => $data->id));
        $criterions = json_decode($data->criterionjson);
        $seq = 0;
        foreach ($criterions as &$criterion) {
            $criterion->taskid = $data->id;
            $criterion->seq = $seq;
            if (!isset($criterion->weight)) {
                $criterion->weight = 100;
            }
            // Criterion does not already exist.
            if (!in_array($criterion->id, array_keys($existingcriterions))) {
                $criterion->id = $DB->insert_record(static::TABLE_TASK_CRITERIONS, $criterion);
            } else {
                // Update existing criterion. 
                $DB->update_record(static::TABLE_TASK_CRITERIONS, $criterion);
                unset($existingcriterions[$criterion->id]);
            }
            $seq++;
        }
        // Regenerate criterionjson from real criterion records so that they include ids and other cols.
        $task->set('criterionjson', json_encode($criterions));
        $task->save();

        // Delete leftovers.
        foreach ($existingcriterions as $existing) {
            $DB->delete_records(static::TABLE_TASK_CRITERIONS, array('id' => $existing->id));
        }

        // Create evidences.
        $DB->delete_records(static::TABLE_TASK_EVIDENCES, array('taskid' => $data->id));
        $evidences = json_decode($data->evidencejson);
        if ($evidences) {
            foreach ($evidences as $evidence) {
                $evidence->taskid = $data->id;
                $DB->insert_record(static::TABLE_TASK_EVIDENCES, $evidence);
            }
        }

        return $data->id;
    }

    public static function save_draft($formjson) {
        global $USER, $DB;

        // Some validation.
        $formdata = json_decode($formjson);
        if (empty($formdata->id)) {
            return;
        }

        $task = new static($formdata->id);
        $task->set('draftjson', $formjson);
        $task->save();

        // Only keep latest draft from this user as they are so frequent.
        $DB->delete_records(static::TABLE_TASK_LOGS, array(
            'taskid' => $formdata->id,
            'username' => $USER->username,
            'status' => 0,
        ));

        // Add a log entry.
        $log = new \stdClass();
        $log->taskid = $formdata->id;
        $log->username = $USER->username;
        $log->logtime = $task->get('timemodified');
        $log->formjson = $formjson;
        $log->status = 0; // draft log.
        $DB->insert_record(static::TABLE_TASK_LOGS, $log);

    }


    public static function get_for_coursemodule($cmid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND cmid = ?
              ORDER BY timemodified DESC";
        $params = array($cmid);

        $records = $DB->get_records_sql($sql, $params);
        $tasks = array();
        foreach ($records as $record) {
            $tasks[] = new static($record->id, $record);
        }

        return $tasks;
    }


    public static function get_grade_criterion_selections($gradeid) {
        global $DB;

        $criterions = array();
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADE_CRITERIONS . "}
                 WHERE gradeid = ?";
        $params = array($gradeid);
        $criterionrecs = $DB->get_records_sql($sql, $params);
        foreach ($criterionrecs as $rec) {
            $criterions[$rec->criterionid] = (object) array( 'gradelevel' => $rec->gradelevel, 'criterionid' => $rec->criterionid );
        }

        return $criterions;
    }

    public static function get_task_user_gradeinfo($taskid, $userid) {
        global $DB;

        $student = \core_user::get_user($userid);
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADES . "}
                 WHERE taskid = ?
                   AND studentusername = ?";
        $params = array($taskid, $student->username);
        $gradeinfo = $DB->get_record_sql($sql, $params);

        if ($gradeinfo) {
            $gradeinfo->criterions = static::get_grade_criterion_selections($gradeinfo->id);
            if ($gradeinfo->engagement) {
                $gradeinfo->engagementlang = utils::ENGAGEMENTOPTIONS[$gradeinfo->engagement];
            }
            return $gradeinfo;
        }

        return [];
    }

    public static function get_cm_user_taskinfo($cmid, $userid, $currtaskid = -1) {
        global $DB;

        $taskinfo = array();

        $tasks = static::get_for_coursemodule($cmid);
        foreach ($tasks as $task) {
            $markurl = new \moodle_url('/mod/psgrading/mark.php', array(
                'cmid' => $cmid,
                'taskid' => $task->get('id'),
                'userid' => $userid,
            ));
            $detailsurl = new \moodle_url('/mod/psgrading/details.php', array(
                'cmid' => $cmid,
                'taskid' => $task->get('id'),
                'userid' => $userid,
            ));
            $taskinfo[] = array(
                'id' => $task->get('id'),
                'taskname' => $task->get('taskname'),
                'detailsurl' => $detailsurl->out(false),
                'markurl' => $markurl->out(false),
                'iscurrent' => ($task->get('id') == $currtaskid),
            );
        }

        return $taskinfo;
    }

    public static function load_criterions(&$task) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_CRITERIONS . "}
                 WHERE taskid = ?
              ORDER BY seq ASC";
        $params = array($task->id);

        $records = $DB->get_records_sql($sql, $params);
        $criterions = array();
        foreach ($records as $record) {
            $criterions[$record->id] = $record;
        }

        $task->criterions = $criterions;
    }

    public static function load_evidences(&$task) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_EVIDENCES . "}
                 WHERE taskid = ?";
        $params = array($task->id);

        $records = $DB->get_records_sql($sql, $params);
        $evidences = array();
        foreach ($records as $record) {
            $evidences[] = $record;
        }

        $task->evidences = $evidences;
    }

    public static function get_myconnect_grade_evidences($gradeid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADE_EVIDENCES . "}
                 WHERE gradeid = ?
                   AND evidencetype = 'myconnect_post'";
        $params = array($gradeid);

        $records = $DB->get_records_sql($sql, $params);
        $evidences = array();
        foreach ($records as $record) {
            $evidences[] = intval($record->refdata);
        }

        return $evidences;
    }


    public static function save_task_grades_for_student($data) {
        global $DB, $USER;
        
        // Some validation.
        if (empty($data->taskid) || empty($data->userid)) {
            return;
        }
        // Load needed data.
        $task = new static($data->taskid);
        $student = \core_user::get_user($data->userid);
        $modulecontext = \context_module::instance($task->get('cmid'));

        // Save evidence files to permanent store.
        if (isset($data->evidences)) {
            $evidenceoptions = form_mark::evidence_options();
            $uniqueid = sprintf( "%d%d", $data->taskid, $data->userid ); // Join the taskid and userid to make a unique itemid.
            file_save_draft_area_files(
                $data->evidences, 
                $modulecontext->id, 
                'mod_psgrading', 
                'evidences', 
                $uniqueid, 
                $evidenceoptions
            );
        }

        // Check if grade for this task/user already exists.
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADES . "}
                 WHERE taskid = ?
                   AND studentusername = ?";
        $params = array($data->taskid, $student->username);
        $graderec = $DB->get_record_sql($sql, $params);

        if ($graderec) {
            // Update the existing grade data.
            $graderec->graderusername = $USER->username;
            $graderec->engagement = $data->engagement;
            $graderec->comment = $data->comment;
            $graderec->evidences = $data->evidences;
            $DB->update_record(static::TABLE_GRADES, $graderec);
        } else {
            // Insert new grade data.
            $graderec = new \stdClass();
            $graderec->taskid = $data->taskid;
            $graderec->studentusername = $student->username;
            $graderec->graderusername = $USER->username;
            $graderec->engagement = $data->engagement;
            $graderec->comment = $data->comment;
            $graderec->evidences = $data->evidences;
            $graderec->id = $DB->insert_record(static::TABLE_GRADES, $graderec);
        }

        if ($graderec->id) {

            // Recreate criterion grades.
            $DB->delete_records(static::TABLE_GRADE_CRITERIONS, array('gradeid' => $graderec->id));
            $criterions = json_decode($data->criterionjson);
            foreach ($criterions as $selection) {
                $criterion = new \stdClass();
                $criterion->taskid = $data->taskid;
                $criterion->criterionid = $selection->id;
                $criterion->gradeid = $graderec->id;
                $criterion->gradelevel = $selection->selectedlevel;
                $DB->insert_record(static::TABLE_GRADE_CRITERIONS, $criterion);
            }

            // Recreate myconnect links.
            $DB->delete_records(static::TABLE_GRADE_EVIDENCES, array(
                'gradeid' => $graderec->id,
                'evidencetype' => 'myconnect_post',
            ));
            $myconnectposts = json_decode($data->myconnectevidencejson);
            if ($myconnectposts) {
                foreach ($myconnectposts as $id) {
                    $evidence = new \stdClass();
                    $evidence->taskid = $data->taskid;
                    $evidence->gradeid = $graderec->id;
                    $evidence->evidencetype = 'myconnect_post';
                    $evidence->refdata = $id;
                    $DB->insert_record(static::TABLE_GRADE_EVIDENCES, $evidence);
                }
            }

        }

        return $graderec->id;
    }

    public static function reset_task_grades_for_student($data) {
        global $DB, $USER;

        // Some validation.
        if (empty($data->taskid) || empty($data->userid)) {
            return;
        }
        // Load needed data.
        $task = new static($data->taskid);
        $student = \core_user::get_user($data->userid);
        $modulecontext = \context_module::instance($task->get('cmid'));

        // Check if grade for this task/user already exists.
        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADES . "}
                 WHERE taskid = ?
                   AND studentusername = ?";
        $params = array($data->taskid, $student->username);
        $graderec = $DB->get_record_sql($sql, $params);

        if ($graderec) {
            // Delete everything...
            $DB->delete_records(static::TABLE_GRADES, array('id' => $graderec->id));
            $DB->delete_records(static::TABLE_GRADE_CRITERIONS, array('gradeid' => $graderec->id));
            // Delete evidence files.
            $fs = get_file_storage();
            $uniqueid = sprintf( "%d%d", $data->taskid, $data->userid ); // Join the taskid and userid to make a unique itemid.
            $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
            foreach ($files as $file) {
                $file->delete();
            }
        }
    }

    public static function save_comment_and_reload($taskid, $comment) {
        global $OUTPUT;
        static::save_comment($taskid, $comment);
        $comments = static::get_comment_bank($taskid);
        $html = $OUTPUT->render_from_template('mod_psgrading/mark_commentbank_comments', array('comments' => $comments));
        return $html;
    }

    public static function save_comment($taskid, $commenttext) {
        global $DB, $USER;
        $comment = new \stdClass();
        $comment->taskid = $taskid;
        $comment->comment = $commenttext;
        $comment->username = $USER->username;
        $comment->id = $DB->insert_record('psgrading_comment_bank', $comment);
        return $comment;
    }

    public static function get_comment_bank($taskid) {
        global $DB, $USER;
        
        $sql = "SELECT *
                  FROM {psgrading_comment_bank}
                 WHERE taskid = ?
                   AND username = ?
              ORDER BY id DESC";
        $params = array($taskid, $USER->username);

        $records = $DB->get_records_sql($sql, $params);
        $comments = array();
        foreach ($records as $record) {
            $comments[] = $record;
        }
        return $comments;
    }

    public static function delete_comment($commentid) {
        global $USER, $DB;

        $DB->delete_records('psgrading_comment_bank', array(
            'id' => $commentid,
            'username' => $USER->username,
        ));

        return 1;
    }


}
