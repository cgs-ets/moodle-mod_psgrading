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

$string['settingsheaderdb'] = 'Database connection';
$string['dbtype'] = 'Database driver';
$string['dbtype_desc'] = 'ADOdb database driver name, type of the external database engine.';
$string['dbhost'] = 'Database host';
$string['dbhost_desc'] = 'Type database server IP address or host name. Use a system DSN name if using ODBC. Use a PDO DSN if using PDO.';
$string['dbname'] = 'Database name';
$string['dbuser'] = 'Database user';
$string['dbpass'] = 'Database password';
$string['staffclassessql'] = 'Staff Classes SQL';
$string['classstudentssql'] = 'Class Students SQL';
$string['s1cutoffmonth'] = 'Semester 1 cutoff month';
$string['s1cutoffday'] = 'Semester 1 cutoff day';


$string['pluginadministration'] = '';
//$string['singleinstanceonly'] = 'Only one instance of \'Primary School Grading\' is allowed per course.';
$string['cron_grade_release'] = 'PS Grading grade release processing.';

$string['tasksetup'] = 'Task setup';
$string['privacy:metadata'] = 'Primary School Grading does not store any personal data.';

$string['task:create'] = 'Create a new task';
$string['task:edit'] = 'Edit task';
$string['task:savesuccess'] = 'Task was successfully saved.';
$string['task:savefail'] = 'Failed to save task.';
$string['task:name'] = 'Name';
$string['task:pypuoi'] = 'PYP UOI';
$string['task:outcomes'] = 'Outcomes';
$string['task:criteria'] = 'Criteria';
$string['task:evidence'] = 'Evidence';
$string['task:notes'] = 'Hidden notes';
$string['task:visibility'] = 'Visibility';
$string['task:visibledesc'] = 'Make task visible to students and parents.';
$string['task:proposedrelease'] = 'Proposed release date';
$string['task:save'] = 'Save';
$string['task:delete'] = 'Delete';
$string['task:cancel'] = 'Cancel';
$string['task:deletetask'] = 'Delete task';


$string['mark:comment'] = 'Comment for {$a}';
$string['mark:engagement'] = 'Engagement';
$string['mark:save'] = 'Save and return';
$string['mark:saveshownext'] = 'Save and show next';
$string['mark:reset'] = 'Reset';
$string['mark:savesuccess'] = 'Feedback was saved for {$a}';
$string['mark:savefail'] = 'Failed to save feedback for {$a}';
$string['mark:resetsuccess'] = 'Feedback was reset for {$a}';
$string['mark:myconnectattachments'] = 'MyConnect attachments';
$string['mark:didnotsubmit'] = 'Did not submit';
$string['mark:dns'] = 'DNS';
$string['mark:replacegrader'] = '<b>{$a}</b> will appear as the grader when this grade is released. Tick this option to become the grader. Leave this option unticked to keep the current grader.';

$string['taskalreadygraded'] = 'Some students have already been graded. Changing the rubric may require tasks to be regraded.';
$string['activitylocked'] = 'The reporting period for this is over and the grading actiity has been locked. Changes to tasks and grades will not be saved.';

$string['list:notasks'] = 'There are no tasks yet.';
$string['overview'] = 'Student overview';
$string['addcriterion'] = 'Add criterion';
$string['addevidence'] = 'Select any course activities that form part of this task';

// Settings
$string['enableweights'] = 'Enable weights';
$string['restrictto'] = 'Restrict to users (comma-separated usernames)';
$string['excludeusers'] = 'Exclude users (comma-separated usernames)';
$string['reportingperiod'] = 'Reporting Period (Semester)';

// Statuses
$string['taskvisible'] = 'Task is visible';
$string['taskhidden'] = 'Task is hidden';
$string['hasgrades'] = 'Task has grades';
$string['gradingnotstarted'] = 'Grading has not started';
$string['gradesreleased'] = 'Feedback has been released';
$string['gradesnotreleased'] = 'Feedback not released';
$string['gradesreleasing'] = 'Releasing grades';
$string['taskhiddenmakevisible'] = 'Task is hidden. Click to make visible.';
$string['dragtoreorder'] = 'Drag to reorder';

// Reporting
$string['reflection'] = 'Reflection';
$string['reflection'] = 'Reflection';
$string['crontask_copy_report_images'] = 'Copy reflection images';
$string['crontask_gradesync'] = 'PSGrading Sync grades';

$string['nodelete'] = 'PS Grading activities cannot be deleted. Please contact the Service Desk and provide the following details in your request: `Delete Primary Grading activity CMID {$a}`';