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
 * Define all the backup steps that will be used by the backup_psgrading_activity_task
 */

/**
 * Define the complete psgrading structure for backup, with file and id annotations
 */
class backup_psgrading_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        //$userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $psgrading = new backup_nested_element('psgrading', array('id'), array(
            'course', 'name', 'timecreated', 'timemodified', 'intro',
            'introformat', 'enableweights'));

        $tasks = new backup_nested_element('tasks');
        $task = new backup_nested_element('task', array('id'), array(
            'cmid', 'creatorusername', 'taskname', 'pypuoi',
            'outcomes', 'criterionjson', 'evidencejson', 'published',
            'deleted', 'seq', 'timerelease', 'timecreated',
            'timemodified'));

        $criterions = new backup_nested_element('criterions');
        $criterion = new backup_nested_element('criterion', array('id'), array(
            'taskid', 'description', 'level4', 'level3',
            'level2', 'subject', 'weight', 'seq',
            'hidden'));

        // Build the tree
        $psgrading->add_child($tasks);
        $tasks->add_child($task);

        $task->add_child($criterions);
        $criterions->add_child($criterion);

        // Define sources
        $psgrading->set_source_table('psgrading', array('id' => backup::VAR_ACTIVITYID));

        $task->set_source_table('psgrading_tasks', array('cmid' => backup::VAR_MODID));

        $criterion->set_source_table('psgrading_task_criterions', array('taskid' => backup::VAR_PARENTID));

        // Define file annotations
        $psgrading->annotate_files('mod_psgrading', 'intro', null); // This file area hasn't itemid

        // Return the root element (psgrading), wrapped into standard activity structure
        return $this->prepare_activity_structure($psgrading);
    }
}
