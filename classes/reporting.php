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

use \mod_psgrading\persistents\task;
use \mod_psgrading\forms\form_reflection;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class reporting {

    const TABLE_REPORTING = 'psgrading_reporting';

    const REPORTENGAGEMENTOPTIONS = array (
        '0' => array (
            'full' => '',
            'minimal' => '',
        ),
        '1' => array (
            'full' => 'Limited Participation',
            'minimal' => 'LP',
        ),
        '2' => array (
            'full' => 'Needs Improvement',
            'minimal' => 'NI',
        ),
        '3' => array (
            'full' => 'Acceptable',
            'minimal' => 'A',
        ),
        '4' => array (
            'full' => 'Very Good',
            'minimal' => 'VG',
        ),
        '5' => array (
            'full' => 'Excellent',
            'minimal' => 'E',
        ),
    );

    public static function get_staff_classes($username, $year, $period) {

        try {

            $config = get_config('mod_psgrading');
            if (empty($config->staffclassessql)) {
                return [];
            }
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->staffclassessql . ' :year, :period, :username';
            $params = array(
                'year' => $year,
                'period' => $period,
                'username' => 41804 //$username
            );

            $staffclasses = $externalDB->get_records_sql($sql, $params);

            return array_values($staffclasses);

        } catch (Exception $ex) {
            // Error.
        }
    }

    public static function get_class_students($classcode, $year, $period) {

        try {

            $config = get_config('mod_psgrading');
            if (empty($config->classstudentssql)) {
                return [];
            }
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->classstudentssql . ' :year, :period, :classcode';
            $params = array(
                'year' => $year,
                'period' => $period,
                'classcode' => $classcode
            );

            $classstudents = $externalDB->get_records_sql($sql, $params);

            return array_values($classstudents);

        } catch (Exception $ex) {
            // Error.
        }
    }

    public static function get_reportelements($assesscode, $yearlevel, $studentreflectionurl) {

        $elements = array();

        if ($yearlevel >= 0) { // Kindy and above.
            switch ($assesscode) {
                case 'CH':
                    $elements[] = array(
                        'subjectarea' => 'Chinese',
                        'type' => 'effort',
                    );
                    break;
                case 'EN':
                    $elements[] = array(
                        'subjectarea' => 'English – reading and viewing',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'English – speaking and listening',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'English – writing',
                        'type' => 'effort',
                    );
                    break;
                case 'IN':
                    $elements[] = array(
                        'subjectarea' => 'HASS',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Science',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Technology',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Media Arts',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Drama',
                        'type' => 'effort',
                    );
                    break;
                case 'MA':
                    $elements[] = array(
                        'subjectarea' => 'Maths – measurement and geometry',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Maths – number and algebra',
                        'type' => 'effort',
                    );
                    $elements[] = array(
                        'subjectarea' => 'Maths – statistics and probability',
                        'type' => 'effort',
                    );
                    break;
                case 'MU':
                    $elements[] = array(
                        'subjectarea' => 'Music',
                        'type' => 'effort',
                    );
                    break;
                case 'ND':
                    $elements[] = array(
                        'subjectarea' => 'Indonesian',
                        'type' => 'effort',
                    );
                    break;
                case 'PE':
                    $elements[] = array(
                        'subjectarea' => 'H&PE',
                        'type' => 'effort',
                    );
                    break;
                case 'VA':
                    $elements[] = array(
                        'subjectarea' => 'Visual Arts',
                        'type' => 'effort',
                    );
                    break;
                case 'OL':
                    $elements[] = array(
                        'subjectarea' => 'Teacher reflection',
                        'type' => 'text',
                    );
                    //if ($yearlevel >= 3) {
                        $elements[] = array(
                            'subjectarea' => 'Student reflection',
                            'type' => 'editor',
                            'url' => $studentreflectionurl->out(false),
                        );
                    //}
                    break;
            }
        } else { // Pre-S to Pre-K
            if ($code == 'OL') {
                $elements[] = array(
                    'subjectarea' => 'Teacher reflection',
                    'type' => 'text',
                );
            }
        }

        return $elements;
    }

    public static function populate_existing_reportelements($courseid, $year, $period, &$students) {
        global $DB;

        foreach ($students as &$sdata) {
            foreach($sdata['reportelements'] as &$element) {
                $conds = array (
                    'courseid' => $courseid,
                    'fileyear' => $year,
                    'reportingperiod' => $period,
                    'studentusername' => $sdata['user']->username,
                    'elementname' => $element['subjectarea'],
                    'elementtype' => $element['type'],
                );
                if ($existing = $DB->get_record('psgrading_reporting', $conds, '*', IGNORE_MULTIPLE)) {
                    // Incorporate existing.
                    if ($existing->elementtype == 'effort') {
                        $element['grade'] = $existing->grade;
                        $element['minimal'] = static::REPORTENGAGEMENTOPTIONS[$existing->grade]['minimal'];
                    } else {
                        if (!empty($existing->reflection)) {
                            $element['reflection'] = $existing->reflection;
                            $element['grade'] = 'text_graded';
                            $element['minimal'] = '';
                        }
                    }
                }
            }
        }
    }

    public static function save_reportelement_effort($courseid, $year, $period, $username, $elname, $eltype, $grade) {
        global $DB, $USER;

        $data = array (
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $elname,
            'elementtype' => $eltype,
        );
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->grade = $grade;
            $existing->reflection = '';
            $existing->reflectionbase64 = '';
            $existing->graderusername = $USER->username;
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['grade'] = $grade;
            $data['reflection'] = '';
            $data['reflectionbase64'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }
        
        return true;

    }

    public static function save_reportelement_text($courseid, $year, $period, $username, $elname, $eltype, $reflection) {
        global $DB, $USER;

        $data = array (
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $elname,
            'elementtype' => $eltype,
        );
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->graderusername = $USER->username;
            $existing->reflection = $reflection;
            $existing->reflectionbase64 = $reflection;
            $existing->grade = '';
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['reflection'] = $reflection;
            $data['reflectionbase64'] = $reflection;
            $data['grade'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }
        
        return true;
    }

    public static function save_reportelement_editor($context, $courseid, $year, $period, $username, $elname, $eltype, $reflection) {
        global $DB, $USER, $CFG;

        $user = \core_user::get_user_by_username($username);

        // Store editor files to permanent file area and get text.
        $reflectiontext = file_save_draft_area_files(
            $reflection['itemid'], 
            $context->id, 
            'mod_psgrading', 
            'reflection', 
            $year . $period . $user->id,
            form_reflection::editor_options(), 
            $reflection['text'],
        );

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($reflectiontext, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($dom->getElementsByTagName('img') as $img) {
            $fs = get_file_storage();
            $fullpath = "/$context->id/mod_psgrading/reflection/" . $year . $period . $user->id . "/" . urldecode(substr($img->getAttribute( 'src' ), 15));

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                continue;
            }

            // Determine the physical location of the file.
            $dir = str_replace('\\\\', '\\', $CFG->dataroot) . 
            '\filedir\\' . substr($file->get_contenthash(), 0, 2) . 
            '\\' . substr($file->get_contenthash(), 2, 2) . 
            '\\';
            $physicalpath = $dir . $file->get_contenthash();

            // Create the base64 string.
            $type = pathinfo($physicalpath, PATHINFO_EXTENSION);
            $data = file_get_contents($physicalpath);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

            // Update the src.
            $img->setAttribute( 'src', $base64 ); 
        }

        $reflectionbase64 = $dom->saveHTML();

        $data = array (
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $elname,
            'elementtype' => $eltype,
        );
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->graderusername = $USER->username;
            $existing->reflection = $reflectiontext;
            $existing->reflectionbase64 = $reflectionbase64;
            $existing->grade = '';
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['reflection'] = $reflectiontext;
            $data['reflectionbase64'] = $reflectionbase64;
            $data['grade'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }
        
        return true;
    }


    public static function get_reportelement_help($courseid, $year, $period, $username, $subjectarea) {
        global $OUTPUT, $DB;

        // Get all psgrading instances for this course.
        // Get the cmids for the mod instances.

        // Get tasks that have this subject in the rubric.
        // Get the student's enagement for these tasks.
        // Display
        // - Taskname, subjectareas (csv), engagement/color

        $username = 51265;

        // Get all psgrading instances for this course.
        $sql = "SELECT id, restrictto
                FROM {psgrading}
                WHERE course = ?
                  AND reportingperiod = ?";
        $modinstances = $DB->get_records_sql($sql, array($courseid, $period));

        $courseinstances = array();
        // Don't include instances that are restricted to specific users.
        foreach($modinstances as $inst) {
            if (empty($inst->restrictto)) {
                $courseinstances[] = $inst->id;
            }
        }
        if (empty($courseinstances)) {
            return;
        }

        // Get the cmids for the mod instances.
        $moduleid = $DB->get_field('modules', 'id', array('name'=> 'psgrading'));
        list($insql, $inparams) = $DB->get_in_or_equal($courseinstances);
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course = ?
                   AND module = ?
                   AND instance $insql";
        $params = array($courseid, $moduleid);
        $cms = $DB->get_records_sql($sql, array_merge($params, $inparams));
        if (empty($cms)) {
            return;
        }


        // Get student engagement for tasks that have this subject in the rubric.
        $relevanttasks = array();
        foreach ($cms as $cm) {
            $sql = "SELECT t.id, t.taskname, tg.engagement
                      FROM {psgrading_tasks} t
                INNER JOIN {psgrading_task_criterions} tc ON t.id = tc.taskid
                INNER JOIN {psgrading_grades} tg on t.id = tg.taskid
                       AND tc.subject = ?
                       AND t.cmid = ?
                       AND t.published = 1
                       AND t.deleted = 0
                       AND tg.studentusername = ?";
            $params = array($subjectarea, $cm->id, $username);
            $cmtasks = $DB->get_records_sql($sql, $params);
            $relevanttasks = array_merge($relevanttasks, $cmtasks);
        }

        // Render as html table.
        $html = $OUTPUT->render_from_template('mod_psgrading/reporting_help', array('tasks' => array_values($relevanttasks)));
        return $html;
    }


    

}        
