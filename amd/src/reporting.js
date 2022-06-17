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
 * Provides the mod_psgrading/reporting module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/reporting
 */
define(['jquery', 'core/log', 'core/ajax', 'core/modal_factory', 'core/modal_events', 'core/templates'], 
    function($, Log, Ajax, ModalFactory, ModalEvents, Templates) {    
    'use strict';

    /**
     * Initializes the reporting component.
     */
    function init(year, period) {
        Log.debug('mod_psgrading/reporting: initializing');

        var rootel = $('.psgrading-reporting');

        if (!rootel.length) {
            Log.error('mod_psgrading/reporting: .psgrading-reporting not found!');
            return;
        }

        var reporting = new Reporting(rootel, year, period);
        reporting.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Reporting(rootel, year, period) {
        var self = this;
        self.rootel = rootel;
        self.year = year;
        self.period = period;
    }

    /**
     * Run the Audience Selector.
     *
     */
     Reporting.prototype.main = function () {
        var self = this;

        // Open element for grading.
        self.rootel.on('click', '.report-element', function(e) {
            e.preventDefault();
            var element = $(this);
            self.openElement(element);
        });

        // Preload the modals and templates.
        self.modals = {
          ELEMENT: null,
        };
        self.templates = {
          FORM: 'mod_psgrading/reporting_modal_form',
        };
        var preloads = [];
        preloads.push(self.loadModal('ELEMENT', '', 'Save', ModalFactory.types.SAVE_CANCEL));
        preloads.push(self.loadTemplate('EFFORT'));
        preloads.push(self.loadTemplate('TEXT'));
        $.when.apply($, preloads).then(function() {
            self.rootel.removeClass('preloading').addClass('preloads-completed');
        });

    };

    Reporting.prototype.openElement = function (element) {
        var self = this;

        if (self.modals.ELEMENT) {

          var type = element.data('type');
          var row = element.closest('.reporting-row');
          var studentinfo = row.find('.student-info').html();
          var subjectarea = element.data('subjectarea');
          var existinggrade = element.data('grade');
          var title = studentinfo ? studentinfo + ': ' + subjectarea : 'Set ' + subjectarea;
          self.modals.ELEMENT.setTitle(title);

          var templatedata = {};
          templatedata["is" + type] = true;
          templatedata["is" + existinggrade] = true;

          console.log(templatedata);

          Templates.render(self.templates.FORM, templatedata).done(function(html) {
            self.modals.ELEMENT.setBody(html);
          });

          self.modals.ELEMENT.getRoot().on(ModalEvents.save, function(e) {
            // Get grade selected, or text, and check it before proceeding.
            var grade = document.querySelector('input[name="effort"]:checked').value;
            Ajax.call([{
                methodname: 'mod_psgrading_apicontrol',
                args: { 
                    action: 'grade_element',
                    data: JSON.stringify({
                        username: row.data('username'),
                        subjectarea: subjectarea,
                        grade: grade,
                    }),
                },
                done: function() {
                    //window.location.reload(false);
                },
                fail: function(reason) {
                    Log.debug(reason);
                }
            }]);
          });

          self.modals.ELEMENT.show();
        }        
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
     Reporting.prototype.loadModal = function (modalkey, title, buttontext, type) {
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

    /**
     * Helper used to preload a template
     *
     * @method loadTemplate
     * @param {string} templatekey The property of the global templates variable
     * @return {object} jQuery promise
     */
     Reporting.prototype.loadTemplate = function (templatekey) {
      var self = this;
      return Templates.render(self.templates[templatekey], {});
    }

    return {
        init: init
    };
});