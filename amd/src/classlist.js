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
 * Provides the mod_psgrading/classlist module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/classlist
 */
define(['jquery', 'core/log', 'core/ajax', 'core/modal_factory', 'core/modal_events', 'core/url'],
    function ($, Log, Ajax, ModalFactory, ModalEvents, URL) {
        'use strict';

        /**
         * Initializes the classlist component.
         */
        function init() {
            Log.debug('mod_psgrading/classlist: initializing');

            var rootel = $('.psgrading-overview-page');

            if (!rootel.length) {
                Log.error('mod_psgrading/classlist: .psgrading-overview-page not found!');
                return;
            }

            var classlist = new ClassList(rootel);
            classlist.main();
        }

        /**
         * The constructor
         *
         * @constructor
         * @param {jQuery} rootel
         */
        function ClassList(rootel) {
            var self = this;
            self.rootel = rootel;
        }

        /**
         * Run the Audience Selector.
         *
         */
        ClassList.prototype.main = function () {
            var self = this;

            self.checkCountdowns();

            // Change reporting period.
            self.rootel.on('change', '.reportingperiod-select', function (e) {
                var select = $(this);
                var url = select.find(':selected').data('viewurl');
                if (url) {
                    window.location.replace(url);
                }
            });

            // Change group.
            self.rootel.on('change', '.group-select', function (e) {
                var select = $(this);
                var url = select.find(':selected').data('viewurl');
                if (url) {
                    window.location.replace(url);
                }
            });

            // Release.
            self.rootel.on('click', '.action-release', function (e) {
                e.preventDefault();
                var button = $(this);
                // Check visibility of evidence first.
                self.checkEvidenceVisibility(button)
                .then(function(hidden) {
                    console.log('Evidence visibility status:', hidden);
                    self.releaseTask(button, hidden);
                })
                .catch(function(error) {
                    console.log('Error checking evidence visibility:', error);
                });
            });

            // Publish.
            self.rootel.on('click', '.action-publish', function (e) {
                e.preventDefault();
                var button = $(this);
                self.publishTask(button);
            });

            // Unpublish.
            self.rootel.on('click', '.action-unpublish', function (e) {
                e.preventDefault();
                var button = $(this);
                self.unpublishTask(button);
            });

            // Undo release.
            self.rootel.on('click', '.action-undorelease', function (e) {
                e.preventDefault();
                var button = $(this);


                self.unreleaseTask(button);
            });

            // Delete task.
            self.rootel.on('click', '.action-delete', function (e) {
                e.preventDefault();
                var button = $(this);
                self.deleteTask(button);
            });

            // Zoom
            self.rootel.on('click', '.btn-fullscreen', function (e) {
                e.preventDefault();
                var button = $(this);
                if (self.rootel.hasClass('zoomed')) {
                    self.rootel.removeClass('zoomed');
                    button.html('<i class="fa fa-window-maximize" aria-hidden="true"></i>');
                } else {
                    self.rootel.addClass('zoomed');
                    button.html('<i class="fa fa-window-minimize" aria-hidden="true"></i>');

                }
            });

            // Toggle
            self.rootel.on('click', '.btn-toggle', function (e) {
                e.preventDefault();
                var button = $(this);
                if (self.rootel.hasClass('toggleoff')) {
                    self.rootel.removeClass('toggleoff');
                    button.html('<i class="fa fa-toggle-off" aria-hidden="true"></i>');
                    self.showHiddenTasks();
                } else {
                    self.rootel.addClass('toggleoff');
                    button.html('<i class="fa fa-toggle-on" aria-hidden="true"></i>');
                    // Hide the columns of the activities that are
                    self.hideHiddenTasks();

                }
            });

            //Recycle bin
            self.rootel.on('click', '.btn-recycle', function (e) {
                e.preventDefault();
                var querystring =  window.location.search
                var urlParams = new URLSearchParams(querystring);
                console.log(urlParams.get('id'))
                var id = urlParams.get('id');
                var url =  URL.relativeUrl('/mod/psgrading/recyclebin.php', {
                                id: id,
                            }, true);
                if (url) {
                    window.location.replace(url);
                }
            });



            // Set up drag reordering of tasks.
            if (typeof Sortable != 'undefined') {
                var el = document.getElementById('task-list');
                var sortable = new Sortable(el, {
                    draggable: ".col-taskname",
                    handle: '.action-reorder',
                    animation: 150,
                    ghostClass: 'reordering',
                    onEnd: self.SortEnd,
                });
            }

            // Initialise listjs sorting.
            var options = {
                valueNames: ['col-firstname', 'col-lastname']
            };
            var studentlist = new List('class-list', options);

            // Update countdowns every so often.
            setInterval(function () {
                self.checkCountdowns();
            }, 9000); // Check every 9 seconds. Somewhat random.

            // Preload the modals and templates.
            self.modals = {
                DELETE: null,
                RELEASE: null,
            };
            var preloads = [];
            preloads.push(self.loadModal('DELETE', 'Confirm delete', 'Delete', ModalFactory.types.SAVE_CANCEL));
            preloads.push(self.loadModal('RELEASE', 'Confirm release', 'Release', ModalFactory.types.SAVE_CANCEL));
            $.when.apply($, preloads).then(function () {
                self.rootel.removeClass('preloading').addClass('preloads-completed');
            })

        };

        ClassList.prototype.deleteTask = function (button) {
            var self = this;

            var task = button.closest('.col-taskname');
            var subject = task.find('.text').first().html();

            //button.replaceWith('<div class="spinner"><div class="circle spin"></div></div>');

            if (self.modals.DELETE) {
                self.modals.DELETE.setBody('<p>Are you sure you want to delete this task?<br><strong>' + subject + '</strong></p>');
                self.modals.DELETE.getRoot().on(ModalEvents.save, function (e) {
                    Ajax.call([{
                        methodname: 'mod_psgrading_apicontrol',
                        args: {
                            action: 'delete_task',
                            data: task.data('id'),
                        },
                        done: function () {
                            window.location.reload(false);
                        },
                        fail: function (reason) {
                            Log.debug(reason);
                        }
                    }]);
                });
                self.modals.DELETE.show();
            }
        };


        /**
         * Publish a task.
         *
         * @method
         */
        ClassList.prototype.publishTask = function (button) {
            var task = button.closest('.col-taskname');

            button.replaceWith('<div class="spinner"><div class="circle spin"></div></div>');

            Ajax.call([{
                methodname: 'mod_psgrading_apicontrol',
                args: {
                    action: 'publish_task',
                    data: task.data('id'),
                },
                done: function () {
                    window.location.reload(false);
                },
                fail: function (reason) {
                    Log.debug(reason);
                }
            }]);
        };


        /**
         * Unpublish a task.
         *
         * @method
         */
        ClassList.prototype.unpublishTask = function (button) {
            var self = this;

            var task = button.closest('.col-taskname');

            button.replaceWith('<div class="spinner"><div class="circle spin"></div></div>');

            Ajax.call([{
                methodname: 'mod_psgrading_apicontrol',
                args: {
                    action: 'unpublish_task',
                    data: task.data('id'),
                },
                done: function () {
                    window.location.reload(false);
                },
                fail: function (reason) {
                    Log.debug(reason);
                }
            }]);
        };

        /**
         * Release a task.
         *
         * @method
         */
        ClassList.prototype.releaseTask = function (button, hidden) {
            var self = this;

            var task = button.closest('.col-taskname');
            var subject = task.find('.text').first().html();
            var message = '<p>Are you sure you want to release this task?<br><strong>' + subject + '</strong></p>';

            if (self.modals.RELEASE) {
                if(hidden > 0) {
                    message= '<p>Are you sure you want to release this task?<br><strong>' + subject + '</strong></p><p>One or more pieces of evidence that you selected for this task are hidden and will not be visible to parents.</p>'
                }
                self.modals.RELEASE.setBody(message);
                self.modals.RELEASE.getRoot().on(ModalEvents.save, function (e) {
                    Ajax.call([{
                        methodname: 'mod_psgrading_apicontrol',
                        args: {
                            action: 'release_task',
                            data: task.data('id'),
                        },
                        done: function () {
                            window.location.reload(false);
                        },
                        fail: function (reason) {
                            Log.debug(reason);
                        }
                    }]);
                });
                self.modals.RELEASE.show();
            }
        };

        /**
         * Hide task grades.
         *
         * @method
         */
        ClassList.prototype.unreleaseTask = function (button) {
            var self = this;

            var task = button.closest('.col-taskname');

            button.replaceWith('<div class="spinner"><div class="circle spin"></div></div>');

            Ajax.call([{
                methodname: 'mod_psgrading_apicontrol',
                args: {
                    action: 'unrelease_task',
                    data: task.data('id'),
                },
                done: function () {
                    window.location.reload(false);
                },
                fail: function (reason) {
                    Log.debug(reason);
                }
            }]);
        };

        ClassList.prototype.checkCountdowns = function () {
            var self = this;

            self.rootel.find('.action-undorelease').each(function () {
                var action = $(this);
                var task = action.closest('.col-taskname');
                Ajax.call([{
                    methodname: 'mod_psgrading_apicontrol',
                    args: {
                        action: 'get_countdown',
                        data: task.data('id'),
                    },
                    done: function (response) {
                        Log.debug(response);
                        if (response) {
                            action.attr('data-original-title', response);
                        } else {
                            window.location.reload(false);
                        }
                    },
                    fail: function (reason) {
                        Log.debug(reason);
                    }
                }]);
            });
        };


        ClassList.prototype.SortEnd = function (e) {

            // Don't reorder if moving to same index.
            if (e.oldIndex == e.newIndex) {
                return;
            }

            var button = $(e.item);  // dragged HTMLElement
            button.find('.btn-reorder').replaceWith('<div class="spinner"><div class="circle spin"></div></div>');

            $('.classlist-table').addClass('reordering');

            // Get new order.
            var tasks = new Array();
            $('#task-list .col-taskname').each(function () {
                tasks.push($(this).data('id'));
            });

            // Update via ajax.
            Ajax.call([{
                methodname: 'mod_psgrading_apicontrol',
                args: {
                    action: 'reorder_tasks',
                    data: JSON.stringify(tasks),
                },
                done: function () {
                    window.location.reload(false);
                },
                fail: function (reason) {
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
        ClassList.prototype.loadModal = function (modalkey, title, buttontext, type) {
            var self = this;
            return ModalFactory.create({ type: type }).then(function (modal) {
                modal.setTitle(title);
                if (buttontext) {
                    modal.setSaveButtonText(buttontext);
                }
                self.modals[modalkey] = modal;
                // Preload backgrop.
                modal.getBackdrop();
                modal.getRoot().addClass('modal-' + modalkey);
            });
        };

        /**
         * Helper function for the toggle
         * Hides the tasks that are hidden for students
         */
        ClassList.prototype.hideHiddenTasks = function () {
            var self = this;
            var trackHiddenTaskPerCM = new Map();
            var trackTasksPerCM = new Map();

            document.querySelectorAll('.not-published').forEach(function (el) {
                el.classList.add('toggleon');
                var key = el.getAttribute('data-cmid');

                if (trackHiddenTaskPerCM.get(key)) {
                    trackHiddenTaskPerCM.set(key, trackHiddenTaskPerCM.get(key) + 1);
                } else if (key != null) {
                    trackHiddenTaskPerCM.set(key, 1);
                }

                if (!trackTasksPerCM.get(key) && key != null) {
                    trackTasksPerCM.set(key, document.querySelectorAll('.col-taskname[data-cmid="' + key + '"]').length);
                }
            });


            if (trackHiddenTaskPerCM.size > 0 && trackTasksPerCM.size > 0) {
                self.recalculateHeaderWidth(trackTasksPerCM, trackHiddenTaskPerCM, true);
            }
        };

        /**
        * Helper function for the toggle
        * Shows the tasks that are hidde
        */
        ClassList.prototype.showHiddenTasks = function () {
            var self = this;
            var trackHiddenTaskPerCM = new Map();
            var trackTasksPerCM = new Map();

            document.querySelectorAll('.not-published').forEach(function (el) {
                el.classList.remove('toggleon');
                var key = el.getAttribute('data-cmid');

                if (trackHiddenTaskPerCM.get(key)) {
                    trackHiddenTaskPerCM.set(key, trackHiddenTaskPerCM.get(key) + 1);
                } else if (key != null) {
                    trackHiddenTaskPerCM.set(key, 1);
                }

                if (!trackTasksPerCM.get(key) && key != null) {
                    trackTasksPerCM.set(key, document.querySelectorAll('.col-taskname[data-cmid="' + key + '"]').length);
                }
            });

            if (trackHiddenTaskPerCM.size > 0 && trackTasksPerCM.size > 0) {
                self.recalculateHeaderWidth(trackTasksPerCM, trackHiddenTaskPerCM, false)
            }
        };
        /**
        * Helper function for the toggle
        */
        ClassList.prototype.recalculateHeaderWidth = function (trackTasksPerCM, trackHiddenTaskPerCM, hide) {

            document.querySelectorAll('.column.col-cm').forEach(function (task) {
                var cmid = task.getAttribute('data-cmid');
                var newWidth = undefined;
                var allTasks = trackTasksPerCM.get(cmid);
                var hiddenTasks = trackHiddenTaskPerCM.get(cmid);

                if (allTasks != undefined && hiddenTasks != undefined) {
                    newWidth = hide ? allTasks - hiddenTasks : allTasks
                }

                if (newWidth == 0) {
                    task.classList.add('toggleon')
                } else {
                    task.classList.remove('toggleon')
                }

                if (newWidth != undefined) {
                    task.style.width = 'calc(90px * ' + newWidth + ')';
                }
            });
        }

        ClassList.prototype.checkEvidenceVisibility = function(button) {
            var taskid = button.closest('.col-taskname').data('id');
            var status = 0;

            return new Promise(function(resolve, reject) {
                Ajax.call([{
                    methodname: 'mod_psgrading_apicontrol',
                    args: {
                        action: 'check_visibility',
                        data: taskid,
                    },
                    done: function(response) {

                        resolve(response);  // Resolve with the response value
                    },
                    fail: function(reason) {
                        Log.debug(reason);
                        reject(reason);  // Reject if there's an error
                    }
                }]);
            });

            return status;
        }


        return {
            init: init
        };
    });