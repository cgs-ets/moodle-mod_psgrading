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
 * Plugin strings are defined here.
 *
 * @package     mod_psgrading
 * @category    string
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();



$string['pluginname'] = 'Primary School Grading';
$string['pluginname_desc'] = 'A continuous grading system.';
$string['modulename'] = 'Primary School Grading';
$string['modulenameplural'] = 'Primary School Grading';
$string['modform:name'] = 'Name';
$string['modform:name_help'] = 'Name for the PS Grading system';
$string['pluginadministration'] = '';
$string['singleinstanceonly'] = 'Only one instance of \'Primary School Grading\' is allowed per course.';
$string['cron_grade_release'] = 'PS Grading grade release processing.';

$string['tasksetup'] = 'Task setup';
$string['psgrading:task'] = 'Create a new task';
$string['privacy:metadata'] = 'Primary School Grading does not store any personal data.';

$string['task:create'] = 'Create a new task';
$string['task:editsuccess'] = 'Task was successfully edited.';
$string['task:publishsuccess'] = 'Task was successfully published.';
$string['task:createfail'] = 'Failed to create task.';
$string['task:name'] = 'Name';
$string['task:pypuoi'] = 'PYP UOI';
$string['task:outcomes'] = 'Outcomes';
$string['task:criteria'] = 'Criteria';
$string['task:evidence'] = 'Evidence';
$string['task:publish'] = 'Publish';
$string['task:publishchanges'] = 'Publish changes';
$string['task:savedraft'] = 'Save draft';
$string['task:discardchanges'] = 'Discard changes';
$string['task:discarddraft'] = 'Discard draft';
$string['task:deletedraft'] = 'Delete draft';
$string['task:exitedit'] = 'Cancel';
$string['mark:comment'] = 'Comment';
$string['mark:engagement'] = 'Engagement';
$string['mark:save'] = 'Save and return';
$string['mark:saveshownext'] = 'Save and show next';
$string['mark:reset'] = 'Reset';
$string['mark:savesuccess'] = 'Grade data was saved for {$a}';
$string['mark:savefail'] = 'Failed to save grade data for {$a}';
$string['mark:resetsuccess'] = 'Grade data was reset for {$a}';

$string['pypuoi:wwa'] = 'Who we are';
$string['pypuoi:wwaipat'] = 'Where we are in place and time';
$string['pypuoi:hweo'] = 'How we express ourselves';
$string['pypuoi:htww'] = 'How the world works';
$string['pypuoi:hwoo'] = 'How we organize ourselves';
$string['pypuoi:stp'] = 'Sharing the planet';

$string['list:notasks'] = 'There are no tasks yet.';
$string['createtask'] = 'Create a new task';
$string['managetasks'] = 'Manage tasks';
$string['overview'] = 'Student overview';
$string['addcriterion'] = 'Add criterion';
$string['addevidence'] = 'Select any course activities that form part of this task';

// Settings
$string['enableweights'] = 'Enable weights';

// Statuses
$string['unpublishededits'] = 'Task has unpublished edits';
$string['notpublishedyet'] = 'Task not published yet';
$string['gradesreleased'] = 'Grades released';
$string['readytograde'] = 'Task/grading in progress';
$string['gradesnotreleased'] = 'Grades not released';
