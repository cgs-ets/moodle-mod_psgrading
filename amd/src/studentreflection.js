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
 * Provides the mod_psgrading/restudentreflectionporting module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/studentreflection
 */
define(['jquery', 'core/log'], 
    function($, Log) {    
    'use strict';

    /**
     * Initializes the studentreflection component.
     */
    function init() {
        Log.debug('mod_psgrading/studentreflection: initializing');

        var rootel = $('#page-mod-psgrading-studentreflection');

        if (!rootel.length) {
            Log.error('mod_psgrading/studentreflection: #page-mod-psgrading-studentreflection not found!');
            return;
        }

        var studentreflection = new StudentReflection(rootel);
        studentreflection.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function StudentReflection(rootel) {
        var self = this;
        self.rootel = rootel;
        self.form = rootel.find('form[data-form="psgrading-studentreflection"]');
    }

    /**
     * Run the Audience Selector.
     *
     */
     StudentReflection.prototype.main = function () {
      var self = this;

      // Cancel.
      self.rootel.on('click', '#btn-cancel', function(e) {
        e.preventDefault();
        window.onbeforeunload = null;
        self.form.find('[name="action"]').val('cancel');
        self.form.submit();
      });

      // Save.
      self.rootel.on('click', '#btn-save', function(e) {
        e.preventDefault();
        window.onbeforeunload = null;
        self.form.find('[name="action"]').val('save');
        self.form.submit();
      });

    };

    return {
        init: init
    };
});