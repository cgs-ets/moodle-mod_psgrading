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
 * Provides the mod_psgrading/details module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/details
 */
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log, Ajax) {    
    'use strict';

    /**
     * Initializes the details component.
     */
    function init() {
        Log.debug('mod_psgrading/details: initializing');

        var rootel = $('.psgrading-details');

        if (!rootel.length) {
            Log.error('mod_psgrading/details: .psgrading-details not found!');
            return;
        }

        var details = new Details(rootel);
        details.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Details(rootel) {
        var self = this;
        self.rootel = rootel;
        self.matrix = rootel.find('.matrix');
    }

    /**
     * Run the Audience Selector.
     *
     */
    Details.prototype.main = function () {
        var self = this;

        // Change student.
        self.rootel.on('change', '.student-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('detailsurl');
            if (url) {
                window.location.replace(url);
            }
        });

        // Change task.
        self.rootel.on('change', '.task-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('detailsurl');
            if (url) {
                window.location.replace(url);
            }
        });

    };


    return {
        init: init
    };
});