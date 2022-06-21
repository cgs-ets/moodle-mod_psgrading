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

    public static function get_reportelements($assesscode, $yearlevel) {

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
                    if ($yearlevel >= 3) {
                        $elements[] = array(
                            'subjectarea' => 'Student reflection',
                            'type' => 'text',
                        );
                    }
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
                    $element['grade'] = $existing->grade;
                    $element['minimal'] = static::REPORTENGAGEMENTOPTIONS[$existing->grade]['minimal'];
                }
                
                //$element['grade'] = '2';
                //$element['minimal'] = static::REPORTENGAGEMENTOPTIONS['2']['minimal'];
            }
            //echo "<pre>"; var_export($sdata); exit;

        }
    }

    public static function save_reportelement($courseid, $year, $period, $username, $elname, $eltype, $grade) {
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

}        
