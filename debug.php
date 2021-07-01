<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Debugger.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/mod/psgrading/debug.php');
$PAGE->set_title('mod_psgrading debugger');
$PAGE->set_heading('mod_psgrading debugger');

require_login();
require_capability('moodle/site:config', $context, $USER->id);

$task = new \mod_psgrading\task\cron_grade_release;
$task->execute();
exit;

