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

        var rootel = $('#page-mod-psgrading-reporting');

        if (!rootel.length) {
            Log.error('mod_psgrading/reporting: #page-mod-psgrading-reporting not found!');
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
        self.rootelinner = self.rootel.find('.psgrading-reporting');
        self.editable = self.rootelinner.hasClass('editable');
    }

    /**
     * Run the Audience Selector.
     *
     */
    Reporting.prototype.main = function () {
      var self = this;

      if (self.editable) {

        $(window).click(function() {
          //Hide grade menus if open.
          if (self.opentype == 'effort') {
            self.closeElements();
          }
        });

        // Open effort element for grading.
        self.rootel.on('click', '.report-element[data-type="effort"]', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var element = $(this);
          if (!element.hasClass('options-open') ) {
            self.openInline(element);
          }
        });

        // Open text element for grading.
        self.rootel.on('click', '.report-element[data-type="text"]', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var element = $(this);
          if (!element.hasClass('options-open') ) {
            self.openPopout(element);
          }
        });

        // Open popout for grading.
        self.rootel.on('click', '.open-popout', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var element = $(this).closest('.report-element');
          self.openPopout(element);
        });

        // Select an effort grade.
        self.rootel.on('click', '.options-area label', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var effortel = $(this);
          self.saveEffort(effortel);
        });

        // Save a text reflection.
        self.rootel.on('click', '.options-area .save', function(e) {
          e.preventDefault();
          e.stopPropagation();
          var btn = $(this);
          self.saveText(btn);
        });
        // Cancel a text reflection.
        self.rootel.on('click', '.options-area .cancel', function(e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeElements();
        });

        // Preload the templates.
        self.templates = {
          INLINE: 'mod_psgrading/reporting_inlinegrading',
          POPOUT: 'mod_psgrading/reporting_popoutgrading',
        };
        var preloads = [];
        preloads.push(self.loadTemplate('INLINE'));
        preloads.push(self.loadTemplate('POPOUT'));
        $.when.apply($, preloads).then(function() {
            self.rootel.removeClass('preloading').addClass('preloads-completed');
        });

      }

      // Change reporting period.
      self.rootel.on('change', '.reportingperiod-select', function(e) {
        var select = $(this);
        var url = select.find(':selected').data('viewurl');
        if (url) {
            window.location.replace(url);
        }
      });

    };

    Reporting.prototype.openInline = function (element) {
        var self = this;

        var row = element.closest('.reporting-row');
        var type = element.data('type');
        self.opentype = type;
        var existinggrade = element.data('grade');
        var optionsarea = element.find('.options-area');
        var hiddentext = element.find('.element-reflection');
        var templatedata = {};
        templatedata["is" + type] = true;
        templatedata["is" + existinggrade] = true;
        templatedata["reflection"] = hiddentext.val();
        Templates.render(self.templates.INLINE, templatedata).done(function(tmpl) {
          self.closeElements();
          row.addClass('dropdown-focussed');
          element.addClass('options-open');

          // Add the dropdown in.
          optionsarea.html(tmpl);
        });
        
    };


    Reporting.prototype.openPopout = function (element) {
      var self = this;

      var row = element.closest('.reporting-row');
      var type = element.data('type');
      self.opentype = type;
      var existinggrade = element.data('grade');
      var optionsarea = element.find('.options-area');
      var hiddentext = element.find('.element-reflection');
      var templatedata = {};
      templatedata["is" + type] = true;
      templatedata["is" + existinggrade] = true;
      templatedata["reflection"] = hiddentext.val();
      Templates.render(self.templates.POPOUT, templatedata).done(function(tmpl) {
        self.closeElements();
        // Open this element.
        row.addClass('popout-focussed');
        element.addClass('options-open');
        element.addClass('popout');
        // Add the dropdown in.
        optionsarea.html(tmpl);

        if (type == 'text') {
          // Load the help guide.
          Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'reporting_help',
                data: JSON.stringify({
                    courseid: self.courseid,
                    year: self.year,
                    period: self.period,
                    username: row.data('username'),
                    subjectarea: element.data('subjectarea'),
                }),
            },
            done: function(html) {
              if (html.length) {
                optionsarea.find('.help-area').html(html);
              } else {
                optionsarea.find('.help-area').html('No relevant task engagement data found for this subject.');
              }
            },
            fail: function(reason) {
              Log.debug(reason);
              optionsarea.find('.help-area').html('No relevant task engagement data found for this subject.');
            }
          }]);
        }

      });
      
  };

    Reporting.prototype.closeElements = function () {
      var self = this;
      self.rootel.find('#reporting-inlinegrading').remove();
      self.rootel.find('.report-element').removeClass('options-open');
      self.rootel.find('.report-element').removeClass('popout');
      self.rootel.find('.reporting-row').removeClass('dropdown-focussed');
      self.rootel.find('.reporting-row').removeClass('popout-focussed');
    }


    Reporting.prototype.saveEffort = function (effortel) {
      var self = this;

      var grade = effortel.data('value');
      var minimal = effortel.data('minimal');

      var element = effortel.closest('.report-element');
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
                grade: grade,
            }),
        },
        done: function(success) {
          element.removeClass('submitting');
          if (success) {
            element.attr('data-grade', grade);
            element.data('grade', grade);
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
          } else {
            element.attr('data-grade', '');
          }
          hiddentext.val(reflection);
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