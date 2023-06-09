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
 * Provides the mod_psgrading/teacherreflection module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/teacherreflection
 */
define(['jquery', 'core/log'], 
    function($, Log) {    
    'use strict';

    /**
     * Initializes the teacherreflection component.
     */
    function init() {
        Log.debug('mod_psgrading/teacherreflection: initializing');

        var rootel = $('#page-mod-psgrading-teacherreflection');

        if (!rootel.length) {
            Log.error('mod_psgrading/teacherreflection: #page-mod-psgrading-teacherreflection not found!');
            return;
        }

        var teacherreflection = new TeacherReflection(rootel);
        teacherreflection.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function TeacherReflection(rootel) {
        var self = this;
        self.rootel = rootel;
        self.form = rootel.find('form[data-form="psgrading-teacherreflection"]');
    }

    /**
     * Run the Audience Selector.
     *
     */
    TeacherReflection.prototype.main = function () {
      var self = this;

      // Cancel edit.
      self.rootel.on('click', 'input[name="cancel"]', function(e) {
          self.rootel.find('[name="action"]').val('cancel');
      });

      // Save.
      self.rootel.on('click', 'input[name="save"]', function(e) {
          self.rootel.find('[name="action"]').val('save');
      });

    };

    return {
        init: init
    };
});