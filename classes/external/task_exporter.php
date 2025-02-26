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
class task_exporter extends persistent_exporter
{

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class()
    {
        return task::class;
    }

    /**
     * Return the list of additional properties.
     *
     * Calculated values or properties generated on the fly based on standard properties and related data.
     *
     * @return array
     */
    protected static function define_other_properties()
    {
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
            'qmarkurl' => [
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
    protected static function define_related()
    {
        return [
            'userid' => 'int?',
        ];
    }

    /*
    * Check if the cm is available to the student.
    */
    private function is_cm_available_for_userid($cm, $userid) {
        if (!$cm->visible) {
            return false;
        }

        // Relevant docs: https://docs.moodle.org/dev/Availability_API
        // Relevant git: https://github.com/moodle/moodle/blob/master/availability/classes/info_module.php
        $info = new \core_availability\info_module($cm);
        $user = \core_user::get_user($userid);
        $filtered = $info->filter_user_list([$userid => $user]);
        if (empty($filtered)) {
            // User is not allowed to view this.
            return false;
        }

        return true;
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $USER, $DB;

        $userid = isset($this->related['userid']) ? $this->related['userid'] : 0;

        $editurl = new \moodle_url('/mod/psgrading/task.php', [
            'cmid' => $this->data->cmid,
            'edit' => $this->data->id,
        ]);

        $markurl = new \moodle_url('/mod/psgrading/mark.php', [
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
            'userid' => $userid,
        ]);

        $qmarkurl = new \moodle_url('/mod/psgrading/quickmark.php', [
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
            'userid' => $userid,
        ]);

        $detailsurl = new \moodle_url('/mod/psgrading/details.php', [
            'cmid' => $this->data->cmid,
            'taskid' => $this->data->id,
            'userid' => $userid,
        ]);

        $readabletime = date('j M Y, g:ia', $this->data->timemodified);

        // Check if released. Time must be in the past but not 0.
        list($released, $releasecountdown) = task::get_release_info($this->data->id);

        // Used to determine URLs below.
        $isstaff = utils::is_grader();

        // Load task evidences (pre-defined evidences).
        $evidences = task::get_evidences($this->data->id);
        foreach ($evidences as $i => &$evidence) {
            if ($evidence->evidencetype == 'cm' || substr($evidence->evidencetype, 0, 3) === "cm_") {
                // Things are a bit different for cm_giportfoliochapter
                if ($evidence->evidencetype == 'cm_giportfoliochapter') {
                    $split = explode('_', $evidence->refdata);
                    $cmid = $split[0];
                    $cminstance = $split[1];
                    $chapterid = $split[2];
                    // get the cm data
                    $cm = get_coursemodule_from_id('', $cmid);
                    $modinfo = get_fast_modinfo($cm->course, $USER->id);
                    $cms = $modinfo->get_cms();
                    $cm = $cms[$cmid];

                    if (! $this->is_cm_available_for_userid($cm, $userid)) {
                        unset($evidences[$i]);
                        continue;
                    }

                    // Get the chapter.
                    $sql = "SELECT *
                            FROM {giportfolio_chapters}
                            WHERE id = ?";
                    $chapter = $DB->get_record_sql($sql, [$chapterid]);
                    if (empty($chapter)) {
                        unset($evidences[$i]);
                        continue;
                    }
                    // Icon
                    $evidence->icon = $cm->get_icon_url()->out();
                    // Name
                    $evidence->name = $cm->name . ' → ' . $chapter->title;
                    // URL
                    $evidence->url = new \moodle_url('/mod/giportfolio/viewgiportfolio.php', [
                        'id' => $cmid,
                        'chapterid' => $chapterid,
                        'mentee' => $userid,
                    ]);
                    $evidence->url = $evidence->url->out(false);
                } else {
                    // Evidence type is "cm" or "cm_something" but these are handled the same
                    // get the cm data
                    $cm = get_coursemodule_from_id('', $evidence->refdata);
                    if(!$cm->course) { // Evidence was deleted and its returning null.
                        continue;
                    }
                    $modinfo = get_fast_modinfo($cm->course, $USER->id);
                    $cms = $modinfo->get_cms();
                    $cm = $cms[$evidence->refdata];

                    if (! $this->is_cm_available_for_userid($cm, $userid)) {
                        unset($evidences[$i]);
                        continue;
                    }
                    // Icon
                    $evidence->icon = $cm->get_icon_url()->out();
                    // Name
                    $evidence->name = $cm->name;

                    // Default.
                    $evidence->url = clone ($cm->url);

                    // Based on activity.
                    switch ($cm->modname) {
                            // For historical purposes. The overarching activity cannot be selected anymore.
                        case 'giportfolio':
                            // Custom URL for all users.
                            $evidence->url = new \moodle_url('/mod/giportfolio/viewcontribute.php', [
                                'id' => $cm->id,
                                'userid' => $userid,
                            ]);
                            break;

                        case 'googledocs':
                            // Use default view page for all users.
                            break;

                        case 'assign':
                            // Use default view page for students/parents.
                            if ($isstaff) {
                                // Custom URL for staff.
                                $evidence->url = new \moodle_url('/mod/assign/view.php', [
                                    'id' => $cm->id,
                                    'action' => 'grader',
                                    'userid' => $userid,
                                ]);
                            }
                            break;

                        case 'quiz':
                            // Use default view page for students/parents.
                            if ($isstaff) {
                                // Custom URL for staff.
                                $evidence->url = new \moodle_url('/mod/quiz/grade.php', [
                                    'id' => $cm->id,
                                    'userid' => $userid,
                                ]);
                            }
                            break;
                        case 'website':

                            $sql = "SELECT w.*
                                    FROM {course_modules} cm
                                    JOIN {website} w ON cm.instance = w.id
                                    WHERE cm.id = :cmid;";

                            $website = $DB->get_record_sql($sql, ['cmid' => $cm->id]);

                            if ($website->distribution == 2) { // Page.

                                $sql = "SELECT sp.id as pageid, ws.id as siteid, ws.cmid
                                        FROM {website_permissions} wp
                                        JOIN {website_site_pages} sp ON wp.resourcekey = sp.id
                                        JOIN {website_sites} ws ON ws.id = sp.siteid
                                        WHERE wp.userid =:userid AND ws.cmid = :cmid;";

                                $page = $DB->get_record_sql($sql, [ 'userid' => $userid, 'cmid' => $cm->id]);

                                $evidence->url = new \moodle_url('/mod/website/site.php', [
                                    'site' => $page->siteid,
                                    'page' => $page->pageid,
                                ]);


                            } else if ($website->distribution == 1) { // Site.

                                $sql = "SELECT id
                                        FROM {website_sites}
                                        WHERE cmid = :cmid
                                        AND userid = :userid;";

                                $site = $DB->get_record_sql($sql, ['cmid' => $cm->id, 'userid' => $userid]);

                                $evidence->url = new \moodle_url('/mod/website/site.php', [
                                    'site' => $site->id,
                                ]);
                            } else {
                                $sql = "SELECT id
                                        FROM {website_sites}
                                        WHERE cmid = :cmid";
                                $site = $DB->get_record_sql($sql, ['cmid' => $cm->id]);

                                $evidence->url = new \moodle_url('/mod/website/site.php', [
                                    'site' => $site->id,
                                ]);
                            }

                            break;
                    }
                    $evidence->url = $evidence->url->out(false);
                }
            }
        }
        $evidences = array_values($evidences);

        // Check if this task has grades.
        $hasgrades = task::has_grades($this->data->id);

        $pypuoilang = utils::PYPUOIOPTIONS[strtolower($this->data->pypuoi)] == 'Select' ? '' : utils::PYPUOIOPTIONS[strtolower($this->data->pypuoi)];

        return [
            'editurl' => $editurl->out(false),
            'markurl' => $markurl->out(false),
            'qmarkurl' => $qmarkurl->out(false),
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
