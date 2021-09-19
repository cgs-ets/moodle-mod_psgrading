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
 * Post installation and migration code.
 *
 * @package   mod_psgrading
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_psgrading_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021062302) {
        // Add status field.
        $table = new xmldb_table('psgrading_task_logs');
        $status = new xmldb_field('status', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, null, 'formjson');
        if (!$dbman->field_exists($table, $status)) {
            $dbman->add_field($table, $status);
        }
    }

    if ($oldversion < 2021062303) {
        // Add seq field.
        $table = new xmldb_table('psgrading_tasks');
        $seq = new xmldb_field('seq', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null, 'draftjson');
        if (!$dbman->field_exists($table, $seq)) {
            $dbman->add_field($table, $seq);
        }
    }

    if ($oldversion < 2021062304) {
        // Add timerelease field.
        $table = new xmldb_table('psgrading_tasks');

        $timerelease = new xmldb_field('timerelease', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null, 'seq');
        if (!$dbman->field_exists($table, $timerelease)) {
            $dbman->add_field($table, $timerelease);
        }

        // Define table psgrading_release_posts to be created.
        $table = new xmldb_table('psgrading_release_posts');

        // Add fields to table psgrading_release_posts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gradeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
       
        // Adding keys to table psgrading_release_posts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('fk_taskid', XMLDB_KEY_FOREIGN, array('taskid'), 'psgrading_tasks', array('id'));
        $table->add_key('fk_gradeid', XMLDB_KEY_FOREIGN, array('gradeid'), 'psgrading_grades', array('id'));

        // Create table psgrading_release_posts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2021062305) {
        $table = new xmldb_table('psgrading_grades');
        $releaseprocessed = new xmldb_field('releaseprocessed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'evidences');
        if (!$dbman->field_exists($table, $releaseprocessed)) {
            $dbman->add_field($table, $releaseprocessed);
        }
    }

    if ($oldversion < 2021063001) {

        // Define table psgrading_userprefs to be created.
        $table = new xmldb_table('psgrading_userprefs');

        // Adding fields to table psgrading_userprefs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table psgrading_userprefs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for psgrading_userprefs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Psgrading savepoint reached.
        upgrade_mod_savepoint(true, 2021063001, 'psgrading');
    }

    if ($oldversion < 2021063006) {

        // Define table psgrading_grades_cache to be created.
        $table = new xmldb_table('psgrading_grades_cache');

        // Adding fields to table psgrading_grades_cache.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table psgrading_grades_cache.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for psgrading_grades_cache.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Psgrading savepoint reached.
        upgrade_mod_savepoint(true, 2021063006, 'psgrading');
    }

    if ($oldversion < 2021063007) {

        // Define field draftjson to be dropped from psgrading_tasks.
        $table = new xmldb_table('psgrading_tasks');
        $field = new xmldb_field('draftjson');

        // Conditionally launch drop field draftjson.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Psgrading savepoint reached.
        upgrade_mod_savepoint(true, 2021063007, 'psgrading');
    }


    if ($oldversion < 2021091600) {

        // Define table psgrading_gradesync to be created.
        $table = new xmldb_table('psgrading_gradesync');

        // Adding fields to table psgrading_gradesync.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('psgradingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('externalreportid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table psgrading_gradesync.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for psgrading_gradesync.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Psgrading savepoint reached.
        upgrade_mod_savepoint(true, 2021091600, 'psgrading');
    }


    return true;
}
