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

use mod_psgrading\persistents\task;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once(__DIR__.'/vendor/PHP-FineDiff/finediff.php');

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class utils {

    const SUBJECTOPTIONS = [
         [
            'txt' => 'Subject',
            'val' => '',
            'attrs' => 'disabled selected',
         ],
         [
            'txt' => 'English – reading and viewing',
            'val' => 'English – reading and viewing',
         ],
         [
            'txt' => 'English – speaking and listening',
            'val' => 'English – speaking and listening',
         ],
         [
            'txt' => 'English – writing',
            'val' => 'English – writing',
         ],
         [
            'txt' => 'HASS',
            'val' => 'HASS',
         ],
         [
            'txt' => 'Science',
            'val' => 'Science',
         ],
         [
            'txt' => 'Technology',
            'val' => 'Technology',
         ],
         [
            'txt' => 'Maths – measurement and geometry',
            'val' => 'Maths – measurement and geometry',
         ],
         [
            'txt' => 'Maths – number and algebra',
            'val' => 'Maths – number and algebra',
         ],
         [
            'txt' => 'Maths – statistics and probability',
            'val' => 'Maths – statistics and probability',
         ],
         [
            'txt' => 'H&PE',
            'val' => 'H&PE',
         ],
         [
            'txt' => 'VisArts',
            'val' => 'VisArts',
         ],
         [
            'txt' => 'Chinese',
            'val' => 'Chinese',
         ],
         [
            'txt' => 'Indonesian',
            'val' => 'Indonesian',
         ],
         [
            'txt' => 'Music',
            'val' => 'Music',
         ],
         [
            'txt' => 'Media Arts',
            'val' => 'Media Arts',
         ],
         [
            'txt' => 'Drama',
            'val' => 'Drama',
         ],
    ];

    const WEIGHTOPTIONS = [
         [
            'txt' => 'Weight',
            'val' => '',
            'attrs' => 'disabled selected',
         ],
         [
            'txt' => '5%',
            'val' => '5',
         ],
         [
            'txt' => '10%',
            'val' => '10',
         ],
         [
            'txt' => '15%',
            'val' => '15',
         ],
         [
            'txt' => '20%',
            'val' => '20',
         ],
         [
            'txt' => '25%',
            'val' => '25',
         ],
         [
            'txt' => '30%',
            'val' => '30',
         ],
         [
            'txt' => '35%',
            'val' => '35',
         ],
         [
            'txt' => '40%',
            'val' => '40',
         ],
         [
            'txt' => '45%',
            'val' => '45',
         ],
         [
            'txt' => '50%',
            'val' => '50',
         ],
         [
            'txt' => '55%',
            'val' => '55',
         ],
         [
            'txt' => '60%',
            'val' => '60',
         ],
         [
            'txt' => '65%',
            'val' => '65',
         ],
         [
            'txt' => '70%',
            'val' => '70',
         ],
         [
            'txt' => '75%',
            'val' => '75',
         ],
         [
            'txt' => '80%',
            'val' => '80',
         ],
         [
            'txt' => '85%',
            'val' => '85',
         ],
         [
            'txt' => '90%',
            'val' => '90',
         ],
         [
            'txt' => '95%',
            'val' => '95',
         ],
         [
            'txt' => '100%',
            'val' => '100',
         ],
    ];

    const GRADELANG = [
        '0' => [
            'full' => '',
            'minimal' => '',
            'tip' => '',
        ],
        '5' => [
            'full' => '5 (NY)',
            'minimal' => 'NY',
            'tip' => 'Not Yet',
        ],
        '4' => [
            'full' => '4 (GS)',
            'minimal' => 'GS',
            'tip' => 'Good Start',
        ],
        '3' => [
            'full' => '3 (MS)',
            'minimal' => 'MS',
            'tip' => 'Making Strides',
        ],
        '2' => [
            'full' => '2 (GRWI)',
            'minimal' => 'GRWI',
            'tip' => 'Go Run With It',
        ],
        '1' => [
            'full' => '1 (FH)',
            'minimal' => 'FH',
            'tip' => 'Fly High',
        ],
    ];
    const GRADEENGAGEMENTLANG = [

        '0' => [
            'full' => '',
            'minimal' => '',
            'tip' => '',
        ],
        '4' => [
            'full' => '4 (NI)',
            'minimal' => 'NI',
            'tip' => 'Needs Improvement',
        ],
        '3' => [
            'full' => '3 (ACC)',
            'minimal' => 'ACC',
            'tip' => 'Acceptable’',
        ],
        '2' => [
            'full' => '2 (VG)',
            'minimal' => 'VG',
            'tip' => 'Very Good',
        ],
        '1' => [
            'full' => '1 (E)',
            'minimal' => 'E',
            'tip' => 'Excellent',
        ],
    ];

    const ENGAGEMENTOPTIONS = [
        '' => 'Select',
        'NI' => 'Needs Improvement',
        'A' => 'Acceptable',
        'VG' => 'Very Good',
        'E' => 'Excellent',
    ];

    const ENGAGEMENTWEIGHTS = [
        '' => 0,
        'NI' => 25,
        'A' => 50,
        'VG' => 75,
        'E' => 100,
    ];

    const PYPUOIOPTIONS = [
        '' => 'Select',
        'wwa' => 'Who we are',
        'wwaipat' => 'Where we are in place and time',
        'hweo' => 'How we express ourselves',
        'htww' => 'How the world works',
        'hwoo' => 'How we organize ourselves',
        'stp' => 'Sharing the planet',
    ];

    public static function decorate_subjectdata($criteriondata) {
        foreach ($criteriondata as $i => $row) {
            $criteriondata[$i]->selectedsubject = $row->subject;
            $criteriondata[$i]->subject = [
                'value' => $row->subject,
                'options' => static::get_subject_options_with_selected($row->subject),
            ];
        }
        return $criteriondata;
    }

    public static function get_subject_options_with_selected($selected) {
        $options = [];
        foreach (static::SUBJECTOPTIONS as $i => $option) {
            if ($option['val'] === $selected) {
                $option['sel'] = true;
            }
            $options[] = $option;
        };
        return $options;
    }


    public static function decorate_weightdata($criteriondata) {
        foreach ($criteriondata as $i => $row) {
            $weight = isset($row->weight) ? $row->weight : '';
            $criteriondata[$i]->weight = [
                'value' => $weight,
                'options' => static::get_weight_options_with_selected($weight),
            ];
            $criteriondata[$i]->selectedweight = $weight;
        }
        return $criteriondata;
    }

    public static function get_weight_options_with_selected($selected) {
        $options = [];
        foreach (static::WEIGHTOPTIONS as $i => $option) {
            if ($option['val'] === $selected) {
                $option['sel'] = true;
            }
            $options[] = $option;
        };
        return $options;
    }

    public static function get_stub_criterion() {
        $criterion = new \stdClass();
        $criterion->id = -1;
        $criterion->subject = [
            'value' => '',
            'options' => static::SUBJECTOPTIONS,
        ];
        $criterion->weight = [
            'value' => '',
            'options' => static::WEIGHTOPTIONS,
        ];
        return $criterion;
    }

    public static function get_evidencedata($course, $evidencejson) {
        global $USER, $DB;

        // Already selected activities.
        $selectedcms = [];
        $evidencejson = json_decode($evidencejson);
        if ($evidencejson) {
            $selectedcms = array_column($evidencejson, 'refdata');
        }
        $activities = [];
        $modinfo = get_fast_modinfo($course, $USER->id);
        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $cmrec = $cm->get_course_module_record(true);

            // Don't include deleted activities.
            if ($cmrec->deletioninprogress) {
                continue;
            }

            // Don't include self, and resources.
            /*if (in_array($cmrec->modname, array(
                    'psgrading',
                    'resource',
                    'folder',
                    'book',
                    'label',
                    'page',
                    'url',
                    'clickview',
                    'unilabel',
                    'zoom',
                ))) {
                continue;
            }*/

            // Only include supported activities.
            if (!in_array($cmrec->modname, [
                    'giportfolio',
                    'googledocs',
                    'assign',
                    'quiz',
                    'website'
                ])) {
                continue;
            }

            // For giportfolio, we want to list all of the chapters as evidence instead of the activity.
            if ($cmrec->modname == 'giportfolio') {
                // Add the module, but no URL to denote it is not a selectable item.
                $cmrec->icon = $cm->get_icon_url()->out();
                $cmrec->isheader = true;
                $cmrec->cmid = $cmrec->id;
                $activities[] = $cmrec;

                // Get the chapters.
                $chapters = [];
                $sql = "SELECT *
                          FROM {giportfolio_chapters}
                         WHERE giportfolioid = ?
                           AND userid = 0";
                $chapters = $DB->get_records_sql($sql, [$cmrec->instance]);

                foreach ($chapters as $chapter) {
                    $ch = new \stdClass();
                    $ch->cmid = $cmrec->id;
                    $ch->id = $cmrec->id . '_' . $cmrec->instance . '_' . $chapter->id;
                    $ch->icon = '';
                    $ch->name = $chapter->title;
                    $ch->modname = 'giportfoliochapter';
                    $url = new \moodle_url('/mod/giportfolio/viewgiportfolio.php', [
                        'id' => $cm->id,
                        'chapterid' => $chapter->id,
                        'userid' => $USER->id,
                    ]);
                    $ch->url = $url->out(false);
                    if (in_array($cmrec->id . '_' . $cmrec->instance . '_' . $chapter->id, $selectedcms)) {
                        $ch->sel = true;
                    }
                    $ch->issub = true;
                    $activities[] = $ch;
                }

            } else {
                $cmrec->cmid = $cm->id;
                $cmrec->icon = $cm->get_icon_url()->out(); // $cmrec->icon = $OUTPUT->pix_icon('icon', $cmrec->name, $cmrec->modname, array('class'=>'icon'));
                $cmrec->url = $cm->url;
                if (in_array($cmrec->id, $selectedcms)) {
                    $cmrec->sel = true;
                }
                $activities[] = $cmrec;
            }

        }
        return $activities;
    }

    /**
     * Helper function to get the students enrolled
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_enrolled_students($courseid, $excludeusers = []) {
        global $DB;
        $context = \context_course::instance($courseid);

        // 5 is student.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $users = get_role_users($studentroleid, $context, false, 'u.id, u.username, u.firstname, u.lastname', 'u.lastname'); // last param is sort by.

        $filteredusers = array_filter( $users, function( $u ) use($excludeusers) {
            return !in_array($u->username, $excludeusers);
        });

        return array_map('intval', array_column($filteredusers, 'id'));
    }

    /**
     * Helper function to get enrolled students by username.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_enrolled_students_restricted($courseid, $usernames, $excludeusers) {
        global $DB;
        $context = \context_course::instance($courseid);
        // 5 is student.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $users = get_role_users($studentroleid, $context, false, 'u.id, u.username, u.firstname, u.lastname', 'u.lastname'); // last param is sort by.

        $filteredusers = array_filter( $users, function( $u ) use($usernames) {
            return in_array($u->username, $usernames);
        });

        $filteredusers = array_filter( $users, function( $u ) use($excludeusers) {
            return !in_array($u->username, $excludeusers);
        });

        return array_map('intval', array_column($filteredusers, 'id'));
    }

    /**
     * Helper function to get the students enrolled.
     * If this is a non-staff member, filter the list and perform permission check.
     *
     * @param int $courseid
     * @param int $accessuserid. The user id that is being viewed.
     * @return int[]
     */
    public static function get_filtered_students($courseid, $accessuserid = 0, $restrictto = '', $excludeusers = '') {
        global $USER;

        if ($restrictto) {
            $students = static::get_enrolled_students_restricted($courseid, explode(',', $restrictto), explode(',', $excludeusers));
        } else {
            $students = static::get_enrolled_students($courseid, explode(',', $excludeusers));
        }

        $isstaff = static::is_grader();
        if (!$isstaff) {
            $vars = [
                'userid' => $USER->id,
                'mentees' => static::get_users_mentees($USER->id, 'id'),
            ];
            // Filter students to only contain self + mentees.
            $students = array_filter($students, function($student) use ($vars) {
                if ($student == $vars['userid']) { // The student is the user themselves.
                    return true;
                }
                if (in_array($student, $vars['mentees'])) { // The student is a mentee.
                    return true;
                }
                return false;
            });
            $students = array_values($students);
        }

        // If a specific user is being viewed, ensure that the user being viewed is in the list of students.
        if (!empty($accessuserid)) {
            if (!in_array($accessuserid, $students)) {
                exit;
            }
        }

        return $students;
    }

    /**
     * Helper function to get groups in a course.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_course_groups($courseid) {
        global $DB;

        $sql = "SELECT g.id, g.name
                  FROM {groups} g
                 WHERE g.courseid = ?
              ORDER BY g.name ASC";
        $groups = $DB->get_records_sql($sql, [$courseid]);
        $groups = array_map('intval', array_column($groups, 'id'));

        return $groups;
    }

    /**
     * Helper function to get users groups in a course.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_users_course_groups($userid, $courseid) {
        global $DB;

        $sql = "SELECT DISTINCT g.id, g.name
                  FROM {groups} g, {groups_members} gm
                 WHERE gm.groupid = g.id
                   AND g.courseid = ?
                   AND gm.userid = ?
              ORDER BY g.name ASC";
        $groups = $DB->get_records_sql($sql, [$courseid, $userid]);
        $groups = array_map('intval', array_column($groups, 'id'));

        return $groups;
    }

    /**
     * Helper function to get group info.
     *
     * @param int $groupid
     * @return stdClass
     */
    public static function get_group_display_info($groupid) {
        global $DB;

        $sql = "SELECT g.id, g.name, g.description
                  FROM {groups} g
                 WHERE g.id = ?";
        $group = $DB->get_record_sql($sql, [$groupid]);

        return $group;
    }

    /**
     * Helper function to get the students enrolled.
     * If this is a non-staff member, filter the list and perform permission check.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $accessuserid. The user id that is being viewed.
     * @return int[]
     */
    public static function get_filtered_students_by_group($courseid, $groupid = 0, $accessuserid = 0, $restrictto = '', $excludeusers = '') {
        global $DB;

        $students = static::get_filtered_students($courseid, $accessuserid, $restrictto, $excludeusers);

        $sql = "SELECT DISTINCT gm.userid
                  FROM {groups_members} gm
                 WHERE gm.groupid = ?";
        $members = array_column($DB->get_records_sql($sql, [$groupid]), 'userid');

        $students = array_values(array_intersect($students, $members));

        return $students;
    }


    /**
     * Helper function to get the students enrolled.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $accessuserid. The user id that is being viewed.
     * @return int[]
     */
    public static function get_enrolled_students_by_group($courseid, $groupid = 0) {
        global $DB;

        $students = static::get_enrolled_students($courseid);

        $sql = "SELECT DISTINCT gm.userid
                  FROM {groups_members} gm
                 WHERE gm.groupid = ?";
        $members = array_column($DB->get_records_sql($sql, [$groupid]), 'userid');

        $students = array_values(array_intersect($students, $members));

        return $students;
    }


    public static function is_staff_profile() {
        global $USER;

        profile_load_custom_fields($USER);
        $campusroles = strtolower($USER->profile['CampusRoles']);
        if (strpos($campusroles, 'staff') !== false) {
            return true;
        }

        return false;
    }

    public static function is_hide_ps_grades() {
        global $USER;

        profile_load_custom_fields($USER);
        if (isset($USER->profile['hidepsgrades'])) {
            if ($USER->profile['hidepsgrades']) {
                return true;
            }
        }

        return false;
    }

    public static function is_grader() {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        if (has_capability('mod/assign:grade', $context)) {
            return true;
        }

        // If the course has ended, past years, the assign:grade capability will be no. Use the following instead.
        // https://docs.moodle.org/400/en/Capabilities/moodle/course:reviewotherusers
        if ($COURSE->enddate && $COURSE->enddate < time()) {
            if (has_capability('moodle/course:reviewotherusers', $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper function to add extra display info for user.
     *
     * @param stdClass $user
     * @return stdClass $user
     */
    public static function load_user_display_info(&$user) {
        global $PAGE;

        // Fullname.
        $user->fullname = fullname($user);
        // Fullname reverse
        $user->fullnamereverse = "{$user->lastname}, {$user->firstname}";

        // Profile photo.
        $userphoto = new \user_picture($user);
        $userphoto->size = 2; // Size f2.
        $user->profilephoto = $userphoto->get_url($PAGE)->out(false);
    }


    public static function get_users_mentors($userid, $field = 'username') {
        global $DB;

        $mentors = [];
        $mentorssql = "SELECT u.*
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE c.instanceid = :menteeid
                          AND c.contextlevel = :contextlevel
                          AND ra.contextid = c.id
                          AND u.id = ra.userid";
        $mentorsparams = [
            'menteeid' => $userid,
            'contextlevel' => CONTEXT_USER,
        ];
        if ($mentors = $DB->get_records_sql($mentorssql, $mentorsparams)) {
            $mentors = array_column($mentors, $field);
        }
        return $mentors;
    }

    public static function get_users_mentees($userid, $field = 'username') {
        global $DB;

        // Get mentees for user.
        $mentees = [];
        $menteessql = "SELECT u.*
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE ra.userid = :mentorid
                          AND ra.contextid = c.id
                          AND c.instanceid = u.id
                          AND c.contextlevel = :contextlevel";
        $menteesparams = [
            'mentorid' => $userid,
            'contextlevel' => CONTEXT_USER,
        ];
        if ($mentees = $DB->get_records_sql($menteessql, $menteesparams)) {
            $mentees = array_column($mentees, $field);
        }
        return $mentees;
    }


    public static function get_myconnect_data($username, $gradeid, $page = 0, $excludeattachments = []) {
        global $CFG, $USER, $OUTPUT;

        $attachments = [];

        if (file_exists($CFG->dirroot . '/local/myconnect/lib.php')) {
            // Load users through MyConnect.
            $loggedinuser = \local_myconnect\utils::get_user_with_extras($USER->username);
            $timelineuser = \local_myconnect\utils::get_user_with_extras($username);
            // Get the posts.
            $posts = \local_myconnect\persistents\post::get_timeline($timelineuser, $page, 10);
            // Export the posts data.
            $relateds = [
                'context' => \context_system::instance(),
                'posts' => $posts,
                'jump' => 0,
                'page' => $page,
                'timelineuser' => $timelineuser,
                'loggedinuser' => $loggedinuser,
                'embed' => 0,
            ];
            $timeline = new \local_myconnect\external\timeline_exporter(null, $relateds);
            $myconnect = $timeline->export($OUTPUT);

            $releasepostids = task::get_grade_release_posts($gradeid);

            // Convert posts to attachments array.
            $attachments = [];
            if (isset($myconnect->posts)) {
                foreach ($myconnect->posts as $post) {
                    // Don't include attachments from posts that were created by this system as they are already available in other posts.
                    if (in_array($post->id, $releasepostids)) {
                        continue;
                    }
                    $attachments = array_merge($attachments, $post->formattedattachments);
                }
            }

            // Take already selected attachments out.
            if ($attachments && $excludeattachments) {
                foreach ($excludeattachments as $exattt) {
                    foreach($attachments as $i => $attachment) {
                        if ($attachment->id == $exattt) {
                            unset($attachments[$i]);
                        }
                    }
                }
            }
        }

        return $attachments;
    }

    public static function get_myconnect_data_for_attachments($username, $attachmentids) {
        global $CFG, $OUTPUT;

        $attachments = [];
        $context = \context_system::instance();
        $fs = get_file_storage();
        foreach($attachmentids as $attachmentid) {
            // Get the file.
            $file = $fs->get_file_by_id($attachmentid);
            if ($file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = new \pix_icon(file_file_icon($file), get_mimetype_description($file));
                $iconimage = $iconimage->export_for_template($OUTPUT);
                $iconimage = $iconimage['attributes'][2]['value']; // src attribute.

                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/local_myconnect/attachment/'.$file->get_itemid().'/'.$filename);

                $isimage = strpos($mimetype, 'image') !== false ? 1 : 0;
                $isvideo = strpos($mimetype, 'video') !== false ? 1 : 0;
                $canpreview = ($isimage || strpos($mimetype, 'mp4') !== false) ? 1 : 0;

                $attachment = [
                    'id' => $file->get_id(),
                    'postid' => $file->get_itemid(),
                    'filename' => $filename,
                    'formattedfilename' => format_text($filename, FORMAT_HTML, ['context' => $context]),
                    'mimetype' => $mimetype,
                    'iconimage' => $iconimage,
                    'path' => $path,
                    'canpreview' => $canpreview,
                    'isimage' => $isimage,
                    'isvideo' => $isvideo,
                    'contenthash' => $file->get_contenthash(),
                ];
                $attachments[] = (object) $attachment;
            }
        }

        return $attachments;
    }

    public static function create_myconnect_file_reference($postid, $fileid) {
        global $DB;

        // Set up a new file record for the db. Remove the id so that a new record is inserted.
        $file = $DB->get_record('files', ['id' => $fileid]);
        if (empty($file)) {
            return 0;
        }

        $newfile = clone $file;
        unset($newfile->id);
        $newfile->contextid = 1;
        $newfile->component = 'local_myconnect';
        $newfile->filearea = 'attachment';
        $newfile->itemid = $postid;
        $newfile->pathnamehash = sha1("/$newfile->contextid/$newfile->component/$newfile->filearea/$newfile->itemid/$newfile->filename");

        $fs = get_file_storage();
        $reference = $fs->pack_reference($file); // Setup the reference to original file.
        $aliasfile = $fs->create_file_from_reference($newfile, 2, $reference); // Create the reference. Use "local" repository.
        return $aliasfile->get_id();
    }

    public static function get_task_as_json($task) {
        $obj = new \stdClass();
        $obj->id = $task->get('id');
        $obj->taskname = $task->get('taskname');
        $obj->pypuoi = $task->get('pypuoi');
        $obj->outcomes = $task->get('outcomes');
        $obj->criterionjson = $task->get('criterionjson');
        $obj->evidencejson = $task->get('evidencejson');
        return json_encode($obj);
    }

    public static function get_taskdata_as_html($data) {
        global $OUTPUT;

        $criterions = json_decode($data->criterionjson);
        $data->criteriahtml = $OUTPUT->render_from_template('mod_psgrading/diff_criterion_row_text', ['criterions' => $criterions]);

        $evidences = json_decode($data->evidencejson);
        $data->evidencehtml = $OUTPUT->render_from_template('mod_psgrading/diff_evidence_text', ['evidences' => $evidences]);

        $snip = nl2br($data->outcomes);
        $snip = str_replace("\t", '', $snip); // remove tabs
        $snip = str_replace("\n", '', $snip); // remove new lines
        $snip = str_replace("\r", '', $snip); // remove carriage returns
        $data->outcomes = $snip;

        $html = $OUTPUT->render_from_template('mod_psgrading/diff_task_text', $data);

        return $html;
    }

    public static function diff_versions($json1, $json2) {

        $olddata = json_decode($json1);
        $newdata = json_decode($json2);

        $fromtext = static::get_taskdata_as_html($olddata);
        $totext = static::get_taskdata_as_html($newdata);

        $opcodes = \FineDiff::getDiffOpcodes($fromtext, $totext);
        return htmlspecialchars_decode(\FineDiff::renderDiffToHTMLFromOpcodes($fromtext, $opcodes));

    }

    public static function get_user_preferences($cmid, $name, $default) {
        global $DB, $USER;

        $value = $DB->get_field('psgrading_userprefs', 'value', [
            'cmid' => $cmid,
            'userid' => $USER->id,
            'name' => $name,
        ]);

        return $value ? $value : $default;
    }

    public static function set_user_preference($cmid, $name, $value) {
        global $DB, $USER;

        $preference = $DB->get_record('psgrading_userprefs', [
            'cmid' => $cmid,
            'userid' => $USER->id,
            'name' => $name,
        ]);

        if ($preference) {
            $preference->value = $value;
            $DB->update_record('psgrading_userprefs', $preference);
        } else {
            $object = new \stdClass();
            $object->cmid = $cmid;
            $object->userid = $USER->id;
            $object->name = $name;
            $object->value = $value;
            $DB->insert_record('psgrading_userprefs', $object);
        }
    }

    public static function invalidate_cache($cmid, $name) {
        global $DB;
        if ($name) {
            $sql = "DELETE
                      FROM {" . task::TABLE_GRADES_CACHE . "}
                     WHERE " . $DB->sql_like('name', ':name') . "
                       AND cmid = :cmid";
            $DB->execute($sql, ['name' => $name, 'cmid' => $cmid]);

            // invalidate the course too.
            if ($name == 'list-%') {
                $courseid = $DB->get_field('course_modules', 'course', ['id' => $cmid]);
                $sql = "DELETE
                          FROM {" . task::TABLE_GRADES_CACHE . "}
                         WHERE " . $DB->sql_like('name', ':name') . "
                           AND cmid = :cmid";
                $DB->execute($sql, ['name' => 'list-course-%', 'cmid' => $courseid]);
            }
        }
    }

    public static function invalidate_cache_by_taskid($taskid, $name) {
        global $DB;
        if ($name) {
            $sql = "DELETE c
                      FROM {" . task::TABLE_GRADES_CACHE . "} c,
                           {" . task::TABLE . "} t
                     WHERE t.id = :taskid
                       AND c.cmid = t.cmid
                       AND " . $DB->sql_like('c.name', ':name');
            $DB->execute($sql, ['name' => $name, 'taskid' => $taskid]);
        }
    }

    public static function get_cache($cmid, $name) {
        global $DB;
        return $DB->get_record(task::TABLE_GRADES_CACHE, [
            'cmid' => $cmid,
            'name' => $name,
        ]);
    }

    public static function save_cache($cmid, $name, $value) {
        global $DB;
        if ($name) {
            $DB->insert_record(task::TABLE_GRADES_CACHE, [
                'cmid' => $cmid,
                'name' => $name,
                'value' => $value,
            ]);
        }
    }

    public static function median($values) {
        $count = count($values);
        if ($count === 0) {  return null;
        }
        sort($values);
        $half = floor($count / 2);
        if ($count % 2) { return $values[$half];
        }
        return ($values[$half - 1] + $values[$half]) / 2.0;
    }


    public static function image_fix_orientation($source, $mimetype) {
        $exif = exif_read_data($source);

        if (empty($exif['Orientation'])) {
            return;
        }

        $image = null;
        if ($mimetype === 'image/jpeg') {
            $image = imagecreatefromjpeg($source);
        }
        if ($mimetype === 'image/png') {
            $image = imagecreatefrompng($source);
        }
        if (!$image) {
            return;
        }

        if (in_array($exif['Orientation'], [3, 4])) {
            $image = imagerotate($image, 180, 0);
        }
        if (in_array($exif['Orientation'], [5, 6])) {
            $image = imagerotate($image, -90, 0);
        }
        if (in_array($exif['Orientation'], [7, 8])) {
            $image = imagerotate($image, 90, 0);
        }
        if (in_array($exif['Orientation'], [2, 5, 7, 4])) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($mimetype === 'image/jpeg') {
            imagejpeg($image, $source);
        }
        if ($mimetype === 'image/png') {
            imagepng($image, $source);
        }
    }

    public static function get_deleted_tasks($cmid) {
        global $DB;

        $sql = "SELECT id, creatorusername, taskname, pypuoi, published
                FROM {psgrading_tasks}
                WHERE id = :id AND deleted = :deleted;";
        $params = ['id' =>$cmid, 'deleted' => 1];

        $r =  $DB->get_records_sql($sql, $params);

        return $r;

    }

    public static function check_evidence_visibility($taskid) {
        global $DB;

        $evidences = task::get_evidences($taskid);
        $counthidden = 0;

        // Get the mod_psgrading cmid and check if that one is visible.
        $psgradingcmid = $DB->get_field('psgrading_tasks', 'cmid', ['id' => $taskid]);
        $module =$cm = get_coursemodule_from_id('', $psgradingcmid);

        if (!$module->visible) {
            return  -1;
        }

        foreach($evidences as $evidence) {

            if ($evidence->evidencetype == 'cm_giportfoliochapter') {
                $split = explode('_', $evidence->refdata);
                $cmid = $split[0];
                $cminstance = $split[1];
                $chapterid = $split[2];

                $cm = get_coursemodule_from_id('', $cmid);

                // If the full portfolio is hidden dont worry about the chapter
                if(!$cm->visible) {
                    $counthidden++;
                    continue;
                } else {
                    $ischapterhidden = $DB->get_field('giportfolio_chapters', 'hidden', ['id' => $chapterid]);
                    if ($ischapterhidden == "1") {
                        $counthidden++;
                    }

                }

            } else {

                $cm = get_coursemodule_from_id('', $evidence->refdata);

                if (!$cm->course) { // Evidence was deleted and its returning null.
                    continue;
                }

                $cm = get_coursemodule_from_id('', $evidence->refdata);

                if (!$cm->visible) {
                    $counthidden++;
                }
            }
        }

        return $counthidden;
    }

}
