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
 * Provides {@link mod_psgrading\external\api} class.
 *
 * @package   mod_psgrading
 * @category  external
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace mod_psgrading\external;


defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
require_once($CFG->libdir.'/externallib.php');

 /**
  * Provides an external API of the plugin.
  *
  * @copyright 2020 Michael Vangelovski
  * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class api extends external_api {
    use apicontrol;
    use get_activities_in_course;
    use get_tasks_in_activity;
    use restore_task;
}
