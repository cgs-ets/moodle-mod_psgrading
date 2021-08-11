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
use \mod_psgrading\persistents\task;

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
            'listhtml' => [
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
            'baseurl' => [
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
        global $DB;

        // Group navigation. 
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

        $taskcreateurl = new \moodle_url('/mod/psgrading/task.php', array(
            'cmid' => $this->related['cmid'],
            'edit' => 0,
        ));

        // Check if there is a cached version of the student rows.
        $listhtml = null;
        $cache = utils::get_cache($this->related['cmid'], 'list-' . $this->related['groupid']);
        if ($cache) {
            $listhtml = $cache->value;
        } else {
            $studentoverviews = array();
            // Export the grade overviews afresh.
            foreach ($this->related['students'] as $studentid) {
                $relateds = array(
                    'cmid' => $this->related['cmid'],
                    'userid' => $studentid,
                    'isstaff' => true, // Only staff can view the class list page.
                    'includehiddentasks' => true,
                );
                $gradeexporter = new grade_exporter(null, $relateds);
                $gradedata = $gradeexporter->export($output);
                $studentoverviews[] = $gradedata;
            }
            // Prerender and cache it.
            $listhtml = $output->render_from_template('mod_psgrading/list_table', array(
                'studentoverviews' => $studentoverviews,
                'taskcreateurl' => $taskcreateurl->out(false),
            ));
            if ($listhtml) {
                utils::save_cache($this->related['cmid'], 'list-' . $this->related['groupid'], $listhtml);
            }
        }



        return array(
            'listhtml' => $listhtml,
            'groups' => $groups,
            'basenavurl' => $basenavurl->out(false),
            'baseurl' => $baseurl->out(false),
        );

    }

}
