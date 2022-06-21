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
    function init(courseid, year, period) {
        Log.debug('mod_psgrading/reporting: initializing');

        var rootel = $('.psgrading-reporting');

        if (!rootel.length) {
            Log.error('mod_psgrading/reporting: .psgrading-reporting not found!');
            return;
        }

        var reporting = new Reporting(rootel, courseid, year, period);
        reporting.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Reporting(rootel, courseid, year, period) {
        var self = this;
        self.rootel = rootel;
        self.courseid = courseid;
        self.year = year;
        self.period = period;
    }

    /**
     * Run the Audience Selector.
     *
     */
     Reporting.prototype.main = function () {
        var self = this;

        $(window).click(function() {
          //Hide grade menus if open.
          if (self.opentype == 'effort') {
            self.closeElements();
            //self.rootel.find('#reporting-grademenu').remove();
            //self.rootel.find('.report-element').removeClass('options-open');
          }
        });

        // Open element for grading.
        self.rootel.on('click', '.report-element', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var element = $(this);
            if (!element.hasClass('options-open') ) {
              self.openElement(element);
            }
        });

        // Save an effort grade.
        self.rootel.on('click', '#reporting-grademenu label', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var effortel = $(this);
          self.saveEffort(effortel);
        });

        // Save a text reflection.
        self.rootel.on('click', '#reporting-grademenu .save', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var btn = $(this);
          self.saveText(btn);
        });
        // Cancel a text reflection.
        self.rootel.on('click', '#reporting-grademenu .cancel', function(e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeElements();
      })

        // Preload the templates.
        self.templates = {
          FORM: 'mod_psgrading/reporting_grademenu',
        };
        var preloads = [];
        preloads.push(self.loadTemplate('FORM'));
        $.when.apply($, preloads).then(function() {
            self.rootel.removeClass('preloading').addClass('preloads-completed');
        });

    };

    Reporting.prototype.openElement = function (element) {
        var self = this;

        var type = element.data('type');
        self.opentype = type;
        var existinggrade = element.data('grade');
        var optionsarea = element.find('.options-area');
        var hiddentext = element.find('.element-reflection');
        var templatedata = {};
        templatedata["is" + type] = true;
        templatedata["is" + existinggrade] = true;
        templatedata["reflection"] = hiddentext.val();

        Templates.render(self.templates.FORM, templatedata).done(function(tmpl) {
          self.closeElements();
          element.addClass('options-open');

          // Add the dropdown in.
          optionsarea.html(tmpl);
        });
        
    };

    Reporting.prototype.closeElements = function () {
      var self = this;
      self.rootel.find('#reporting-grademenu').remove();
      self.rootel.find('.report-element').removeClass('options-open');
    }


    Reporting.prototype.saveEffort = function (effortel) {
      var self = this;

      var grade = effortel.data('value');
      var minimal = effortel.data('minimal');

      var element = effortel.closest('.report-element');
      var subjectarea = element.data('subjectarea');
      var type = element.data('type');
      var row = element.closest('.reporting-row');

      //self.rootel.find('#reporting-grademenu').remove();
      self.closeElements();
      element.addClass('submitting');

      Ajax.call([{
        methodname: 'mod_psgrading_apicontrol',
        args: { 
            action: 'grade_element',
            data: JSON.stringify({
                courseid: self.courseid,
                year: self.year,
                period: self.period,
                username: row.data('username'),
                subjectarea: subjectarea,
                type: type,
                grade: grade,
            }),
        },
        done: function(success) {
          element.removeClass('submitting');
          if (success) {
            element.attr('data-grade', grade);
            var label = subjectarea;
            if (minimal) {
              label += " (" + minimal + ")";
            }
            element.find('.subjectgrade').html(label);
          }
        },
        fail: function(reason) {
          Log.debug(reason);
          element.removeClass('submitting');
        }
      }]);

    };

    Reporting.prototype.saveText = function (btn) {
      var self = this;

      var element = btn.closest('.report-element');
      var hiddentext = element.find('.element-reflection');
      var textel = element.find('.element-text');
      var reflection = textel.val();
      var subjectarea = element.data('subjectarea');
      var type = element.data('type');
      var row = element.closest('.reporting-row');

      self.closeElements();
      element.addClass('submitting');

      Ajax.call([{
        methodname: 'mod_psgrading_apicontrol',
        args: { 
            action: 'grade_element',
            data: JSON.stringify({
                courseid: self.courseid,
                year: self.year,
                period: self.period,
                username: row.data('username'),
                subjectarea: subjectarea,
                type: type,
                reflection: reflection,
            }),
        },
        done: function(success) {
          element.removeClass('submitting');
          if (success && reflection.length) {
            element.attr('data-grade', 'text_graded');
            hiddentext.val(reflection);
          } else {
            element.attr('data-grade', '');
          }
        },
        fail: function(reason) {
          Log.debug(reason);
          element.removeClass('submitting');
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
    /*Reporting.prototype.loadModal = function (modalkey, title, buttontext, type) {
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
    }*/

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