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
 * @package mod_psgrading
 * @subpackage backup-moodle2
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_psgrading_activity_task
 */

/**
 * Structure step to restore one psgrading activity
 */
class restore_psgrading_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('psgrading', '/activity/psgrading');
        $paths[] = new restore_path_element('psgrading_task', '/activity/psgrading/tasks/task');
        $paths[] = new restore_path_element('psgrading_criterion', '/activity/psgrading/tasks/task/criterions/criterion');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_psgrading($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the psgrading record
        $newitemid = $DB->insert_record('psgrading', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_psgrading_task($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->cmid = $this->task->get_moduleid();
        $data->published = 0;
        $data->proposedrelease = 0;
        $data->timerelease = 0;

        // insert the entry record
        $newitemid = $DB->insert_record('psgrading_tasks', $data);
        $this->set_mapping('psgrading_task', $oldid, $newitemid, true);
    }

    protected function process_psgrading_criterion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->taskid = $this->get_new_parentid('psgrading_tasks');
        $newitemid = $DB->insert_record('psgrading_task_criterions', $data);

        // Need to update mdl_psgrading_tasks.criterionjson to point the IDs to the new criteria..
        if ($task = $DB->get_record('psgrading_tasks', ['id' => $data->taskid], '*', IGNORE_MULTIPLE)) {
            $json = json_decode($task->criterionjson, true); // Decode as an associative array

            // Loop through each criterion and update the id property
            foreach ($json as &$criterion) {
                if ($criterion['id'] == $oldid) {
                    $criterion['id'] = $newitemid;
                }
            }

            // Encode the updated array back to JSON
            $task->criterionjson = json_encode($json);

            // Update the record in the database
            $DB->update_record('psgrading_tasks', $task);
        }
    }

    protected function after_execute() {
        // Add psgrading related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_psgrading', 'intro', null);
    }
}
