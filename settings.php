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
 * Plugin administration pages are defined here.
 *
 * @package     mod_psgrading
 * @category    admin
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

	$options = array('', "mysqli", "oci", "pdo", "pgsql", "sqlite3", "sqlsrv");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect(
        'mod_psgrading/dbtype', 
        get_string('dbtype', 'mod_psgrading'), 
        get_string('dbtype_desc', 'mod_psgrading'), 
        '', 
        $options
    ));
    $settings->add(new admin_setting_configtext('mod_psgrading/dbhost', get_string('dbhost', 'mod_psgrading'), get_string('dbhost_desc', 'mod_psgrading'), 'localhost'));
    $settings->add(new admin_setting_configtext('mod_psgrading/dbuser', get_string('dbuser', 'mod_psgrading'), '', ''));
    $settings->add(new admin_setting_configpasswordunmask('mod_psgrading/dbpass', get_string('dbpass', 'mod_psgrading'), '', ''));
    $settings->add(new admin_setting_configtext('mod_psgrading/dbname', get_string('dbname', 'mod_psgrading'), '', ''));
    $settings->add(new admin_setting_configtext('mod_psgrading/staffclassessql', get_string('staffclassessql', 'mod_psgrading'), '', ''));
    $settings->add(new admin_setting_configtext('mod_psgrading/classstudentssql', get_string('classstudentssql', 'mod_psgrading'), '', ''));

    $name = 'mod_psgrading/s1cutoffmonth';
    $title = get_string('s1cutoffmonth', 'mod_psgrading');
    $default = 9;
    $type = PARAM_INT;
    $setting = new admin_setting_configtext($name, $title, '', $default, $type);
    $settings->add($setting);

    $name = 'mod_psgrading/s1cutoffday';
    $title = get_string('s1cutoffday', 'mod_psgrading');
    $default = 15;
    $type = PARAM_INT;
    $setting = new admin_setting_configtext($name, $title, '', $default, $type);
    $settings->add($setting);


}
