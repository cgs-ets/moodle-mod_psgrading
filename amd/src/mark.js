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
 * Provides the mod_psgrading/mark module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/mark
 */
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log, Ajax) {    
    'use strict';

    /**
     * Initializes the mark component.
     */
    function init(userid, taskid) {
        Log.debug('mod_psgrading/mark: initializing');

        var rootel = $('.mod_psgrading_mark');

        if (!rootel.length) {
            Log.error('mod_psgrading/mark: .mod_psgrading_mark not found!');
            return;
        }

        var mark = new Mark(rootel, userid, taskid);
        mark.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Mark(rootel, userid, taskid) {
        var self = this;
        self.rootel = rootel;
        self.userid = userid;
        self.taskid = taskid;
    }

    /**
     * Run the Audience Selector.
     *
     */
   Mark.prototype.main = function () {
        var self = this;

        self.loadMarks();

        // Save.
        self.rootel.on('click', '#btn-save', function(e) {
            e.preventDefault();
        });

        // Save and show next.
        self.rootel.on('click', '#btn-saveshownext', function(e) {
            e.preventDefault();
        });

        // Reset.
        self.rootel.on('click', '#btn-reset', function(e) {
            e.preventDefault();
        });

    };


    /**
     * Autosave progress.
     *
     * @method
     */
    Mark.prototype.loadMarks = function () {
        var self = this;

        Ajax.call([{
            methodname: 'mod_psgrading_load_task_marking',
            args: { 
                taskid: self.taskid, 
                userid: self.userid, 
            },
            done: function(response) {
                var task = self.rootel.find('.task');
                task.removeClass('loading');
                task.html(response);
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);
    };

    return {
        init: init
    };
});