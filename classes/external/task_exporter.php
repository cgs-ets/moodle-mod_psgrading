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
            'readabletime' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'draftdata' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        $editurl = new \moodle_url('/mod/psgrading/task.php', array(
            'cmid' => $this->data->cmid,
            'edit' => $this->data->id,
        ));

        $markurl = new \moodle_url('/mod/psgrading/mark.php', array(
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
        ));

        $readabletime = date('j M Y, g:ia', $this->data->timemodified);

        $draftdata = json_decode($this->data->draftjson);

    	return [
            'editurl' => $editurl->out(false),
            'markurl' => $markurl->out(false),
	        'readabletime' => $readabletime,
	        'draftdata' => $draftdata,
	    ];
    }

}
