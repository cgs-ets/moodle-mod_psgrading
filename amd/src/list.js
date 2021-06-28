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
 * Provides the mod_psgrading/list module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/list
 */
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log, Ajax) {    
    'use strict';

    /**
     * Initializes the list component.
     */
    function init() {
        Log.debug('mod_psgrading/list: initializing');

        var rootel = $('#page-mod-psgrading-view');

        if (!rootel.length) {
            Log.error('mod_psgrading/list: #page-mod-psgrading-view not found!');
            return;
        }

        var list = new List(rootel);
        list.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function List(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
    List.prototype.main = function () {
        var self = this;

        // Release.
        self.rootel.on('click', '.btn-release', function(e) {
            e.preventDefault();
            var button = $(this);
            if (!button.hasClass('submitting')) {
                button.addClass('submitting');
                button.html('<div class="spinner"><div class="circle spin"></div></div>');
                self.releaseTask(button);
            }
        });

        // Undo release.
        self.rootel.on('click', '.btn-undorelease', function(e) {
            e.preventDefault();
            var button = $(this);
            if (!button.hasClass('submitting')) {
                button.addClass('submitting');
                button.html('<div class="spinner"><div class="circle spin"></div></div>');
                self.unreleaseTask(button);
            }
        });

        // Set up drag reordering of criterions.
        if (typeof Sortable != 'undefined') {
            var el = document.getElementById('task-list');
            var sortable = new Sortable(el, {
                handle: '.btn-reorder',
                animation: 150,
                ghostClass: 'reordering',
                onEnd: self.SortEnd,
            });
        }

    };

    /**
     * Release a task.
     *
     * @method
     */
    List.prototype.releaseTask = function (button) {
        var self = this;

        var task = button.closest('.task');

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'release_task',
                data: task.data('id'),
            },
            done: function() {
                window.location.reload(false);
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);

    };

    /**
     * Release a task.
     *
     * @method
     */
    List.prototype.unreleaseTask = function (button) {
        var self = this;

        var task = button.closest('.task');

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'unrelease_task',
                data: task.data('id'),
            },
            done: function() {
                window.location.reload(false);
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);

    };


    List.prototype.SortEnd = function (e) {
        // Get new order.
        var tasks = new Array();
        $('#task-list .task').each(function() {
            tasks.push($(this).data('id'));
        });

        // Update via ajax.
        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'reorder_tasks',
                data: JSON.stringify(tasks),
            },
            done: function() {},
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);
    };

    return {
        init: init
    };
});