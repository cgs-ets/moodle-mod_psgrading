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
 * Scheduled task for processing following a new/updated grade.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_psgrading\task;

defined('MOODLE_INTERNAL') || die();

use mod_psgrading\persistents\task;
use mod_psgrading\utils;

class cron_grade_release extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_grade_release', 'mod_psgrading');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB, $OUTPUT, $CFG;

        if (!file_exists($CFG->dirroot . '/local/myconnect/lib.php')) {
            $this->log("MyConnect is not installed.");
            return;
        }

        // $config = get_config('mod_psgrading');
        // Get grades that need to be processed, from tasks that are released.
        $this->log_finish("Looking for grades to process");
        $sql = "SELECT tg.*, t.cmid
                  FROM {" . task::TABLE_GRADES . "} tg
            INNER JOIN {" . task::TABLE . "} t ON t.id = tg.taskid
                 WHERE tg.releaseprocessed = 0
                   AND t.timerelease != 0
                   AND t.timerelease < " . time() . "
                   AND t.deleted = 0
                   AND t.published = 1";
        $grades = $DB->get_records_sql($sql, []);

        // Loop grades.
        foreach ($grades as $grade) {
            $this->log("Processing grade record " . $grade->id, 1);

            // Check restrictto and excludeusers.
            $cm = get_coursemodule_from_id('psgrading', $grade->cmid, 0, false, MUST_EXIST);
            $moduleinstance = $DB->get_record('psgrading', ['id' => $cm->instance], '*', MUST_EXIST);
            if (in_array($grade->studentusername, explode(',', $moduleinstance->excludeusers))) {
                $this->log_finish("Skipping as this student is excluded: " . $grade->studentusername, 3);
                continue;
            }
            if ($moduleinstance->restrictto && !in_array($grade->studentusername, explode(',', $moduleinstance->restrictto))) {
                $this->log_finish("Skipping as this student is not in the restrictto list: " . $grade->studentusername, 3);
                continue;
            }

            // If the rubric is not graded at all then skip, unless didnotsubmit selected and comment is present.
            $criteriaselections = task::get_grade_criterion_selections($grade->id);
            $criteriasum = array_sum(array_column($criteriaselections, 'gradelevel'));
            if (empty($criteriasum)) {
                // Check for did not submit and comment.
                if ($grade->didnotsubmit && !empty($grade->comment)) {
                    // Continue to release the grade.
                } else {
                    $this->log_finish("Skipping. The rubric was not graded for this student/task: " . $grade->studentusername . "/" . $grade->taskid, 3);
                    // Mark release skipped so we don't keep trying to release this.
                    $grade->releaseprocessed = 2;
                    $DB->update_record(task::TABLE_GRADES, $grade);
                    continue;
                }
            }

            // Get the task record for reference.
            $task = $DB->get_record('psgrading_tasks', ['id' => $grade->taskid]);

            // Create a MyConnect post.
            $this->log("Creating MyConnect post.", 2);
            $grader = \local_myconnect\utils::get_user_with_extras($grade->graderusername);
            $student = \local_myconnect\utils::get_user_with_extras($grade->studentusername);

            $postdata = new \stdClass();

            $recipient = new \stdClass();
            $recipient->idhighlighted = $recipient->idfield = $student->username;
            $recipient->fullnamehighlighted = $recipient->fullname = $student->fullname;
            $recipient->timelineurl = $student->timelineurl->out(false);
            $recipient->photourl = $student->photo;
            $recipient->type = 'user';
            $postdata->recipientjson = json_encode([$recipient]);

            $postdata->attachments = 0;

            $params = [
                'cmid' => $task->cmid,
                'taskid' => $task->id,
                'groupid' => 0,
                'userid' => $student->id,
            ];
            $detailsurl = new \moodle_url('/mod/psgrading/details.php', $params);
            $postdata->comment = [
                'text' => $OUTPUT->render_from_template('mod_psgrading/release_grade_myconnect_new', [
                    'detailsurl' => $detailsurl->out(false),
                    'taskname' => $task->taskname,
                ]),
                'format' => '1',
                'itemid' => 0,
            ];

            $postid = \local_myconnect\persistents\post::save_from_formdata(0, $postdata, $grader);
            if ($postid) {
                $this->log("MyConnect post created " . $postid, 2);

                // Record grade-to-myconnectpost relationship.
                $releasepostrec = new \stdClass();
                $releasepostrec->taskid = $task->id;
                $releasepostrec->gradeid = $grade->id;
                $releasepostrec->postid = $postid;
                $releasepostrec->id = $DB->insert_record(task::TABLE_RELEASE_POSTS, $releasepostrec);

                // Copy evidence to MyConnect post.
                $this->log("Copying evidence to MyConnect post.", 2);
                $modulecontext = \context_module::instance($task->cmid);
                $fs = get_file_storage();
                $uniqueid = sprintf( "%d%d", $task->id, $student->id ); // Join the task id and grade student it to make the unique itemid.
                $files = $fs->get_area_files($modulecontext->id, 'mod_psgrading', 'evidences', $uniqueid, "filename", false);
                foreach ($files as $storedfile) {
                    $newid = utils::create_myconnect_file_reference($postid, $storedfile->get_id());
                    if ($newid) {
                        $this->log("Created file reference '" . $newid . "'", 3);
                    } else {
                        $this->log_finish("Weird, the file record was not found for " . $storedfile->get_id(), 3);
                    }
                }

                // Get MyConnect evidence references and copy them back into MyConnect post.
                $this->log("Copying MyConnect evidence to MyConnect post.", 2);
                $myconnectevidences = task::get_myconnect_grade_evidences($grade->id);
                if ($myconnectevidences) {
                    foreach ($myconnectevidences as $fileid) {
                        $newid = utils::create_myconnect_file_reference($postid, $fileid);
                        if ($newid) {
                            $this->log("Created file reference '" . $newid . "'", 3);
                        } else {
                            $this->log_finish("Weird, the file record was not found for " . $fileid, 3);
                        }
                    }
                }

                // Set the display type now that the post has files.
                \local_myconnect\persistents\post::determine_attachment_display_type($postid);

            } else {
                $this->log("Failed to create MyConnect post", 2);
            }

            // Mark grade as processed.
            $grade->releaseprocessed = 1;
            $DB->update_record(task::TABLE_GRADES, $grade);

        }

        $this->log_finish("Finished processing grades");

    }

}
