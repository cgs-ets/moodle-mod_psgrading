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
            'basenavurl' => [
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
            'isstaff' => [
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
            'courseid' => 'int?',
            'cmid' => 'int?',
            'groups' => 'int[]?',
            'students' => 'int[]?',
            'userid' => 'int',
            'groupid' => 'int',
            'isstaff' => 'bool',
            'includehiddentasks' => 'bool?',
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

        $baseurl = new \moodle_url('/mod/psgrading/overview.php', array(
            'cmid' => $this->related['cmid'],
            'userid' => $this->related['userid'],
        ));
        $relateds = array(
            'userid' => $this->related['userid'],
            'isstaff' => $this->related['isstaff'],
        );
        if ($this->related['cmid']) {
            $relateds['cmid'] = $this->related['cmid'];
        } else {
            $baseurl = new \moodle_url('/mod/psgrading/studentoverview.php', array(
                'courseid' => $this->related['courseid'],
                'userid' => $this->related['userid'],
            ));
            $relateds['courseid'] = $this->related['courseid'];
        }

        $gradeexporter = new grade_exporter(null, $relateds);
        $gradedata = $gradeexporter->export($output);
        $tasks = $gradedata->tasks;
        $reportgrades = $gradedata->reportgrades;

        //echo "<pre>"; var_export($gradedata); exit;

        // Group navigation.
        $groups = array();
        foreach ($this->related['groups'] as $i => $groupid) {
            $group = utils::get_group_display_info($groupid);
            $group->overviewurl = clone($baseurl);
            $group->overviewurl->param('groupid', $groupid);
            $group->overviewurl = $group->overviewurl->out(false); // Replace overviewurl with string val.
            $group->iscurrent = false;
            if ($this->related['groupid'] == $group->id) {
                $group->iscurrent = true;
            }
            $groups[] = $group;
        }

        // Student navigation.
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

        $basenavurl = clone($baseurl);
        $basenavurl->param('groupid', 0);
        $basenavurl->param('nav', 'all'); 

        $out = array(
            'tasks' => $tasks,
            'reportgrades' => $reportgrades,
            'groups' => $groups,
            'students' => $students,
            'currstudent' => $currstudent,
            'baseurl' => $baseurl->out(false),
            'basenavurl' => $basenavurl->out(false),
            'nextstudenturl' => $nextstudenturl,
            'prevstudenturl' => $prevstudenturl,
            'isstaff' => $this->related['isstaff'],
        );
        return $out;
    }

}
