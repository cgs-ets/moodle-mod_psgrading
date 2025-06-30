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

use mod_psgrading\persistents\task;
use mod_psgrading\forms\form_reflection;
use mod_psgrading\forms\form_treflection;

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

    const REPORTENGAGEMENTOPTIONS = [
        '0' => [
            'full' => '',
            'minimal' => '',
        ],
        '1' => [
            'full' => 'Limited Participation',
            'minimal' => 'LP',
        ],
        '2' => [
            'full' => 'Needs Improvement',
            'minimal' => 'NI',
        ],
        '3' => [
            'full' => 'Acceptable',
            'minimal' => 'A',
        ],
        '4' => [
            'full' => 'Very Good',
            'minimal' => 'VG',
        ],
        '5' => [
            'full' => 'Excellent',
            'minimal' => 'E',
        ],
    ];

    public static function get_staff_classes($username, $year, $period) {

        try {

            $config = get_config('mod_psgrading');
            if (empty($config->staffclassessql)) {
                return [];
            }
            $externaldb = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externaldb->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->staffclassessql . ' :year, :period, :username';
            $params = [
                'year' => $year,
                'period' => $period,
                'username' => $username, // 41804
            ];

            $staffclasses = $externaldb->get_records_sql($sql, $params);

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
            $externaldb = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externaldb->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->classstudentssql . ' :year, :period, :classcode';
            $params = [
                'year' => $year,
                'period' => $period,
                'classcode' => $classcode,
            ];

            $classstudents = $externaldb->get_records_sql($sql, $params);

            return array_values($classstudents);

        } catch (Exception $ex) {
            // Error.
        }
    }

    public static function get_reportelements($assesscode, $yearlevel, $studentreflectionurl, $teacherreflectionurl) {

        $elements = [];

        if ($yearlevel >= 0 && $yearlevel <= 6) {// K-6.
            switch ($assesscode) {
                // case 'CH': TODO: From 2025 Effort is sync from the mdl_psgradin_gradesync table;
                //     $elements[] = [
                //         'subjectarea' => 'Chinese',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'EN':
                //     $elements[] = [
                //         'subjectarea' => 'English – reading and viewing',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'English – speaking and listening',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'English – writing',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'IN':
                //     $elements[] = [
                //         'subjectarea' => 'HASS',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Science',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Technology',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Media Arts',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Drama',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'MA':
                //     $elements[] = [
                //         'subjectarea' => 'Maths – measurement and geometry',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Maths – number and algebra',
                //         'type' => 'effort',
                //     ];
                //     $elements[] = [
                //         'subjectarea' => 'Maths – statistics and probability',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'MU':
                //     $elements[] = [
                //         'subjectarea' => 'Music',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'ND':
                //     $elements[] = [
                //         'subjectarea' => 'Indonesian',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'PE':
                //     $elements[] = [
                //         'subjectarea' => 'H&PE',
                //         'type' => 'effort',
                //     ];
                //     break;
                // case 'VA':
                //     $elements[] = [
                //         'subjectarea' => 'Visual Arts',
                //         'type' => 'effort',
                //     ];
                //     break;
                case 'OL': // Core class
                    $elements[] = [
                        'subjectarea' => 'Teacher reflection',
                        'type' => 'text',
                    ];
                    if ($yearlevel < 3) { // K - Year 2.
                        $elements[] = [
                            'subjectarea' => 'Student reflection',
                            'type' => 'text',
                            'type' => 'form',
                            'url' => $studentreflectionurl->out(false),
                        ];
                    } else { // Year 3 - 6.
                        // $studentreflectionurl->param('type', 'editor');
                        $elements[] = [
                            'subjectarea' => 'Student reflection',
                            'type' => 'text',
                            // 'type' => 'editor',
                            // 'url' => $studentreflectionurl->out(false),
                        ];
                    }
                    break;
            }
        } else { // Pre-S to Pre-K
            if ($assesscode == 'OL') { // Core class
                $elements[] = [
                    'subjectarea' => 'Teacher reflection',
                    'type' => 'form',
                    'url' => $teacherreflectionurl->out(false),
                ];

                $elements[] = [
                    'subjectarea' => 'Student reflection',
                    'type' => 'text',
                    'type' => 'form',
                    'url' => $studentreflectionurl->out(false),
                ];
            }
        }

        return $elements;
    }

    public static function populate_existing_reportelements($courseid, $year, $period, &$students) {
        global $DB;

        foreach ($students as &$sdata) {
            foreach($sdata['reportelements'] as &$element) {
                $subjectsanitised = strtolower(str_replace([' ', '&', '–'], '', $element['subjectarea']));
                $conds = [
                    'courseid' => $courseid,
                    'fileyear' => $year,
                    'reportingperiod' => $period,
                    'studentusername' => $sdata['user']->username,
                    'elementname' => $subjectsanitised,
                    'elementtype' => $element['type'],
                ];
                if ($existing = $DB->get_record('psgrading_reporting', $conds, '*', IGNORE_MULTIPLE)) {
                    // Incorporate existing.
                    if ($existing->elementtype == 'effort') {
                        $element['grade'] = $existing->grade;
                        $element['minimal'] = static::REPORTENGAGEMENTOPTIONS[$existing->grade]['minimal'];
                    } else {
                        if (!empty($existing->reflection) ||
                            !empty($existing->reflectionimagepath) ||
                            !empty($existing->reflection2) ||
                            !empty($existing->reflection3) ||
                            !empty($existing->reflection4) ||
                            !empty($existing->reflection5)
                        ) {
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

        $subjectsanitised = strtolower(str_replace([' ', '&', '–'], '', $elname));

        $data = [
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $subjectsanitised,
            'elementtype' => $eltype,
        ];
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->grade = $grade;
            $existing->reflection = '';
            $existing->graderusername = $USER->username;
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['grade'] = $grade;
            $data['reflection'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }

        return true;

    }

    public static function save_reportelement_text($courseid, $year, $period, $username, $elname, $eltype, $reflection) {
        global $DB, $USER;

        $subjectsanitised = strtolower(str_replace([' ', '&', '–'], '', $elname));

        $data = [
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $subjectsanitised,
            'elementtype' => $eltype,
        ];
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->graderusername = $USER->username;
            $existing->reflection = $reflection;
            $existing->grade = '';
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['reflection'] = $reflection;
            $data['grade'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }

        return true;
    }

    public static function save_reportelement_textareas($context, $courseid, $year, $period, $username, $elname, $eltype, $formdata) {
        global $DB, $USER, $CFG;

        $user = \core_user::get_user_by_username($username);

        $data = [
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $elname,
            'elementtype' => $eltype,
        ];
        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->graderusername = $USER->username;
            $existing->reflection = $formdata->reflection;
            $existing->reflection2 = $formdata->reflection2;
            $existing->reflection3 = $formdata->reflection3;
            $existing->reflection4 = $formdata->reflection4;
            $existing->reflection5 = $formdata->reflection5;
            $existing->grade = '';
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['reflection'] = $formdata->reflection;
            $data['reflection2'] = $formdata->reflection2;
            $data['reflection3'] = $formdata->reflection3;
            $data['reflection4'] = $formdata->reflection4;
            $data['reflection5'] = $formdata->reflection5;
            $data['grade'] = '';
            $DB->insert_record(static::TABLE_REPORTING, $data);
        }

        return true;
    }

    /*public static function save_reportelement_editor($context, $courseid, $year, $period, $username, $elname, $eltype, $reflection) {
        global $DB, $USER, $CFG;

        $user = \core_user::get_user_by_username($username);

        // Store editor files to permanent file area and get text.
        $reflectiontext = file_save_draft_area_files(
            $reflection['itemid'],
            $context->id,
            'mod_psgrading',
            'reflection',
            $year . $period . $user->id,
            form_treflection::editor_options(),
            $reflection['text'],
        );

        // Remove attributes from html.
        $reflectiontext = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $reflectiontext);

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
    }*/

    public static function save_reportelement_form($context, $courseid, $year, $period, $username, $elname, $eltype, $formdata) {
        global $DB, $USER, $CFG;

        $user = \core_user::get_user_by_username($username);

        // Save image to permanent store.
        if (isset($formdata->reflectionimage)) {
            $uniqueid = sprintf( "%d%d%d", $year, $period, $user->id ); // Join the year, period and userid to make a unique itemid.
            file_save_draft_area_files(
                $formdata->reflectionimage,
                $context->id,
                'mod_psgrading',
                'reflectionimage',
                $uniqueid,
                form_reflection::image_options()
            );
        }

        $reflectionimagepath = '';
        $reflectionimagefileid = 0;
        $fs = get_file_storage();
        $uniqueid = sprintf( "%d%d%d", $year, $period, $user->id );
        $files = $fs->get_area_files($context->id, 'mod_psgrading', 'reflectionimage', $uniqueid, "filename", false);
        if (count($files)) {
            // Get first file. Should only be one.
            $file = reset($files);
            // Determine the physical location of the file.
            $dir = str_replace('\\\\', '\\', $CFG->dataroot) .
            '\filedir\\' . substr($file->get_contenthash(), 0, 2) .
            '\\' . substr($file->get_contenthash(), 2, 2) .
            '\\';
            $reflectionimagepath = $dir . $file->get_contenthash();
            $reflectionimagefileid = $file->get_id();
        }

        $subjectsanitised = strtolower(str_replace([' ', '&', '–'], '', $elname));

        $data = [
            'courseid' => $courseid,
            'fileyear' => $year,
            'reportingperiod' => $period,
            'studentusername' => $username,
            'elementname' => $subjectsanitised,
            'elementtype' => $eltype,
        ];

        if ($existing = $DB->get_record('psgrading_reporting', $data, '*', IGNORE_MULTIPLE)) {
            // Update
            $existing->graderusername = $USER->username;
            $existing->reflection = $formdata->reflection;
            $existing->reflectionimagepath = $reflectionimagepath;
            $existing->reflectionimagefileid = $reflectionimagefileid;
            $existing->grade = '';
            $DB->update_record(static::TABLE_REPORTING, $existing);
        } else {
            // Insert
            $data['graderusername'] = $USER->username;
            $data['reflection'] = $formdata->reflection;
            $data['reflectionimagepath'] = $reflectionimagepath;
            $data['reflectionimagefileid'] = $reflectionimagefileid;
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

        // Get all psgrading instances for this course.
        $sql = "SELECT id, restrictto
                FROM {psgrading}
                WHERE course = ?
                  AND reportingperiod = ?";
        $modinstances = $DB->get_records_sql($sql, [$courseid, $period]);

        $courseinstances = [];
        // Don't include instances that are restricted to specific users.
        foreach($modinstances as $inst) {
            if (empty($inst->restrictto)) {
                $courseinstances[] = $inst->id;
            }
        }
        if (empty($courseinstances)) {
            return '';
        }

        // Get the cmids for the mod instances.
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'psgrading']);
        list($insql, $inparams) = $DB->get_in_or_equal($courseinstances);
        $sql = "SELECT id
                  FROM {course_modules}
                 WHERE course = ?
                   AND module = ?
                   AND instance $insql";
        $params = [$courseid, $moduleid];
        $cms = $DB->get_records_sql($sql, array_merge($params, $inparams));
        if (empty($cms)) {
            return '';
        }

        // Get student engagement for tasks that have this subject in the rubric.
        $relevanttasks = [];
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
            $params = [$subjectarea, $cm->id, $username];
            $cmtasks = $DB->get_records_sql($sql, $params);
            $relevanttasks = array_merge($relevanttasks, $cmtasks);
        }

        // Render as html table.
        $html = $OUTPUT->render_from_template('mod_psgrading/reporting_help', ['tasks' => array_values($relevanttasks)]);
        return $html;
    }

}
