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
 * Manage Importing task from one psgrading to another.
 *
 * @package   mod_psgrading
 * @copyright 2024, Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/log', 'core/templates'], function (Ajax, Log, Templates) {

    function init() {
        Log.debug('mod_psgrading/import_task: initialising');
        var importTask = new ImportTask();
        importTask.main();
    }

    /**
     * Constructor.
     */
    function ImportTask() {
        var self = this;
        self.courseSelection = document.getElementById('id_courseswithpsgrading');
        self.courseSelectionJSON = document.getElementById('id_selectedcourseJSON').value;
        self.taskSelectedJSON = document.getElementById('id_selectedtasksJSON').value;
    }

    ImportTask.prototype.main = function () {
        var self = this;

        self.initEventListeners();
    };

    ImportTask.prototype.initEventListeners = function () {
        var self = this;
        self.courseSelection.addEventListener('change', this.findPSActivitiesInCourse.bind(this));
    };

    ImportTask.prototype.findPSActivitiesInCourse = function (e) {
        var courseSelectionJSON = JSON.parse(this.courseSelectionJSON);

        // Get the selected options
        var selectedOptions = [];
        for (var i = 0; i < this.courseSelection.options.length; i++) {
            if (this.courseSelection.options[i].selected) {
                selectedOptions.push(this.courseSelection.options[i].value);
            }
        }

        // Update the courseSelectionJSON array
        courseSelectionJSON = selectedOptions;
        // Update the courseSelectionJSON property
        this.courseSelectionJSON = JSON.stringify(courseSelectionJSON);

        document.getElementById('id_selectedcourseJSON').value = this.courseSelectionJSON;
        // Update the input json

        // call the JSOn to get the  activities in the course
        this.getPSActivitiesInCourses();
    };

    ImportTask.prototype.getPSActivitiesInCourses = function () {
        var self = this;
        Ajax.call([{
            methodname: 'mod_psgrading_get_activities_in_course',
            args: {
                data: this.courseSelectionJSON
            },
            done: function (response) {
                var templatecontext = JSON.parse(response.templatecontext);
                console.log(templatecontext);
                var context = {
                    activities: templatecontext.activities,
                    size: templatecontext.size,
                    cmids: templatecontext.cmids
                }

                Templates.render('mod_psgrading/import_task_activities', context)
                    .then(function (html, js) {
                        // Check if there  is a select already rendererd. If so, replace it.
                        Templates.replaceNodeContents('.psgrading-import-task-modules-in-selected-course', html, js);
                        // Add  event listener to the activities.
                        self.setListenerForActivities();

                    })
                    .catch(function (error) {
                        console.error('Error rendering template:', error);
                    });
            },
            fail: function (reason) {
                console.log(reason);
            }

        }])

    };

    ImportTask.prototype.setListenerForActivities = function () {
        var selector = document.getElementById('id_activitiesincourseselected');
        selector.addEventListener('change', this.findTasks.bind(this));
    }

    ImportTask.prototype.findTasks = function (e) {
        var self = this;
        var cmids = JSON.parse(e.target.getAttribute('data-cmids'));
        var selectedCMIDS = [];
        var activitiesSelectedEl = document.getElementById('id_activitiesincourseselected');

        for (var i = 0; i < activitiesSelectedEl.options.length; i++) {
            if (activitiesSelectedEl.options[i].selected) {

                var val = activitiesSelectedEl.options[i].value;
                selectedCMIDS.push(cmids[val]);
            }
        }

        Ajax.call([{
            methodname: 'mod_psgrading_get_tasks_in_activity',
            args: {
                data: JSON.stringify(selectedCMIDS)
            },
            done: function (response) {
                var templatecontext = JSON.parse(response.templatecontext);
                console.log(templatecontext);
                var context = {
                    id: templatecontext.id,
                    tasks: templatecontext.tasks,
                    size: templatecontext.size,
                }

                Templates.render('mod_psgrading/import_task_tasks_in_activity', context)
                    .then(function (html, js) {
                        // Check if there  is a select already rendererd. If so, replace it.
                        Templates.replaceNodeContents('.psgrading-import-task-tasks-in-selected-activity', html, js);
                        // Add  event listener to the activities.
                        self.setListenerForTasks();

                    })
                    .catch(function (error) {
                        console.error('Error rendering template:', error);
                    });
            },
            fail: function (reason) {
                console.log(reason);
            }

        }]);

        ImportTask.prototype.setListenerForTasks = function () {
            var selector = document.getElementById('id_tasksinselectedactivity');
            selector.addEventListener('change', this.taskselected.bind(this));
        }

        ImportTask.prototype.taskselected = function (e) {
            var self = this;
            var taskSelectedJSON = JSON.parse(this.taskSelectedJSON);
            var selectedtaskid = [];
            var activitiesSelectedEl = document.getElementById('id_tasksinselectedactivity');

            for (var i = 0; i < activitiesSelectedEl.options.length; i++) {
                if (activitiesSelectedEl.options[i].selected) {
                    selectedtaskid.push(activitiesSelectedEl.options[i].value);
                }
            }
            taskSelectedJSON = selectedtaskid
            console.log(selectedtaskid);
            this.taskSelectedJSON = JSON.stringify(taskSelectedJSON);
            document.getElementById('id_selectedtasksJSON').value = this.taskSelectedJSON;
        }



    }

    return {
        init: init
    };
});
