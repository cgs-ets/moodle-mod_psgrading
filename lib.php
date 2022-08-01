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
 * Library of interface functions and constants.
 *
 * @package     mod_psgrading
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/** Include required files */
require_once($CFG->libdir.'/filelib.php');

/**
 * Serves the plugin attachments.
 *
 * @package  local_excursions
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_psgrading_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_psgrading/$filearea/$relativepath";

    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}


/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function psgrading_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_BACKUP_MOODLE2:  return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_psgrading into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_psgrading_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function psgrading_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('psgrading', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the mod_psgrading in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_psgrading_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function psgrading_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('psgrading', $moduleinstance);
}

/**
 * Removes an instance of the mod_psgrading from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function psgrading_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('psgrading', array('id' => $id));
    if (!$exists) {
        return false;
    }

    $DB->delete_records('psgrading', array('id' => $id));

    return true;
}

/**
 * Extends the global navigation tree by adding mod_psgrading nodes if there is a relevant content.
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $psgradingnode An object representing the navigation tree node.
 * @param stdClass $course.
 * @param stdClass $module.
 * @param cm_info $cm.
 */
function psgrading_extend_navigation($psgradingnode, $course, $module, $cm) {
}

/**
 * Extends the settings navigation with the mod_psgrading settings.
 *
 * This function is called when the context for the page is a mod_psgrading module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@see settings_navigation}
 * @param navigation_node $psgradingnode {@see navigation_node}
 */
function psgrading_extend_settings_navigation($settingsnav, $psgradingnode = null) {
}
