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
 * Provides {@link mod_psgrading\external\overview_exporter} class.
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
class overview_exporter extends exporter {

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'tasks' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => false,
            ],
            'reportgrades' => [
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
            'cmid' => 'int',
            'students' => 'int[]?',
            'userid' => 'int',
            'overviewurl' => 'moodle_url',
            'isstaff' => 'bool',
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $USER;

        $baseurl = clone($this->related['overviewurl']);

        // Get all tasks for this course module.
        $tasks = array();
        $cmtasks = task::get_for_coursemodule($this->related['cmid']);

        foreach ($cmtasks as $task) {
            $taskexporter = new task_exporter($task);
            $task = $taskexporter->export($output);

            // Add rubric url.
            $rubricurl = new \moodle_url('/mod/psgrading/rubric.php', array(
                'cmid' => $task->cmid,
                'taskid' => $task->id,
                'userid' => $this->related['userid'],
            ));
            $task->rubricurl = $rubricurl->out(false);

            // Add the task criterion definitions.
            task::load_criterions($task);

            // Get existing grades for this user.
            $gradeinfo = task::get_task_user_gradeinfo($task->id, $this->related['userid']);
            $task->gradeinfo = $gradeinfo;

            // Extract subject grades from criterion grades.
            $subjectgrades = array();
            foreach ($gradeinfo->criterions as $criteriongrade) {
                $criterionsubject = $task->criterions[$criteriongrade->criterionid]->subject;
                if (!isset($subjectgrades[$criterionsubject])) {
                    $subjectgrades[$criterionsubject] = array();
                }
                $subjectgrades[$criterionsubject][] = $criteriongrade->gradelevel;
            }
            // Flatten to rounded averages.
            foreach ($subjectgrades as &$subjectgrade) {
                $subjectgrade = array_sum($subjectgrade)/count($subjectgrade);
                $subjectgrade = (int) round($subjectgrade, 0);
            }
            // Rebuild into mustache friendly array.
            $task->subjectgrades = array();
            foreach (utils::SUBJECTOPTIONS as $subject) {
                if ($subject['val']) {
                    $grade = 0;
                    if (isset($subjectgrades[$subject['val']])) {
                        $grade = $subjectgrades[$subject['val']];
                    }
                    $gradelang = utils::GRADELANG[$grade];
                    $task->subjectgrades[] = array(
                        'subject' => $subject['val'],
                        'subjectsanitised' => str_replace('&', '', $subject['val']),
                        'grade' => $grade,
                        'gradelang' => $this->related['isstaff'] ? $gradelang['full'] : $gradelang['minimal'],
                    );
                }
            }

            // Calculate success.
            $success = array_sum($subjectgrades)/count($subjectgrades);
            $success = (int) round($success, 0);
            $gradelang = utils::GRADELANG[$success];
            $task->success = array(
                'grade' => $success,
                'gradelang' => $this->related['isstaff'] ? $gradelang['full'] : $gradelang['minimal'],
            );

            // Load task evidences (default).
            task::load_evidences($task);
            foreach ($task->evidences as &$evidence) {
                if ($evidence->evidencetype == 'cm') {
                    // get the icon and name.
                    $cm = get_coursemodule_from_id('', $evidence->refdata);
                    $modinfo = get_fast_modinfo($cm->course, $USER->id);
                    $cms = $modinfo->get_cms();
                    $cm = $cms[$evidence->refdata];
                    $evidence->icon = $cm->get_icon_url()->out();
                    $evidence->url = $cm->url;
                    $evidence->name = $cm->name;
                }
            }

            // Ditch some unnecessary data.
            unset($task->criterions);

            $tasks[] = $task;
        }

        $index = count( $tasks ) - 1;
        $tasks[$index]->islast = true;

        // Calculate report grades.
        $reportgrades = array();
        if ($this->related['isstaff']) {
            foreach (utils::SUBJECTOPTIONS as $subject) {
                $subject = $subject['val'];
                if (!$subject) {
                    continue;
                }
                // Get all the grades for this subject accross all of the tasks.
                foreach ($tasks as $task) {
                    foreach ($task->subjectgrades as $subjectgrade) {
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
                    'subjectsanitised' => str_replace('&', '', $key),
                    'grade' => $grade,
                    'gradelang' => utils::GRADELANG[$grade]['full'],
                    'issubject' => true,
                );
            }
            $reportgrades = array_values($reportgrades);

            // Get the engagement accross all tasks.
            $engagement = array();
            foreach ($tasks as $task) {
                if ($task->gradeinfo->engagement) {
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
            $engagement = ceil($engagement / 25) * 25;
            // Add to report grades.
            $reportgrades[] = array(
                'subject' => 'Engagement',
                'subjectsanitised' => 'engagement',
                'grade' => $engagement,
                'gradelang' => $engagement,
                'issubject' => false,
            );
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
            $student->overviewurl = clone($baseurl);
            $student->overviewurl->param('userid', $student->id);
            $student->overviewurl = $student->overviewurl->out(false); // Replace overviewurl with string val.
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

        return array(
            'tasks' => $tasks,
            'reportgrades' => $reportgrades,
            'students' => $students,
            'currstudent' => $currstudent,
            'nextstudenturl' => $nextstudenturl,
            'prevstudenturl' => $prevstudenturl,
        );
    }

}