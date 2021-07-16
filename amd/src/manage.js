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
 * Provides the mod_psgrading/manage module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/manage
 */
define(['jquery', 'core/log', 'core/ajax', 'core/modal_factory', 'core/modal_events',], 
    function($, Log, Ajax, ModalFactory, ModalEvents) {    
    'use strict';

    /**
     * Initializes the manage component.
     */
    function init() {
        Log.debug('mod_psgrading/manage: initializing');

        var rootel = $('#page-mod-psgrading-manage');

        if (!rootel.length) {
            Log.error('mod_psgrading/manage: #page-mod-psgrading-manage not found!');
            return;
        }

        var manage = new Manage(rootel);
        manage.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Manage(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
    Manage.prototype.main = function () {
        var self = this;

        // Release.
        self.rootel.on('click', '.action-release', function(e) {
            e.preventDefault();
            var button = $(this);
            self.releaseTask(button);
        });

        // Undo release.
        self.rootel.on('click', '.action-undorelease', function(e) {
            e.preventDefault();
            var button = $(this);
            self.unreleaseTask(button);
        });

        // Delete draft.
        self.rootel.on('click', '.action-discarddraft', function(e) {
            e.preventDefault();
            var button = $(this);
            self.showDeleteDraft(button);
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

        // Preload the modals and templates.
        self.modals = {
            DIFF: null,
        };
        var preloads = [];
        preloads.push(self.loadModal('DIFF', 'Review draft changes and confirm deletion', 'Delete draft', ModalFactory.types.SAVE_CANCEL));
        $.when.apply($, preloads).then(function() {
            self.rootel.removeClass('preloading').addClass('preloads-completed');
        })

    };

    /**
     * Delete draft.
     *
     * @method
     */
    Manage.prototype.showDeleteDraft = function (button) {
        var self = this;

        if (self.modals.DIFF) {
            var task = button.closest('.task');

            if (task.hasClass('not-published')) {
                self.deleteDraft(task.data('id'));
            } else {
                // Get the diff.
                Ajax.call([{
                    methodname: 'mod_psgrading_apicontrol',
                    args: { 
                        action: 'get_diff',
                        data: task.data('id'),
                    },
                    done: function(html) {
                        self.modals.DIFF.setBody(html);
                    },
                    fail: function(reason) {
                        Log.debug(reason);
                        return "Failed to load diff."
                    }
                }]);

                // Set up the modal cevents.
                self.modals.DIFF.getModal().addClass('modal-xl');
                self.modals.DIFF.getRoot().on(ModalEvents.save, {self: self, taskid: task.data('id')}, self.handleDeleteDraft);
                self.modals.DIFF.show();
            }
        }
    };

    Manage.prototype.handleDeleteDraft = function (event) {
        var self = event.data.self;
        var taskid = event.data.taskid;

        self.deleteDraft(taskid);
    };

    Manage.prototype.deleteDraft = function (taskid) {
        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'delete_draft',
                data: taskid,
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
    Manage.prototype.releaseTask = function (button) {
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
     * Hide task grades.
     *
     * @method
     */
    Manage.prototype.unreleaseTask = function (button) {
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


    Manage.prototype.SortEnd = function (e) {
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

    /**
     * Helper used to preload a modal
     *
     * @method loadModal
     * @param {string} modalkey The property of the global modals variable
     * @param {string} title The title of the modal
     * @param {string} title The button text of the modal
     * @return {object} jQuery promise
     */
    Manage.prototype.loadModal = function (modalkey, title, buttontext, type) {
        var self = this;
        return ModalFactory.create({type: type}).then(function(modal) {
            modal.setTitle(title);
            if (buttontext) {
                modal.setSaveButtonText(buttontext);
            }
            self.modals[modalkey] = modal;
            // Preload backgrop.
            modal.getBackdrop();
            modal.getRoot().addClass('modal-' + modalkey);
        });
    }

    return {
        init: init
    };
});