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
 * Plugin external functions and services are defined here.
 *
 * @package   mod_psgrading
 * @category  external
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_psgrading_autosave' => [
        'classname'     => 'mod_psgrading\external\api',
        'methodname'    => 'autosave',
        'classpath'     => '',
        'description'   => 'Autosave a task',
        'type'          => 'write',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'mod_psgrading_apicontrol' => [
        'classname'     => 'mod_psgrading\external\api',
        'methodname'    => 'apicontrol',
        'classpath'     => '',
        'description'   => 'API control',
        'type'          => 'write',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'mod_psgrading_get_activities_in_course' => [
        'classname'     => 'mod_psgrading\external\api',
        'methodname'    => 'get_activities_in_course',
        'classpath'     => '',
        'description'   => 'Get the PS grading activities for a given course',
        'type'          => 'read',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'mod_psgrading_get_tasks_in_activity' => [
        'classname'     => 'mod_psgrading\external\api',
        'methodname'    => 'get_tasks_in_activity',
        'classpath'     => '',
        'description'   => 'Get the tasks associated to an activity',
        'type'          => 'read',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'mod_psgrading_restore_task' => [
        'classname'     => 'mod_psgrading\external\api',
        'methodname'    => 'restore_task',
        'classpath'     => '',
        'description'   => 'Restore deleted task',
        'type'          => 'read',
        'loginrequired' => true,
        'ajax'          => true,
    ],
];
