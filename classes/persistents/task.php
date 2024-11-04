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

use mod_psgrading\utils;
use core\persistent;
use mod_psgrading\forms\form_mark;
use mod_psgrading\forms\form_task;
use mod_psgrading\external\task_exporter;

/**
 * Persistent model representing a single task.
 */
class task extends persistent {

    /** Table to store this persistent model instances. */
    const TABLE = 'psgrading_tasks';
    const TABLE_TASK_LOGS = 'psgrading_task_logs';
    const TABLE_TASK_EVIDENCES = 'psgrading_task_evidences';
    const TABLE_TASK_CRITERIONS = 'psgrading_task_criterions';
    const TABLE_TASK_ENGAGEMENT = 'psgrading_task_engagement';
    const TABLE_GRADES = 'psgrading_grades';
    const TABLE_GRADE_CRITERIONS = 'psgrading_grade_criterions';
    const TABLE_GRADE_ENGAGEMENT = 'psgrading_grade_engagement';
    const TABLE_GRADE_EVIDENCES = 'psgrading_grade_evidences';
    const TABLE_RELEASE_POSTS = 'psgrading_release_posts';
    const TABLE_GRADES_CACHE = 'psgrading_grades_cache';


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
            "proposedrelease" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "deleted" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "seq" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "timerelease" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "releaseprocessed" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "notes" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "engagementjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "oldorder" => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
        ];
    }

    public static function save_from_data($id, $cmid, $formdata) {
        global $DB, $USER;

        // Take what we need from formdata.
        $data = new \stdClass();
        $data->taskname = $formdata->taskname;
        $data->pypuoi = $formdata->pypuoi;
        $data->outcomes = $formdata->outcomes;
        $data->published = intval($formdata->published);
        $data->proposedrelease = intval($formdata->proposedrelease);
        $data->criterionjson = $formdata->criterionjson;
        $data->evidencejson = $formdata->evidencejson;
        $data->engagementjson = $formdata->engagementjson;

        $editing = false;
        if ($id > 0) {
            // Make sure the record actually exists.
            if (!static::record_exists($id)) {
                return false;
            }
            $editing = true;
        }

        // Load or create new instance, depending on $id.
        $task = new static($id);

        // Set/update the data.
        $timenow = time();
        $task->set('taskname', $data->taskname);
        $task->set('pypuoi', $data->pypuoi);
        $task->set('outcomes', $data->outcomes);
        $task->set('criterionjson', $data->criterionjson);
        $task->set('evidencejson', $data->evidencejson);
        $task->set('engagementjson', $data->engagementjson);
        $task->set('notes', '');
        $task->set('proposedrelease', $data->proposedrelease);
        if ($editing) {
            // Editing.
            list($released, $countdown) = static::get_release_info($id);
            if ($data->published == 1 || !$released) { // can only hide until grades released.
                $task->set('published', $data->published);
            }
        } else {
            // Creating.
            $task->set('cmid', $cmid);
            $task->set('creatorusername', $USER->username);
            $task->set('deleted', 0);
            $task->set('oldorder', 0);
            $task->set('published', $data->published);
        }
        $task->save();
        $id = $task->get('id');

        // Store editor files to permanent file area and get text.
        $context = \context_module::instance($cmid);
        $editor = $formdata->notes;
        $notestext = file_save_draft_area_files(
            $editor['itemid'],
            $context->id,
            'mod_psgrading',
            'notes',
            $id,
            form_task::editor_options(),
            $editor['text'],
        );
        $task->set('notes', $notestext);
        $task->save();

        // Add a log entry.
        $log = new \stdClass();
        $log->taskid = $id;
        $log->username = $USER->username;
        $log->logtime = $task->get('timemodified');
        $log->formjson = json_encode($data);
        $log->status = 1; // published log.
        $DB->insert_record(static::TABLE_TASK_LOGS, $log);

        // Create/update criterions.
        $existingcriterions = $DB->get_records(static::TABLE_TASK_CRITERIONS, array('taskid' => $id));
        $criterions = json_decode($data->criterionjson);
        $seq = 0;
        foreach ($criterions as &$criterion) {
            $criterion->taskid = $id;
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

        // Create/update engagement.
        $existingengagement = $DB->get_records(static::TABLE_TASK_ENGAGEMENT, ['taskid' => $id]);
        $engagements = json_decode($data->engagementjson);
        $seq = 0;

        foreach ($engagements as &$engagement) {
            $engagement->taskid = $id;
            $engagement->seq = $seq;
            if (!isset($engagement->weight)) {
                $engagement->weight = 100;
            }
            // Engagement does not already exist.
            if (!in_array($engagement->id, array_keys($existingengagement))) {
                $engagement->id = $DB->insert_record(static::TABLE_TASK_ENGAGEMENT, $engagement);
            } else {
                // Update existing engagement.
                $DB->update_record(static::TABLE_TASK_ENGAGEMENT, $engagement);
                unset($existingengagement[$engagement->id]);
            }
            $seq++;
        }
        // Regenerate Engagementjson from real criterion records so that they include ids and other cols.
        $task->set('engagementjson', json_encode($engagements));
        $task->save();

        // Delete leftovers.
        foreach ($existingcriterions as $existing) {
            $DB->delete_records(static::TABLE_TASK_CRITERIONS, array('id' => $existing->id));
        }

        // Delete leftovers.
        foreach ($existingengagement as $existing) {
            $DB->delete_records(static::TABLE_TASK_ENGAGEMENT, array('id' => $existing->id));
        }

        // Create evidences.
        $DB->delete_records(static::TABLE_TASK_EVIDENCES, array('taskid' => $id));
        $evidences = json_decode($data->evidencejson);
        if ($evidences) {
            foreach ($evidences as $evidence) {
                $evidence->taskid = $id;
                $DB->insert_record(static::TABLE_TASK_EVIDENCES, $evidence);
            }
        }

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return $id;
    }



    public static function soft_delete($id) {
        global $USER, $DB;

        $task = new static($id);
        $task->set('deleted', 1);
        $task->save();

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    public static function get_for_coursemodule($cmid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND cmid = ?
              ORDER BY seq ASC, timecreated DESC";
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

        $sql = "SELECT gc.*
                FROM mdl_psgrading_grade_criterions gc
                INNER JOIN mdl_psgrading_task_criterions tc on tc.id = gc.criterionid
                WHERE gc.gradeid = ?";
        $params = array($gradeid);
        $criterionrecs = $DB->get_records_sql($sql, $params);
        foreach ($criterionrecs as $rec) {
            $criterions[$rec->criterionid] = (object) array( 'gradelevel' => $rec->gradelevel, 'criterionid' => $rec->criterionid );
        }

        return $criterions;
    }

    public static function get_grade_engagement_selections($gradeid) {
        global $DB;

        $engagements = [];

        $sql = "SELECT gce.*
                FROM mdl_psgrading_grade_engagement gce
                INNER JOIN mdl_psgrading_task_engagement te on te.id = gce.engagementid
                WHERE gce.gradeid = ?";
        $params = [$gradeid];
        $engagementrecs = $DB->get_records_sql($sql, $params);

        foreach ($engagementrecs as $rec) {
            $engagements[$rec->engagementid] = (object) [ 'gradelevel' => $rec->gradelevel, 'engagementid' => $rec->engagementid ];
        }

        return $engagements;
    }

    public static function get_printurl($taskid) {
        $task = new static($taskid);
        $printurl = new \moodle_url('/mod/psgrading/print.php', array(
            'cmid' => $task->get('cmid'),
            'taskid' => $taskid,
        ));
        return $printurl->out();
    }

    public static function has_grades($taskid) {
        global $DB;
        if ($taskid) {
            return $DB->record_exists(static::TABLE_GRADES, array('taskid' => $taskid));
        }
        return false;
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
            $gradeinfo->engagements = static::get_grade_engagement_selections($gradeinfo->id);
            $gradeinfo->engagementlang = '';
            if ($gradeinfo->engagement && isset(utils::ENGAGEMENTOPTIONS[$gradeinfo->engagement])) {
                $gradeinfo->engagementlang = utils::ENGAGEMENTOPTIONS[$gradeinfo->engagement];
            }
            return $gradeinfo;
        }

        return [];
    }

    public static function get_cm_user_taskinfo($cmid, $userid, $currtaskid = -1) {
        $taskinfo = array();
        $tasks = static::get_for_coursemodule($cmid);
        foreach ($tasks as $task) {
            if (! $task->get('published')) {
                continue;
            }
            $markurl = new \moodle_url('/mod/psgrading/mark.php', array(
                'cmid' => $cmid,
                'taskid' => $task->get('id'),
                'userid' => $userid,
            ));
            $qmarkurl = new \moodle_url('/mod/psgrading/quickmark.php', array(
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
                'qmarkurl' => $qmarkurl->out(false),
                'iscurrent' => ($task->get('id') == $currtaskid),
            );
        }

        return $taskinfo;
    }

    public static function get_criterions($taskid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_CRITERIONS . "}
                 WHERE taskid = ?
              ORDER BY seq ASC";
        $params = array($taskid);

        $records = $DB->get_records_sql($sql, $params);
        $criterions = array();
        foreach ($records as $record) {
            $criterions[$record->id] = $record;
        }

        return $criterions;
    }

    public static function get_engagement($taskid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_ENGAGEMENT . "}
                 WHERE taskid = ?
              ORDER BY seq ASC";
        $params = [$taskid];

        $records = $DB->get_records_sql($sql, $params);
        $engagements = [];
        foreach ($records as $record) {
            $engagements[$record->id] = $record;
        }

        return $engagements;
    }

    public static function get_evidences($taskid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_TASK_EVIDENCES . "}
                 WHERE taskid = ?";
        $params = array($taskid);

        $records = $DB->get_records_sql($sql, $params);
        $evidences = array();
        foreach ($records as $record) {
            $evidences[] = $record;
        }

        return $evidences;
    }

    public static function get_myconnect_grade_evidences($gradeid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_GRADE_EVIDENCES . "}
                 WHERE gradeid = ?
                   AND evidencetype = 'myconnect_attachment'";
        $params = array($gradeid);

        $records = $DB->get_records_sql($sql, $params);
        $evidences = array();
        foreach ($records as $record) {
            $evidences[] = intval($record->refdata);
        }

        return $evidences;
    }

    public static function get_grade_release_posts($gradeid) {
        global $DB;

        $sql = "SELECT *
                  FROM {" . static::TABLE_RELEASE_POSTS . "}
                 WHERE gradeid = ?";
        $params = array($gradeid);

        $records = $DB->get_records_sql($sql, $params);
        $postids = array();
        foreach ($records as $record) {
            $postids[] = intval($record->postid);
        }
        return $postids;
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
            if ($data->replacegrader) {
                $graderec->graderusername = $USER->username;
            }
            $graderec->didnotsubmit = $data->didnotsubmit;
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
            $graderec->didnotsubmit = $data->didnotsubmit;
            $graderec->engagement = $data->engagement;
            $graderec->comment = $data->comment;
            $graderec->evidences = $data->evidences;
            $graderec->id = $DB->insert_record(static::TABLE_GRADES, $graderec);
        }

        if ($graderec->id) {
            // Recreate criterion grades.
            $DB->delete_records(static::TABLE_GRADE_CRITERIONS, array('gradeid' => $graderec->id));
            $criterions = json_decode($data->criterionjson);
            if ($criterions) {
                foreach ($criterions as $selection) {
                    $criterion = new \stdClass();
                    $criterion->taskid = $data->taskid;
                    $criterion->criterionid = $selection->id;
                    $criterion->gradeid = $graderec->id;
                    $criterion->gradelevel = $selection->selectedlevel;
                    $DB->insert_record(static::TABLE_GRADE_CRITERIONS, $criterion);
                }
            }

            // Recreate engagement grades.
            $DB->delete_records(static::TABLE_GRADE_ENGAGEMENT, ['gradeid' => $graderec->id]);
            $engagements = json_decode($data->engagementjson);
            if ($engagements) {
                foreach ($engagements as $selection) {
                    $engagement = new \stdClass();
                    $engagement->taskid = $data->taskid;
                    $engagement->engagementid = $selection->id;
                    $engagement->gradeid = $graderec->id;
                    $engagement->gradelevel = $selection->selectedlevel;
                    $DB->insert_record(static::TABLE_GRADE_ENGAGEMENT, $engagement);
                }
            }

            // Recreate myconnect links.
            $DB->delete_records(static::TABLE_GRADE_EVIDENCES, array(
                'gradeid' => $graderec->id,
                'evidencetype' => 'myconnect_attachment',
            ));
            $myconnectfiles = json_decode($data->myconnectevidencejson);
            if ($myconnectfiles) {
                foreach ($myconnectfiles as $id) {
                    $evidence = new \stdClass();
                    $evidence->taskid = $data->taskid;
                    $evidence->gradeid = $graderec->id;
                    $evidence->evidencetype = 'myconnect_attachment';
                    $evidence->refdata = $id;
                    $DB->insert_record(static::TABLE_GRADE_EVIDENCES, $evidence);
                }
            }

        }

        //$tasks = static::compute_grades_for_cm($task->get('cmid'), $data->userid, true, true);
        //$reportgrades = static::compute_report_grades($tasks, true);

        // Invalidate cached list page.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return $graderec->id;
    }

    public static function compute_grades_for_course($courseid, $userid, $includehiddentasks, $isstaff, $reportingperiod = 1) {
        global  $DB;

        // Get all psgrading instances for this course.
        $sql = "SELECT id, restrictto, excludeusers
                FROM {psgrading}
                WHERE course = ?
                  AND reportingperiod = ?";
        $modinstances = $DB->get_records_sql($sql, array($courseid, $reportingperiod));
        $courseinstances = array();
        // Don't include instances that are restricted to specific users.
        foreach ($modinstances as $inst) {
            if (empty($inst->restrictto)) {
                $courseinstances[] = $inst->id;
            }
        }
        if (empty($courseinstances)) {
            return;
        }

        // Get the cmids for the mod instances.
        $moduleid = $DB->get_field('modules', 'id', array('name'=> 'psgrading'));
        list($insql, $inparams) = $DB->get_in_or_equal($courseinstances);
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course = ?
                   AND module = ?
                   AND instance $insql";
        $params = array($courseid, $moduleid);
        $cms = $DB->get_records_sql($sql, array_merge($params, $inparams));
        if (empty($cms)) {
            return;
        }

        // Compute the grades across all cms.
        $tasks = array();
        foreach ($cms as $cm) {
            $cmtasks = static::compute_grades_for_cm($cm->id, $userid, $includehiddentasks, $isstaff);
            $tasks = array_merge($tasks, $cmtasks);
        }

        return $tasks;
    }

    public static function compute_grades_for_cm($cmid, $userid, $includehiddentasks, $isstaff) {
        global $OUTPUT;

        // Get all tasks for this course module.
        $tasks = array();
        $cmtasks = static::get_for_coursemodule($cmid);

        if (empty($cmtasks)) {
            return [];
        }

        foreach ($cmtasks as $task) {
            // TODO: Check that the task applies to this user based on setting.

            $taskexporter = new task_exporter($task, array('userid' => $userid));
            $task = $taskexporter->export($OUTPUT);
            if (!$task->published && !$includehiddentasks) {
                continue;
            }

            $task = static::compute_grades_for_task($task, $userid, $isstaff);

            // Ditch some unnecessary data.
            unset($task->criterions);
            $tasks[] = $task;
        }

        return $tasks;
    }

    public static function compute_grades_for_task($task, $userid, $isstaff) {
        global $OUTPUT;

        // Add the task criterion definitions.
        $task->criterions = static::get_criterions($task->id);

        // Setup details url.
        $detailsurl = new \moodle_url('/mod/psgrading/quickmark.php', array(
            'cmid' => $task->cmid,
            'taskid' => $task->id,
            'userid' => $userid,
        ));

        // Get existing grades for this user.
        $gradeinfo = static::get_task_user_gradeinfo($task->id, $userid);
        $task->gradeinfo = $gradeinfo;
        $task->subjectgrades = array();
        $task->success = array(
            'grade' => 0,
            'gradelang' => '',
            'detailsurl' => $detailsurl->out(false),
            'missingcomment' => static::is_missing_comment_engagement($gradeinfo),
            'missingevidence' => static::is_missing_evidence($gradeinfo, $userid, $task->cmid),
        );

        $showgrades = true;
        // If task is not released yet do not show grades parents/students.
        if (!$task->released || utils::is_hide_ps_grades()) {
            if (!$isstaff) {
                $showgrades = false;
            }
        }

        // Check if there is gradeinfo / whether task is released.
        // Check if student `didnosubmit`
        if (empty($gradeinfo) || !$showgrades || $gradeinfo->didnotsubmit) {
            // Skip over the calculations, but define empty structure required by template.
            foreach (utils::SUBJECTOPTIONS as $subject) {
                if ($subject['val']) {
                    $task->subjectgrades[] = array(
                        'subject' => $subject['val'],
                        'subjectsanitised' => str_replace(array(' ', '&', '–'), '', $subject['val']),
                        'grade' => 0,
                        'gradelang' => '',
                    );
                }
                if (!empty($gradeinfo) && $gradeinfo->didnotsubmit) {
                    $task->didnotsubmit = true;
                    $task->success['gradelang'] = get_string('mark:dns', 'mod_psgrading');
                }
            }
            unset($task->gradeinfo);
            unset($task->criterions);
            return $task;
        }

        // Extract subject grades from criterion grades.
        $subjectgrades = array();
        error_log(print_r($gradeinfo, true));
        foreach ($gradeinfo->criterions as $criteriongrade) {
            $criterionsubject = $task->criterions[$criteriongrade->criterionid]->subject;
            if (!isset($subjectgrades[$criterionsubject])) {
                $subjectgrades[$criterionsubject] = array();
            }
            if ($criteriongrade->gradelevel) {
                $subjectgrades[$criterionsubject][] = $criteriongrade->gradelevel;
            }
        }

        // Flatten to rounded averages.
        //foreach ($subjectgrades as &$subjectgrade) {
        //    if (count($subjectgrade)) {
        //        $subjectgrade = array_sum($subjectgrade)/count($subjectgrade);
        //        $subjectgrade = (int) round($subjectgrade, 0);
        //    } else {
        //        $subjectgrade = 0;
        //    }
        //}

        // Get the final scores.
        foreach ($subjectgrades as &$subjectgrade) {
            if (count($subjectgrade)) {
                // Get the mean.
                $subjectgrademean = array_sum($subjectgrade)/count($subjectgrade);
                // Get the median.
                $subjectgrademedian = utils::median($subjectgrade);
                // Influenced average.
                $influencedmean = ($subjectgrademean + $subjectgrademedian) / 2;
                // Rounding based on influenced mean.
                if ($influencedmean > $subjectgrademean) {
                    $subjectgrade = (int) round($influencedmean, 0, PHP_ROUND_HALF_UP);
                } else if ($influencedmean < $subjectgrademean) {
                    $subjectgrade = (int) round($influencedmean, 0, PHP_ROUND_HALF_DOWN);
                } else {
                    $subjectgrade = (int) round($influencedmean, 0, PHP_ROUND_HALF_UP);
                }
            } else {
                $subjectgrade = 0;
            }
        }

        // Rebuild into mustache friendly array.
        foreach (utils::SUBJECTOPTIONS as $subject) {
            if ($subject['val']) {
                $grade = 0;
                if ( ! empty($subjectgrades[$subject['val']]) ) {
                    $grade = $subjectgrades[$subject['val']];
                }
                $gradelang = utils::GRADELANG[$grade];
                $task->subjectgrades[] = array(
                    'subject' => $subject['val'],
                    'subjectsanitised' => str_replace(array(' ', '&', '–'), '', $subject['val']),
                    'grade' => $grade,
                    'gradelang' => $isstaff ? $gradelang['full'] : $gradelang['minimal'],
                    'gradetip' => $gradelang['tip'],
                );
            }
        }

        // Final Grades to be average from rubric rather than subject averages.
        // Calculate success/final grades --> average of task's subject grades.
        /*$success = 0;
        if (array_sum($subjectgrades)) {
            $successgrades = $subjectgrades;
            // Remove subjects that are zero from the success calculation.
            if (($key = array_search(0, $successgrades)) !== false) {
                unset($successgrades[$key]);
            }
            $success = array_sum($successgrades)/count($successgrades);
            $success = (int) round($success, 0);
        }
        $gradelang = utils::GRADELANG[$success];
        $task->success['grade'] = $success;
        $task->success['gradelang'] = $isstaff ? $gradelang['full'] : $gradelang['minimal'];
        $task->success['gradetip'] = $gradelang['tip'];*/

        // Calculate success/final grades --> average of task's criteria grades.
        $success = 0;
        $criteriagrades = array();
        foreach ($gradeinfo->criterions as $criteriongrade) {
            if ($criteriongrade->gradelevel) {
                $criteriagrades[] = $criteriongrade->gradelevel;
            }
        }
        if (array_sum($criteriagrades)) {
            //$success = array_sum($criteriagrades)/count($criteriagrades);
            //$success = (int) round($success, 0);
            $success = 0;
            // Get the mean.
            $successmean = array_sum($criteriagrades)/count($criteriagrades);
            // Get the median.
            $successmedian = utils::median($criteriagrades);
            // Influenced average.
            $influencedmean = ($successmean + $successmedian) / 2;
            // Rounding based on influenced mean.
            if ($influencedmean > $successmean) {
                $success = (int) round($influencedmean, 0, PHP_ROUND_HALF_UP);
            } else if ($influencedmean < $successmean) {
                $success = (int) round($influencedmean, 0, PHP_ROUND_HALF_DOWN);
            } else {
                $success = (int) round($influencedmean, 0, PHP_ROUND_HALF_UP);
            }

        }
        $gradelang = utils::GRADELANG[$success];
        $task->success['grade'] = $success;
        $task->success['gradelang'] = $isstaff ? $gradelang['full'] : $gradelang['minimal'];
        $task->success['gradetip'] = $gradelang['tip'];

        // Get the releasepost
        if ($task->released) {
            $task->releaseposturl = null;
            $releasepostids = static::get_grade_release_posts($gradeinfo->id);
            $student = \core_user::get_user($userid);
            $thepost = array_pop($releasepostids);
            if ($thepost) {
                $releaseposturl = new \moodle_url('/local/myconnect/index.php', array(
                    'timeline' => $student->username,
                    'postid' => $thepost,
                ));
                $task->releaseposturl = $releaseposturl->out(false);
            }
        }

        // Ditch some unnecessary data.
        unset($task->criterions);
        return $task;
    }

    public static function is_missing_comment_engagement($gradeinfo) {
        if (empty($gradeinfo)) {
            return true;
        }
        if (empty($gradeinfo->engagement) || empty($gradeinfo->comment)) {
            return true;
        }
    }

    public static function is_missing_evidence($gradeinfo, $userid, $cmid) {
        if (empty($gradeinfo)) {
            return true;
        }

        $evidence = static::get_evidences($gradeinfo->taskid);
        if ($evidence) {
            return false;
        }

        $myconnectevidence = static::get_myconnect_grade_evidences($gradeinfo->taskid);
        if ($myconnectevidence) {
            return false;
        }

        $modulecontext = \context_module::instance($cmid);
        $fs = get_file_storage();
        $uniqueid = sprintf( "%d%d", $gradeinfo->taskid, $userid ); // Join the taskid and userid to make a unique itemid.
        $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
        if ($files) {
            return false;
        }
    }

    public static function compute_report_grades($tasks) {
        $reportgrades = array();
        if (empty($tasks)) {
            return [];
        }
        foreach (utils::SUBJECTOPTIONS as $subject) {
            $subject = $subject['val'];
            if (!$subject) {
                continue;
            }
            // Get all the grades for this subject accross all of the tasks.
            foreach ($tasks as $task) {
                if (empty($task->subjectgrades)) {
                    continue;
                }
                foreach ($task->subjectgrades as $subjectgrade) {
                    $subjectgrade = (array) $subjectgrade;
                    if ($subjectgrade['subject'] == $subject) {
                        if (!isset($reportgrades[$subject])) {
                            $reportgrades[$subject] = array();
                        }
                        if ($subjectgrade['grade']) {
                            $reportgrades[$subject][] = $subjectgrade['grade'];
                        }
                    }
                }
            }
        }

        // Flatten to rounded averages.
        foreach ($reportgrades as &$reportgrade) {
            if (array_sum($reportgrade)) {
                $reportgrade = array_sum($reportgrade)/count($reportgrade);
                $reportgrade = (int) round($reportgrade, 0);
            } else {
                $reportgrade = 0;
            }
        }
        // Rebuild into mustache friendly array.
        foreach ($reportgrades as $key => $grade) {
            $reportgrades[$key] = array(
                'subject' => $key,
                'subjectsanitised' => str_replace(array(' ', '&', '–'), '', $key),
                'grade' => $grade,
                'gradelang' => utils::GRADELANG[$grade]['full'],
                'gradetip' => utils::GRADELANG[$grade]['tip'],
                'issubject' => true,
            );
        }
        $reportgrades = array_values($reportgrades);

        // Get the average engagement accross all tasks.
        $engagement = array();
        foreach ($tasks as $task) {
            if (!empty($task->gradeinfo->engagement)) {
                $engagement[] = utils::ENGAGEMENTWEIGHTS[$task->gradeinfo->engagement];
            }
        }
        // Round engagement.
        if (array_sum($engagement)) {
            $engagement = array_sum($engagement)/count($engagement);
            $engagement = (int) round($engagement, 0);
        } else {
            $engagement = 0;
        }
        // Round up to nearest 25.
        //$engagement = ceil($engagement / 25) * 25;
        $engagement = round($engagement / 25) * 25;
        $engagementlang = array_flip(utils::ENGAGEMENTWEIGHTS);

        // Add to report grades.
        $reportgrades[] = array(
            'subject' => 'Engagement',
            'subjectsanitised' => 'engagement',
            'grade' => $engagement,
            'gradelang' => $engagementlang[$engagement],
            'issubject' => false,
        );

        return $reportgrades;
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
            $DB->delete_records(static::TABLE_GRADE_ENGAGEMENT, ['gradeid' => $graderec->id]);
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

    public static function reorder_all($taskids) {
        $seq = 0;
        foreach ($taskids as $id) {
            $task = new static($id);
            $task->set('seq', $seq);
            $task->update();
            $seq++;
        }

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    public static function publish($id) {
        $task = new static($id);
        // For previous records that have this column in null is throwing an error.
        if (empty($task->get('engagementjson') )) {
            $task->set('engagementjson', '');
        }
        $task->set('published', 1);

        $task->update();

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    public static function unpublish($id) {
        $task = new static($id);
        $task->set('published', 0);
        $task->update();

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    public static function release($id) {
        $task = new static($id);
        $timeplus15mins = time() + (15 * 60);
        $task->set('timerelease', $timeplus15mins); // Release in 15 minutes.
        $task->update();

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    public static function unrelease($id) {
        $task = new static($id);
        $task->set('timerelease', 0);
        $task->update();

        // Invalidate list html cache.
        utils::invalidate_cache($task->get('cmid'), 'list-%');

        return 1;
    }

    /*public static function get_diff($id) {
        $task = new static($id);
        return utils::diff_versions(utils::get_task_as_json($task), $task->get('draftjson'));
    }*/

    public static function get_release_info($id) {
        $task = new static($id);

        // Check if released. Time must be in the past but not 0.
        $released = false;
        $now = time();
        $nowplus15mins = $now + (15 * 60); // Release in 15 minutes.
        if ($task->get('timerelease') && $now > $task->get('timerelease')) {
            $released = true;
        }

        // Calculate countdown.
        $releasecountdown = 0;
        if ($task->get('timerelease') && $now <= $task->get('timerelease')) {
            $releasecountdown = $task->get('timerelease') - $now;
            $minutes = floor(($releasecountdown / 60) % 60);
            $seconds = $releasecountdown % 60;
            $releasecountdown = "$minutes minutes $seconds seconds"; // To minutes.
        }

        return array($released, $releasecountdown);
    }


}
