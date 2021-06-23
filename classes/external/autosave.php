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
 * Provides {@link mod_psgrading\external\post_reply} trait.
 *
 * @package   mod_psgrading
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace mod_psgrading\external;

defined('MOODLE_INTERNAL') || die();

use \mod_psgrading\persistents\task;
use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->libdir.'/externallib.php');

/**
 * Trait implementing the external function mod_psgrading_autosave.
 */
trait autosave {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function autosave_parameters() {
        return new external_function_parameters([
            'formjson' => new external_value(PARAM_RAW, 'Form data'),
            'logthis' => new external_value(PARAM_BOOL, 'Whether to log the draft'),
        ]);
    }

    /**
     * Autosave the form data.
     *
     * @param int $query The search query
     */
    public static function autosave($formjson) {
        global $USER;

        // Validate params.
        self::validate_parameters(self::autosave_parameters(), compact('formjson', 'logthis'));
       
        // Save.
        return task::save_draft($formjson, $logthis);

    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function autosave_returns() {
         return new external_value(PARAM_INT, 'Result');
    }
}
