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

        // Export mark data.
        $markrelateds = array(
            'task' => $this->related['task'],
            'students' => $this->related['students'],
            'userid' => $this->related['userid'],
            'markurl' => $this->related['detailsurl'],
        );      
        $markexporter = new mark_exporter(null, $markrelateds);
        $out = $markexporter->export($output);

        $out->isstaff = $this->related['isstaff'];

        // Add additional evidences for this user.
        $modulecontext = \context_module::instance($this->related['cmid']);
        $fs = get_file_storage();
        $uniqueid = sprintf( "%d%d", $out->task->id, $this->related['userid'] ); // Join the taskid and userid to make a unique itemid.
        $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
        if ($files) {
            foreach ($files as $file) {
                $filename = $file->get_filename();
                //$mimetype = $file->get_mimetype();
                //$iconimage = $output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$modulecontext->id.'/mod_psgrading/evidences/'.$uniqueid.'/'.$filename);
                //$isimage = in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png')) ? 1 : 0;
                $out->task->evidences[] = array(
                    'icon' => $path . '?preview=thumb',
                    'url' => $path,
                    'name' => $filename,
                );
            }
        }

        // Remove hidden criterions for non-staff.
        if (!$this->related['isstaff']) {
            foreach ($out->task->criterions as $i => $criterion) {
                if ($criterion->hidden) {
                    unset($out->task->criterions[$i]);
                }
            }
        }
        $out->task->criterions = array_values($out->task->criterions);
        // Engagement.
        $out->gradeinfo->engagement = $out->gradeinfo->engagement ? utils::ENGAGEMENTOPTIONS[$out->gradeinfo->engagement] : '';

        return (array) $out;
    }

}
