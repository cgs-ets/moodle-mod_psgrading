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
 * Provides {@link mod_psgrading\external\course_exporter} class.
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
class course_exporter extends exporter {

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
            'reportingperiods' => [
                'type' => PARAM_RAW,
                'multiple' => true,
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
            'reportingurl' => [
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
            'courseid' => 'int',
            'groups' => 'int[]?',
            'students' => 'int[]?',
            'groupid' => 'int',
            'reportingperiod' => 'int',
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

        $baseurl = new \moodle_url('/mod/psgrading/courseoverview.php', array(
            'courseid' => $this->related['courseid'],
            'reporting' => $this->related['reportingperiod'],
        ));

        // Group navigation. 
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

        // Reporting period navigation. 
        $rps = array();
        for ($i = 1; $i <= 2; $i++) {
            $rp = new \stdClass();
            $rp->value = $rp->name = $i;
            $rp->viewurl = clone($baseurl);
            $rp->viewurl->param('reporting', $i);
            $rp->viewurl = $rp->viewurl->out(false); // Replace viewurl with string val.
            $rp->iscurrent = false;
            if ($this->related['reportingperiod'] == $i) {
                $rp->iscurrent = true;
            }
            $rps[] = $rp;
        }

        $basenavurl = clone($baseurl);
        $basenavurl->param('groupid', 0);
        $basenavurl->param('reporting', $this->related['reportingperiod']);
        $basenavurl->param('nav', 'all');

        // Check if there is a cached version of the student rows.
        $listhtml = null;
        $cache = utils::get_cache($this->related['courseid'], 'list-course-' . $this->related['reportingperiod'] . '-' . $this->related['groupid']);
        if ($cache) {
            $listhtml = $cache->value;
        } else {
            $studentoverviews = array();
            // Export the grade overviews afresh.
            foreach ($this->related['students'] as $studentid) {
                $relateds = array(
                    'courseid' => $this->related['courseid'],
                    'userid' => $studentid,
                    'isstaff' => true, // Only staff can view the class list page.
                    'includehiddentasks' => true,
                    'reportingperiod' => $this->related['reportingperiod'],
                );
                $gradeexporter = new grade_exporter(null, $relateds);
                $gradedata = $gradeexporter->export($output);
                $studentoverviews[] = $gradedata;
            }

            // Add psgrading instance titles above tasks.
            $cms = array();
            if ( !empty($studentoverviews) && !empty($studentoverviews[0]->tasks) ) {
                $processingcmid = 0;
                $width = 0;
                foreach($studentoverviews[0]->tasks as $task) {
                    if ($task->cmid != $processingcmid) {
                        if ($processingcmid != 0) {
                            // Save the cm.
                            $cmtitle = $DB->get_field_sql(
                                'SELECT p.name 
                                 FROM {psgrading} p, {course_modules} c
                                 WHERE c.id = ?
                                 AND c.course = p.course
                                 AND c.instance = p.id', 
                                 array($processingcmid)
                            );
                            $viewurl = new \moodle_url('/mod/psgrading/view.php', array(
                                'id' => $processingcmid,
                                'groupid' =>$this->related['groupid'],
                            ));
                            $cms[] = array(
                                'cmid' => $processingcmid,
                                'overviewurl' => $viewurl->out(false),
                                'title' => $cmtitle,
                                'width' => $width,
                            );
                        }
                        $processingcmid = $task->cmid;
                        $width = 1;
                    } else {
                        $width++;
                    }
                }
                // Save the last one.
                $lasttask = end($studentoverviews[0]->tasks);
                $cmtitle = $DB->get_field_sql(
                    'SELECT p.name 
                     FROM {psgrading} p, {course_modules} c
                     WHERE c.id = ?
                     AND c.course = p.course
                     AND c.instance = p.id', 
                     array($lasttask->cmid)
                );
                $viewurl = new \moodle_url('/mod/psgrading/view.php', array(
                    'id' => $lasttask->cmid,
                    'groupid' =>$this->related['groupid'],
                ));
                $cms[] = array(
                    'cmid' => $lasttask->cmid,
                    'overviewurl' => $viewurl->out(false),
                    'title' => $cmtitle,
                    'width' => $width,
                );
            }
            //var_export($cms);
            //exit;
            
            // Prerender and cache it.
            $listhtml = $output->render_from_template('mod_psgrading/list_table', array(
                'studentoverviews' => $studentoverviews,
                'cms' => $cms,
                //'taskcreateurl' => '', // Tasks must be created in the context of a single instance.
                //'courseoverviewurl' => '', // Not needed because we are already in the course overview.
            ));
            if ($listhtml) {
                utils::save_cache($this->related['courseid'], 'list-course-' . $this->related['reportingperiod'] . '-' . $this->related['groupid'], $listhtml);
            }
        }

        $reportingurl = new \moodle_url('/mod/psgrading/reporting.php', array(
            'courseid' => $this->related['courseid'],
            'year' => date('Y'),
            'period' => $this->related['reportingperiod'],
        ));

        return array(
            'listhtml' => $listhtml,
            'reportingperiods' => $rps,
            'groups' => $groups,
            'basenavurl' => $basenavurl->out(false),
            'baseurl' => $baseurl->out(false),
            'reportingurl' => $reportingurl->out(false),
        );

    }

}
