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
 * Provides {@link mod_psgrading\external\list_exporter} class.
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

/**
 * Exporter of a single task
 */
class list_exporter extends exporter {

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'studentoverviews' => [
                'type' => overview_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'taskcreateurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'groups' => [
                'type' => PARAM_RAW,
                'multiple' => true,
                'optional' => false,
            ],
            'basenavurl' => [
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
            'groups' => 'int[]?',
            'students' => 'int[]?',
            'groupid' => 'int',
            'tasks' => 'mod_psgrading\persistents\task[]',
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {

        // Group navigation. TODO: take grade exporting out of overview exporter so that we don't export group nav for every student overview.
        $baseurl = new \moodle_url('/mod/psgrading/view.php', array(
            'id' => $this->related['cmid']
        ));
        $groups = array();
        foreach ($this->related['groups'] as $i => $groupid) {
            $group = utils::get_group_display_info($groupid);
            $group->viewurl = clone($baseurl);
            $group->viewurl->param('groupid', $groupid);
            $group->viewurl = $group->viewurl->out(false); // Replace viewurl with string val.
            $group->iscurrent = false;
            if ($this->related['groupid'] == $group->id) {
                $group->iscurrent = true;
            }
            $groups[] = $group;
        }
        $basenavurl = clone($baseurl);
        $basenavurl->param('groupid', 0);
        $basenavurl->param('nav', 'all'); 

        $studentoverviews = array();
        foreach ($this->related['students'] as $studentid) {
            // Export the overview for the student.
            $relateds = array(
                'cmid' => $this->related['cmid'],
                'groups' => $this->related['groups'],
                'students' => $this->related['students'],
                'userid' => $studentid,
                'groupid' => $this->related['groupid'],
                'isstaff' => true, // Only staff can view the class list page.
                'includedrafttasks' => true,
            );
            $overviewexporter = new overview_exporter(null, $relateds);
            $studentoverviews[] = $overviewexporter->export($output);
        }

        $taskcreateurl = new \moodle_url('/mod/psgrading/task.php', array(
            'cmid' => $this->related['cmid'],
            'create' => 1,
        ));

        return array(
            'studentoverviews' => $studentoverviews,
            'taskcreateurl' => $taskcreateurl->out(false),
            'groups' => $groups,
            'basenavurl' => $basenavurl->out(false),
        );

    }

}
