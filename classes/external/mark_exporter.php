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
 * Provides {@link mod_psgrading\external\mark_exporter} class.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\external;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use core\external\exporter;
use \mod_psgrading\utils;
use \mod_psgrading\persistents\task;

/**
 * Exporter of a single task
 */
class mark_exporter extends exporter {

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'task' => [
                'type' => task_exporter::read_properties_definition(),
                'multiple' => false,
                'optional' => false,
            ],
            'groups' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => false,
            ],
            'students' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => false,
            ],
            'currstudent' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'baseurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'nextstudenturl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'prevstudenturl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'gradeinfo' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'myconnectattachments' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
        ];
    }

    /**
    * Returns a list of objects that are related.
    *
    * Data needed to generate "other" properties.
    *
    * @return array
    */
    protected static function define_related() {
        return [
            'task' => 'mod_psgrading\persistents\task',
            'students' => 'int[]?',
            'userid' => 'int',
            'markurl' => 'moodle_url',
            'groups' => 'int[]?',
            'groupid' => 'int',
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $USER, $CFG;

        $baseurl = clone($this->related['markurl']);

		$taskexporter = new task_exporter($this->related['task'], array('userid' => $this->related['userid']));
		$task = $taskexporter->export($output);

        // Group navigation.
        $groups = array();
        if ($this->related['groups']) {
            foreach ($this->related['groups'] as $i => $groupid) {
                $group = utils::get_group_display_info($groupid);
                $group->markurl = clone($baseurl);
                $group->markurl->param('groupid', $groupid);
                $group->markurl = $group->markurl->out(false); // Replace markurl with string val.
                $group->iscurrent = false;
                if ($this->related['groupid'] == $group->id) {
                    $group->iscurrent = true;
                }
                $groups[] = $group;
            }
        }

        // Student Navigation.
        $currstudent = null;
        $nextstudenturl = null;
        $prevstudenturl = null;
        $students = array();
        foreach ($this->related['students'] as $i => $studentid) {
            $student = \core_user::get_user($studentid);
            utils::load_user_display_info($student);
            $student->iscurrent = false;
            $student->markurl = clone($baseurl);
            $student->markurl->param('userid', $student->id);
            $student->markurl = $student->markurl->out(false); // Replace markurl with string val.
            $overviewurl = new \moodle_url('/mod/psgrading/overview.php', array(
                'cmid' => $task->cmid,
                'userid' => $student->id,
            ));
            $student->overviewurl = $overviewurl->out(false);
            if ($this->related['userid'] == $student->id) {
                $student->iscurrent = true;
                $currstudent = $student;
                $len = count($this->related['students']);
                if ($len > 1) {
                    // Base url.
                    $nextstudenturl = clone($baseurl);
                    $prevstudenturl = clone($baseurl);

                    // Determine next and prev users.
                    if ($i == 0) {
                        // if the index is 0, then the prev student loops back to the end of the list.
                        $prevstudenturl->param('userid', $this->related['students'][$len - 1]);
                    } else {
                        $prevstudenturl->param('userid', $this->related['students'][$i - 1]);
                    }

                    if ($i == $len - 1) {
                        // if the index is at the end of the list, then the next student is at the begining of the list.
                        $nextstudenturl->param('userid', $this->related['students'][0]);
                    } else {
                        $nextstudenturl->param('userid', $this->related['students'][$i + 1]);
                    }
                }
            }
            $students[] = $student;
        }
        if ($prevstudenturl) {
            $prevstudenturl = $prevstudenturl->out(false);
            $nextstudenturl = $nextstudenturl->out(false);
        }

        // Load current students other tasks from this activity.
        $currstudent->othertasks = task::get_cm_user_taskinfo($task->cmid, $this->related['userid'], $task->id);

        // Get existing marking values for this user and incorporate into task criterion data.
        $gradeinfo = task::get_task_user_gradeinfo($task->id, $this->related['userid']);

        // Load task criterions.
        $task->criterions = task::get_criterions($task->id);
        foreach ($task->criterions as $criteron) {
            // add marks to criterion definitions.
            if (isset($gradeinfo->criterions[$criteron->id])) {
                // There is a gradelevel chosen for this criterion.
                $criteron->{'level' . $gradeinfo->criterions[$criteron->id]->gradelevel . 'selected'} = true;
            }
        }

        // Zero indexes so templates work.
        $task->criterions = array_values($task->criterions);
        
        $baseurl->param('groupid', 0);
        $baseurl->param('nav', 'all');

        // Get selected MyConnect grade evidences.
        $task->myconnectevidences = array();
        $task->myconnectevidencejson = '';
        $myconnectfileids = array();
        if ($gradeinfo) {
            // Get selected ids
            $myconnectfileids = task::get_myconnect_grade_evidences($gradeinfo->id);
            if ($myconnectfileids) {
                // Convert to json.
                $task->myconnectevidencejson = json_encode($myconnectfileids);
            }
            // Get formatted attachment for selected fileids.
            $task->myconnectevidences = utils::get_myconnect_data_for_attachments($currstudent->username, $myconnectfileids);
        }

        // Get MyConnect attachments for evidence selector, passing selected attachments and posts to be excluded.
        $myconnectattachments = utils::get_myconnect_data($currstudent->username, $gradeinfo->id, 0, $myconnectfileids);

        // Put the already selected attachments at the front.
        if ($task->myconnectevidences || $myconnectattachments) {
            $myconnectattachments = array_merge($task->myconnectevidences, $myconnectattachments);
        }

        //echo "<pre>";
        //echo "pre selected<hr>";
        //var_export($task->myconnectevidences); 
        //echo "everything<hr>";
        //var_export($myconnect); 
        //exit;

        return array(
            'task' => $task,
            'students' => $students,
            'groups' => $groups,
            'currstudent' => $currstudent,
            'baseurl' => $baseurl->out(false),
            'nextstudenturl' => $nextstudenturl,
            'prevstudenturl' => $prevstudenturl,
            'gradeinfo' => $gradeinfo,
            'myconnectattachments' => $myconnectattachments,
        );
    }

}
