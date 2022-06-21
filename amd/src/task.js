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
 * Provides the mod_psgrading/task module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/task
 */
define(['jquery', 'core/log', 'core/templates', 'core/ajax', 'core/str'], 
    function($, Log, Templates, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the task component.
     */
    function init() {
        Log.debug('mod_psgrading/task: initializing');

        var rootel = $('form[data-form="psgrading-task"]');

        if (!rootel.length) {
            Log.error('mod_psgrading/tasks: form[data-form="psgrading-task"] not found!');
            return;
        }

        var task = new Task(rootel);
        task.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Task(rootel) {
        var self = this;
        self.rootel = rootel;
        
        // Get the stub criterion.
        var txt = document.createElement("textarea");
        txt.innerHTML = self.rootel.find('.criterion-selector').data('stub');
        self.stubcriterion = $.parseJSON(txt.value);

        // Setup initial json.
        self.regenerateEvidenceJSON();
        self.regenerateCriterionJSON();
    }

    /**
     * Run the Audience Selector.
     *
     */
   Task.prototype.main = function () {
        var self = this;

        // Cancel edit.
        self.rootel.on('click', '#btn-cancel', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('cancel');
            self.rootel.submit();
        });

        // Delete task.
        self.rootel.on('click', '#btn-delete', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('delete');
            self.rootel.submit();
        });

        // Save.
        self.rootel.on('click', '#btn-save', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('save');
            self.regenerateEvidenceJSON();
            self.regenerateCriterionJSON();
            self.rootel.submit();
        });

        // Add criterion.
        self.rootel.on('click', '#btn-addcriterion', function(e) {
            e.preventDefault();
            self.addCriterion();
        });

        // Hide criterion.
        self.rootel.on('click', '.toggle', function(e) {
            var toggle = $(this);
            self.toggleCriterion(toggle);
        });

        // Delete criterion.
        self.rootel.on('click', '.btn-delete', function(e) {
            e.preventDefault();
            var button = $(this);
            self.deleteCriterion(button);
        });

        // Toggle evidence.
        self.rootel.on('click', '.activity.labelonly', function(e) {
          e.preventDefault();
          var toggle = $(this);
          self.toggleEvidence(toggle);
        });
        

        // Styling the criterion selects based on selected option.
        self.rootel.on('change', 'select', function(e) {
            var select = $(this);
            select.removeClass('selected');
            if (select.children(':selected').val()) {
                select.addClass('selected');
            }
            //select.attr('class', 'form-control').addClass(select.children(':selected').val());
        });
        self.rootel.find('select').change();

        // Preload the modals and templates.
        self.templates = {
            CRITERION: 'mod_psgrading/criterion_selector_row',
        };
        var preloads = [];
        preloads.push(self.loadTemplate('CRITERION'));
        // Do not show actions until preloads are complete.
        $.when.apply($, preloads).then(function() {
            self.rootel.addClass('preloads-completed');
        })

        // Set up drag reordering of criterions.
        if(typeof Sortable != 'undefined') {
            var el = document.getElementById('task-criterions');
            var sortable = new Sortable(el, {
                handle: '.btn-reorder',
                animation: 150,
                ghostClass: 'reordering',
                //onEnd: self.SortEnd
            });
        }
    };

    Task.prototype.SortEnd = function (e) {
        self = this;
        //console.log(e);
    };

    /**
     * Regenerate evidence json.
     *
     * @method
     */
    Task.prototype.regenerateEvidenceJSON = function () {
      var self = this;

      var evidencejson = $('input[name="evidencejson"]');
      var evidences = new Array();

      self.rootel.find('.evidence-selector .activity .cmid:checked').each(function() {
          var checkbox = $(this);
          var cm = {
              evidencetype: 'cm_' + checkbox.data('modname'),
              refdata: checkbox.val(),
          };
          evidences.push(cm);
      });

      // Encode to json and add tag to hidden input.
      var evidencesStr = '';
      if (evidences.length) {
          evidencesStr = JSON.stringify(evidences);
      }

      evidencejson.val(evidencesStr);
    };

    /**
     * Regenerate criterion json.
     *
     * @method
     */
    Task.prototype.regenerateCriterionJSON = function () {
        var self = this;

        var criterionjson = $('input[name="criterionjson"]');
        var criterions = new Array();

        self.rootel.find('.criterions .tbl-tr').each(function() {
            var row = $(this);
            var subject = row.find('[name=subject]').val();
            if (subject == null){
                subject = '';
            }
            var criterion = {
                id: row.data('id'),
                description: row.find('[name=description]').val(),
                level5: row.find('[name=level5]').val(),
                level4: row.find('[name=level4]').val(),
                level3: row.find('[name=level3]').val(),
                level2: row.find('[name=level2]').val(),
                level1: row.find('[name=level1]').val(),
                subject: subject,
                weight: row.find('[name=weight]').val(),
                hidden: row.find('[name=hidden]').is(":checked") ? 0 : 1,
            };
            criterions.push(criterion);
        });

        // Encode to json and add tag to hidden input.
        var criterionsStr = '';
        if (criterions.length) {
            criterionsStr = JSON.stringify(criterions);
            criterionjson.val(criterionsStr);
        }

        return criterionsStr;
    };

    /**
     * Add a new blank criterion to the form.
     *
     * @method
     */
    Task.prototype.addCriterion = function () {
        var self = this;

        var stubcriterion = {"criterions": [self.stubcriterion]};

        Templates.render(self.templates.CRITERION, stubcriterion)
            .done(function(html) {
                self.rootel.find('.criterions').append(html);
            });
    };

    /**
     * Toggle criterion visibility.
     *
     * @method
     */
    Task.prototype.toggleCriterion = function (toggle) {
        var self = this;

        var control = toggle.closest('.hidden-control');
        var tbltr = control.closest('.tbl-tr');
        var input = control.find('input');
        var desc = control.find('.desc');
        if (input.is(":checked")) {
            // Checked = visible. Make it hidden.
            input.prop('checked', false);
            tbltr.addClass('criterion-hidden');
            desc.html('Hidden from students');
        } else {
            // Unchecked = hidden. Make it visible.
            input.prop('checked', true);
            tbltr.removeClass('criterion-hidden');
            desc.html('Visible');
        }
    };

    /**
     * Delete criterion.
     *
     * @method
     */
    Task.prototype.deleteCriterion = function (button) {
        var self = this;

        var tbltr = button.closest('.tbl-tr');
        tbltr.fadeOut(200, function() {
            $(this).remove();
        });
    };

    /**
     * Toggle evidence visibility.
     *
     * @method
     */
     Task.prototype.toggleEvidence = function (toggle) {
      var self = this;
      var cmid = toggle.data('cmid');

      if (toggle.hasClass("subhidden")) {
        // Show subs.
        self.rootel.find('.activity.sub[data-cmid="' + cmid + '"]').show();
        toggle.removeClass("subhidden");
      } else {
        // Hide subs.
        self.rootel.find('.activity.sub[data-cmid="' + cmid + '"]').hide();
        toggle.addClass("subhidden");
      }
  };

    /**
     * Helper used to preload a template
     *
     * @method loadModal
     * @param {string} templatekey The property of the global templates variable
     * @return {object} jQuery promise
     */
    Task.prototype.loadTemplate = function (templatekey) {
        var self = this;
        return Templates.render(self.templates[templatekey], {});
    }

    return {
        init: init
    };
});