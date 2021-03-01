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
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/mod/wiki/diff/difflib.php'); // Use wiki's diff lib.

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class utils {

    const SUBJECTOPTIONS = array (
        array (
            'txt' => 'Subject',
            'val' => '',
            'attrs' => 'disabled selected',
        ),
        array (
            'txt' => 'English',
            'val' => 'English',
        ),
        array (
            'txt' => 'HAAS',
            'val' => 'HAAS',
        ),
        array (
            'txt' => 'Science',
            'val' => 'Science',
        ),
        array (
            'txt' => 'Technology',
            'val' => 'Technology',
        ),
        array (
            'txt' => 'Maths',
            'val' => 'Maths',
        ),
        array (
            'txt' => 'H&PE',
            'val' => 'H&PE',
        ),
        array (
            'txt' => 'VisArts',
            'val' => 'VisArts',
        ),
        array (
            'txt' => 'Language',
            'val' => 'Language',
        ),
        array (
            'txt' => 'Music',
            'val' => 'Music',
        ),
    );

    const WEIGHTOPTIONS = array (
        array (
            'txt' => 'Weight',
            'val' => '',
            'attrs' => 'disabled selected',
        ),
        array (
            'txt' => '5%',
            'val' => '5',
        ),
        array (
            'txt' => '10%',
            'val' => '10',
        ),
        array (
            'txt' => '15%',
            'val' => '15',
        ),
        array (
            'txt' => '20%',
            'val' => '20',
        ),
        array (
            'txt' => '25%',
            'val' => '25',
        ),
        array (
            'txt' => '30%',
            'val' => '30',
        ),
        array (
            'txt' => '35%',
            'val' => '35',
        ),
        array (
            'txt' => '40%',
            'val' => '40',
        ),
        array (
            'txt' => '45%',
            'val' => '45',
        ),
        array (
            'txt' => '50%',
            'val' => '50',
        ),
        array (
            'txt' => '55%',
            'val' => '55',
        ),
        array (
            'txt' => '60%',
            'val' => '60',
        ),
        array (
            'txt' => '65%',
            'val' => '65',
        ),
        array (
            'txt' => '70%',
            'val' => '70',
        ),
        array (
            'txt' => '75%',
            'val' => '75',
        ),
        array (
            'txt' => '80%',
            'val' => '80',
        ),
        array (
            'txt' => '85%',
            'val' => '85',
        ),
        array (
            'txt' => '90%',
            'val' => '90',
        ),
        array (
            'txt' => '95%',
            'val' => '95',
        ),
        array (
            'txt' => '100%',
            'val' => '100',
        ),
    );

    public static function decorate_subjectdata($rubricdata) {
        foreach ($rubricdata as $i => $row) {
            $rubricdata[$i]->subject = array(
                'value' => $row->subject,
                'options' => static::get_subject_options_with_selected($row->subject),
            );
        }
        return $rubricdata;
    }

    public static function get_subject_options_with_selected($selected) {
        $options = array();
        foreach (static::SUBJECTOPTIONS as $i => $option) {
            if ($option['val'] === $selected) {
                $option['sel'] = true;
            }
            $options[] = $option;
        };
        return $options;
    }


    public static function decorate_weightdata($rubricdata) {
        foreach ($rubricdata as $i => $row) {
            $weight = isset($row->weight) ? $row->weight : '';
            $rubricdata[$i]->weight = array(
                'value' => $weight,
                'options' => static::get_weight_options_with_selected($weight),
            );
        }
        return $rubricdata;
    }

    public static function get_weight_options_with_selected($selected) {
        $options = array();
        foreach (static::WEIGHTOPTIONS as $i => $option) {
            if ($option['val'] === $selected) {
                $option['sel'] = true;
            }
            $options[] = $option;
        };
        return $options;
    }

    public static function get_stub_criterion() {
        $criterion = new \stdClass();
        $criterion->subject = array(
            'value' => '',
            'options' => static::SUBJECTOPTIONS,
        );
        $criterion->weight = array(
            'value' => '',
            'options' => static::WEIGHTOPTIONS,
        );
        return $criterion;
    }

    public static function get_evidencedata($course, $evidencejson) {
        global $USER;

        // Already selected activities.
        $selectedcms = array();
        $evidencejson = json_decode($evidencejson);
        if ($evidencejson) {
            $selectedcms = array_column($evidencejson, 'data');
        }
        $activities = array();
        $modinfo = get_fast_modinfo($course, $USER->id);
        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $cmrec = $cm->get_course_module_record(true);
            if ($cmrec->deletioninprogress) { // Don't include deleted activities.
                continue;
            }
            // Don't include self, and resources.
            if (in_array($cmrec->modname, array(
                    'psgrading', 
                    'resource', 
                    'folder', 
                    'book', 
                    'label', 
                    'page', 
                    'url',
                    'clickview',
                    'unilabel',
                    'zoom',
                ))) {
                continue;
            }
            //$cmrec->icon = $OUTPUT->pix_icon('icon', $cmrec->name, $cmrec->modname, array('class'=>'icon'));
            $cmrec->icon = $cm->get_icon_url()->out();
            $cmrec->url = $cm->url;
            if (in_array($cmrec->id, $selectedcms)) {
                $cmrec->sel = true;
            }
            $activities[] = $cmrec;
        }
        return $activities;
    }

    public static function get_taskdata_as_xml($data) {
        $xml = "<taskname>{$data->taskname}</taskname>";
        $xml .= "<pypuoi>{$data->pypuoi}</pypuoi>";
        $xml .= "<outcomes>{$data->outcomes}</outcomes>";
        $xml .= "<rubricjson>{$data->rubricjson}</rubricjson>";

        return $xml;
    }

    /* TODO: Uses mod_wikis diff lib */
    public static function diff_versions($json1, $json2) {
        global $DB, $PAGE;
        $olddata = json_decode($json1);
        $newdata = json_decode($json2);

        $oldxml = static::get_taskdata_as_xml($olddata);
        $newxml = static::get_taskdata_as_xml($newdata);

        list($diff1, $diff2) = ouwiki_diff_html($oldxml, $newxml);

        $diff1 = format_text($diff1, FORMAT_HTML, array('overflowdiv'=>true));
        $diff2 = format_text($diff2, FORMAT_HTML, array('overflowdiv'=>true));

        // Mock up the data needed by the wiki renderer.
        $wikioutput = $PAGE->get_renderer('mod_wiki');
        $oldversion = array(
            'id' => 1111, // Use log id.
            'pageid' => $olddata->id,
            'content' => $oldxml,
            'contentformat' => 'html',
            'version' => 1111, // Use log id.
            'timecreated' => 1613693887,
            'userid' => 2,
            'diff' => $diff1,
            'user' => $DB->get_record('user', array('id' => 2)),
        );
        $newversion = array(
            'id' => 1112, // Use log id.
            'pageid' => $newdata->id,
            'content' => $newxml,
            'contentformat' => 'html',
            'version' => 1112, // Use log id.
            'timecreated' => 1613693887,
            'userid' => 2,
            'diff' => $diff2,
            'user' => $DB->get_record('user', array('id' => 2)),
        );

        echo $wikioutput->diff($newdata->id, (object) $oldversion, (object) $newversion, array('total' => 9999));

    }



}