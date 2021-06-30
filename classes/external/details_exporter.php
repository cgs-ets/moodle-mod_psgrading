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
use \mod_psgrading\external\mark_exporter;

/**
 * Exporter of a single task
 */
class details_exporter extends exporter {

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
            'gradeinfo' => [
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
            'cmid' => 'int',
            'task' => 'mod_psgrading\persistents\task',
            'students' => 'int[]?',
            'userid' => 'int',
            'detailsurl' => 'moodle_url',
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
        global $USER, $PAGE, $CFG;

        $baseurl = clone($this->related['detailsurl']);

        $taskexporter = new task_exporter($this->related['task']);
        $task = $taskexporter->export($output);

        $isstaff = $this->related['isstaff'];

        // Student navigation.
        $students = array();
        foreach ($this->related['students'] as $i => $studentid) {
            $student = \core_user::get_user($studentid);
            utils::load_user_display_info($student);
            $student->iscurrent = false;
            $student->detailsurl = clone($baseurl);
            $student->detailsurl->param('userid', $student->id);
            $student->detailsurl = $student->detailsurl->out(false); // Replace markurl with string val.
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
        foreach ($task->criterions as $i => $criterion) {
            if ($criterion->hidden) {
                unset($task->criterions[$i]);
                continue;
            }
            // Add selections.
            if (isset($gradeinfo->criterions[$criterion->id]) && $task->released) {
                // There is a gradelevel chosen for this criterion.
                $criterion->{'level' . $gradeinfo->criterions[$criterion->id]->gradelevel . 'selected'} = true;
            }
        }

        // Zero indexes so templates work.
        $task->criterions = array_values($task->criterions);

        if ($task->released) {
            // Get selected MyConnect grade evidences.
            $task->myconnectevidences = array();
            $task->myconnectevidencejson = '';
            $myconnectids = array();
            if ($gradeinfo) {
                // Get selected ids
                $myconnectids = task::get_myconnect_grade_evidences($gradeinfo->id);
                if ($myconnectids) {
                    // Convert to json.
                    $task->myconnectevidencejson = json_encode($myconnectids);
                }
                // Get full post objects for selected ids.
                $myconnectdata = utils::get_myconnect_data_for_postids($currstudent->username, $myconnectids);
                if (isset($myconnectdata->posts)) {
                    $task->myconnectevidences = array_values($myconnectdata->posts);
                }
            }

            // Add additional evidences for this user.
            $modulecontext = \context_module::instance($this->related['cmid']);
            $fs = get_file_storage();
            $uniqueid = sprintf( "%d%d", $task->id, $this->related['userid'] ); // Join the taskid and userid to make a unique itemid.
            $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
            if ($files) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    //$mimetype = $file->get_mimetype();
                    //$iconimage = $output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
                    $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$modulecontext->id.'/mod_psgrading/evidences/'.$uniqueid.'/'.$filename);
                    //$isimage = in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png')) ? 1 : 0;
                    $task->evidences[] = array(
                        'icon' => $path . '?preview=thumb',
                        'url' => $path,
                        'name' => $filename,
                    );
                }
            }
        } else {
            // Unset some things.
            unset($gradeinfo->engagement);
            unset($gradeinfo->engagementlang);
        }

        return array(
            'task' => $task,
            'students' => $students,
            'currstudent' => $currstudent,
            'nextstudenturl' => $nextstudenturl,
            'prevstudenturl' => $prevstudenturl,
            'gradeinfo' => $gradeinfo,
            'isstaff' => $isstaff,
        );

    }

}
