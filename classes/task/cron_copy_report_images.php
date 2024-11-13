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
 * The main scheduled task to set up syncing of grades to the staging table.
 * The effort is divided into independent adhoc tasks that process the sync for a single course.
 *
 * @package   mod_psgrading
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_psgrading\task;

defined('MOODLE_INTERNAL') || die();

use mod_psgrading\utils;

class cron_copy_report_images extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask_copy_report_images', 'mod_psgrading');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB, $CFG;

        $this->log_start("Starting image copy.");

        $distinationdir = str_replace('\\\\', '\\', $CFG->dataroot) . '\psgrading\reporting\\';

        // Check for destination dir before moving forward.
        $distinationdirroot = str_replace('\\\\', '\\', $CFG->dataroot) . '\psgrading\\';
        if (!is_dir($distinationdirroot)) {
            if (!mkdir($distinationdirroot)) {
                $this->log_finish('Failed to create directory: ' . $distinationdirroot);
                exit;
            }
        }
        if (!is_dir($distinationdir)) {
            if (!mkdir($distinationdir)) {
                $this->log_finish('Failed to create directory: ' . $distinationdir);
                exit;
            }
        }

        $config = get_config('mod_psgrading');
        $year = date('Y');
        $passedp1 = time() > strtotime( $year . '-' . $config->s1cutoffmonth . '-' . $config->s1cutoffday );
        $period = 1;
        if ( $passedp1 ) {
            $period = 2;
        }

        // Get all psgrading instances.
        $sql = "SELECT r.*, f.*
                FROM {psgrading_reporting} r, {files} f
                WHERE fileyear = " . $year . "
                AND reportingperiod = " . $period . "
                AND reflectionimagefileid > 0
                AND elementname = 'studentreflection'
                AND f.id = r.reflectionimagefileid ";
        $rows = $DB->get_records_sql($sql);

        // Copy the images.
        foreach ($rows as $row) {
            $mime = explode("/", $row->mimetype);
            $distinationfilename = $row->studentusername . '.' . $mime[1];

            // -- overwrite old images
            // If file already exists in distination, don't copy it again.
            // if (file_exists($distinationdir . $distinationfilename)) {continue;}

            // If file exists in source dir, copy it over.
            $this->log("Copying " . $row->reflectionimagepath . " to " . $distinationdir . $distinationfilename, 2);
            if (file_exists($row->reflectionimagepath)) {
                copy($row->reflectionimagepath, $distinationdir . $distinationfilename);
                // Rotate the file if necessary.
                utils::image_fix_orientation($distinationdir . $distinationfilename, $row->mimetype);
            }
        }

        $this->log_finish("Copy complete.");
    }



}
