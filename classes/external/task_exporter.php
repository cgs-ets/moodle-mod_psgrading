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
 * Provides {@link mod_psgrading\external\task_exporter} class.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\external;

defined('MOODLE_INTERNAL') || die();

use core\external\persistent_exporter;
use renderer_base;
use mod_psgrading\persistents\task;
use mod_psgrading\utils;

/**
 * Exporter of a single task
 */
class task_exporter extends persistent_exporter {

    /**
    * Returns the specific class the persistent should be an instance of.
    *
    * @return string
    */
    protected static function define_class() {
        return task::class; 
    }

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'editurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'markurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'detailsurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'readabletime' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'released' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ],
            'releasecountdown' => [
                'type' => PARAM_INT,
                'multiple' => false,
                'optional' => false,
            ],
            'evidences' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => true,
            ],
            'hasgrades' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ],
            'pypuoilang' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => true,
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
            'userid' => 'int?',
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

        $userid = isset($this->related['userid']) ? $this->related['userid'] : 0;
        
        $editurl = new \moodle_url('/mod/psgrading/task.php', array(
            'cmid' => $this->data->cmid,
            'edit' => $this->data->id,
        ));

        $markurl = new \moodle_url('/mod/psgrading/mark.php', array(
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
            'userid' => $userid,
        ));

        $detailsurl = new \moodle_url('/mod/psgrading/details.php', array(
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
            'userid' => $userid,
        ));

        $readabletime = date('j M Y, g:ia', $this->data->timemodified);

        // Check if released. Time must be in the past but not 0.
        list($released, $releasecountdown) = task::get_release_info($this->data->id);

        // Load task evidences (pre-defined evidences).
        $evidences = task::get_evidences($this->data->id);
        foreach ($evidences as &$evidence) {
            if ($evidence->evidencetype == 'cm') {
                // get the icon and name.
                $cm = get_coursemodule_from_id('', $evidence->refdata);
                $modinfo = get_fast_modinfo($cm->course, $USER->id);
                $cms = $modinfo->get_cms();
                $cm = $cms[$evidence->refdata];
                $evidence->icon = $cm->get_icon_url()->out();
                $evidence->name = $cm->name;

                // Determine the URL depending on the activity, and viewing user's role.
                $isstaff = utils::is_grader();

                // Default.
                $evidence->url = clone($cm->url);

                // Based on activity.
                switch ($cm->modname) 
                {
                    case 'giportfolio':
                        // Custom URL for all users.
                        $evidence->url = new \moodle_url('/mod/giportfolio/viewcontribute.php', array(
                            'id' => $cm->id,
                            'userid' => $userid,
                        ));
                        break;

                    case 'googledocs':
                        // Use default view page for all users.
                        break;

                    case 'assign':
                        // Use default view page for students/parents.
                        if ($isstaff) {
                            // Custom URL for staff.
                            $evidence->url = new \moodle_url('/mod/assign/view.php', array(
                                'id' => $cm->id,
                                'action' => 'grader',
                                'userid' => $userid,
                            ));
                        }
                        break;

                    case 'quiz':
                        // Use default view page for students/parents.
                        if ($isstaff) {
                            // Custom URL for staff.
                            $evidence->url = new \moodle_url('/mod/quiz/grade.php', array(
                                'id' => $cm->id,
                                'userid' => $userid,
                            ));
                        }
                        break;
                }

                $evidence->url = $evidence->url->out(false);
            }
        }

        // Check if this task has grades.
        $hasgrades = task::has_grades($this->data->id);

        $pypuoilang = utils::PYPUOIOPTIONS[strtolower($this->data->pypuoi)] == 'Select' ? '' : utils::PYPUOIOPTIONS[strtolower($this->data->pypuoi)];

    	return [
            'editurl' => $editurl->out(false),
            'markurl' => $markurl->out(false),
            'detailsurl' => $detailsurl->out(false),
	        'readabletime' => $readabletime,
            'released' => $released,
            'releasecountdown' => $releasecountdown,
            'evidences' => $evidences,
            'hasgrades' => $hasgrades,
            'pypuoilang' => $pypuoilang,
	    ];
    }

}
