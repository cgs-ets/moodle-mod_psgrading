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
 * Provides {@link mod_psgrading\external\grade_exporter} class.
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
class grade_exporter extends exporter {

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
            'currstudent' => [
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
            'userid' => 'int',
            'isstaff' => 'bool',
            'includedrafttasks' => 'bool?',
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

        $out = array(
            'tasks' => null,
            'reportgrades' => null,
            'currstudent' => null,
        );

        // Get the current student.
        $currstudent = \core_user::get_user($this->related['userid']);
        utils::load_user_display_info($currstudent);
        $currstudent->iscurrent = false;
        $overviewurl = new \moodle_url('/mod/psgrading/overview.php', array(
            'cmid' => $this->related['cmid'],
            'userid' => $this->related['userid'],
        ));
        $currstudent->overviewurl = $overviewurl->out(false); // Replace overviewurl with string val.
        $currstudent->iscurrent = true;

        $tasks = task::compute_grades($this->related['cmid'], $this->related['userid'], $this->related['includedrafttasks'], $this->related['isstaff']);

        $index = count( $tasks ) - 1;
        if (isset($tasks[$index])) {
            $tasks[$index]->islast = true;
        }

        $reportgrades = array();
        if ($this->related['isstaff']) {
            $reportgrades = task::compute_report_grades($tasks);
        }

        return array(
            'tasks' => $tasks,
            'reportgrades' => $reportgrades,
            'currstudent' => $currstudent,
        );
    }

}
