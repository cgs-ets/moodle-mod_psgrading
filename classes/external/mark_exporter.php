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
            'task' => 'mod_psgrading\persistents\task',
            'students' => 'int[]?',
            'userid' => 'int',
            'usergrades' => 'stdClass?',
            'markurl' => 'moodle_url?'
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
		$taskexporter = new task_exporter($this->related['task']);
		$task = $taskexporter->export($output);

        $currstudent = null;
        $nextstudenturl = null;
        $prevstudenturl = null;
        $students = array();
        foreach ($this->related['students'] as $i => $studentid) {
            $student = \core_user::get_user($studentid);
            utils::load_user_display_info($student);
            $student->isselected = false;
            if ($this->related['userid'] == $student->id) {
                $currstudent = $student;
                $len = count($this->related['students']);
                if ($len > 1) {
                    // Base url.
                    $nextstudenturl = $this->related['markurl'];
                    $prevstudenturl = $this->related['markurl'];

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

        if ($this->related['usergrades']) {
            $grades = $this->related['usergrades'];
            // if user marks have been supplied to the exporter, incorporate this and more info with the task data.
            task::load_criterions($task);
            foreach ($task->criterions as $criteron) {
                // add marks to criterion definitions.
                if (isset($grades->criterions[$criteron->id])) {
                    // There is a gradelevel chosen for this criterion.
                    $criteron->{'level' . $grades->criterions[$criteron->id]->gradelevel . 'selected'} = true;
                }
            }

            //task::load_evidences($task);

        }

        return array(
            'task' => $task,
            'students' => $students,
            'currstudent' => $currstudent,
            'nextstudenturl' => $nextstudenturl,
            'prevstudenturl' => $prevstudenturl,
        );
    }

}
