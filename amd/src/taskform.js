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
 * @module mod_psgrading/taskform
 */
define(['jquery', 'core/log', 'core/templates', 'core/ajax', 'core/str'], 
    function($, Log, Templates, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the taskform component.
     */
    function init(stubcriterion) {
        Log.debug('mod_psgrading/task: initializing');

        var rootel = $('form[data-form="psgrading-task"]');

        if (!rootel.length) {
            Log.error('mod_psgrading/tasks: form[data-form="psgrading-task"] not found!');
            return;
        }

        var taskform = new TaskForm(rootel, stubcriterion);
        taskform.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function TaskForm(rootel, stubcriterion) {
        var self = this;
        self.rootel = rootel;
        self.formjson = self.getFormJSON();
        self.autosaving = false;
        self.savestatus = self.rootel.find('#savestatus');
        self.stubcriterion = stubcriterion;
    }

    /**
     * Run the Audience Selector.
     *
     */
   TaskForm.prototype.main = function () {
        var self = this;

        // Auto-save when leaving a field.
        self.rootel.on('blur', 'input[type="text"], select, textarea', function(e) {
            var input = $(this);
            var isRubricInput = !!input.closest('.criterions').length;
            if (isRubricInput) {
                self.regenerateRubricJSON();
            }
            self.autoSave();
        });

        // Auto-save on evidence selection.
        self.rootel.on('change', '.evidence-selector .cmid', function() {
            self.regenerateEvidenceJSON();
            self.autosaving = false; // Force an autosave.
            self.autoSave();
        });

        // Auto-save every 15 seconds.
        setInterval(function() {
            self.regenerateAndSave();
        }, 15000);

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

        // Save draft clicked.
        self.rootel.on('click', '#btn-savedraft', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            // Force an autosave.
            self.autosaving = false;
            self.regenerateAndSave(false);
            self.rootel.find('[name="action"]').val('savedraft');
            self.rootel.submit();
        });

        // Discard chages clicked.
        self.rootel.on('click', '#btn-discardchanges', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('discardchanges');
            self.rootel.submit();
        });

        // Publish clicked.
        self.rootel.on('click', '#btn-publish', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('publish');
            self.rootel.submit();
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
            CRITERION: 'mod_psgrading/rubric_selector_criterionform',
        };
        var preloads = [];
        preloads.push(self.loadTemplate('CRITERION'));
        // Do not show actions until preloads are complete.
        $.when.apply($, preloads).then(function() {
            self.rootel.addClass('preloads-completed');
        })

    };

    TaskForm.prototype.checkSelects = function () {
        
    };

    /**
     * Generate json from form fields.
     *
     * @method
     */
    TaskForm.prototype.getFormJSON = function () {
        var self = this;

        var id = $('input[name="edit"]').val();
        var taskname = $('input[name="taskname"]').val();
        var pypuoi = $('select[name="pypuoi"]').val();
        var outcomes = $('textarea[name="outcomes"]').val();
        var rubricjson = $('input[name="rubricjson"]').val();
        var evidencejson = $('input[name="evidencejson"]').val();

        var formdata = {
            id: id,
            taskname: taskname,
            pypuoi: pypuoi,
            outcomes: outcomes,
            rubricjson: rubricjson,
            evidencejson: evidencejson,
        };

        var formjson = JSON.stringify(formdata);

        return formjson;
    };

    /**
     * Regenerate json and autosave.
     *
     * @method
     */
    TaskForm.prototype.regenerateAndSave = function (async) {
        var self = this;

        self.regenerateEvidenceJSON();
        self.regenerateRubricJSON();
        self.autoSave(async);
    };

    /**
     * Autosave progress.
     *
     * @method
     */
    TaskForm.prototype.autoSave = function (async) {
        var self = this;

        // Check if saving already in-progress.
        if (self.autosaving) {
            return;
        }

        var formjson = self.getFormJSON();

        // Do not autosave if nothing is entered yet. Empty formjson is 205 chars.
        if (formjson.length <= 205) {
            return;
        }

        // Check if form data has changed.
        if (self.formjson == formjson) {
            return;
        }

        self.formjson = formjson;
        self.statusSaving();

        if (typeof async === "undefined") {
            async = true;
        }

        Ajax.call([{
            methodname: 'mod_psgrading_autosave',
            args: { formjson: formjson },
            done: function() {
                self.statusSaved();
            },
            fail: function(reason) {
                Log.debug(reason);
                self.statusSaveFailed();
            }
        }], async);
    };

    /**
     * Regenerate evidence json.
     *
     * @method
     */
    TaskForm.prototype.regenerateEvidenceJSON = function () {
        var self = this;

        var evidencejson = $('input[name="evidencejson"]');
        var evidences = new Array();

        self.rootel.find('.evidence-selector .activity .cmid:checked').each(function() {
            var checkbox = $(this);
            var cm = {
                type: 'cm',
                data: checkbox.val(),
            };
            evidences.push(cm);
        });

        // Encode to json and add tag to hidden input.
        var evidencesStr = '';
        if (evidences.length) {
            evidencesStr = JSON.stringify(evidences);
            evidencejson.val(evidencesStr);
        }

        return evidencesStr;
    };

    /**
     * Regenerate rubric json.
     *
     * @method
     */
    TaskForm.prototype.regenerateRubricJSON = function () {
        var self = this;

        var rubricjson = $('input[name="rubricjson"]');
        var criterions = new Array();

        self.rootel.find('.criterions .tbl-tr').each(function() {
            var row = $(this);
            var criterion = {
                description: row.find('[name=description]').val(),
                level2: row.find('[name=level2]').val(),
                level3: row.find('[name=level3]').val(),
                level4: row.find('[name=level4]').val(),
                subject: row.find('[name=subject]').val(),
                weight: row.find('[name=weight]').val(),
                hidden: row.find('[name=hidden]').is(":checked") ? 0 : 1,
            };
            criterions.push(criterion);
        });

        // Encode to json and add tag to hidden input.
        var criterionsStr = '';
        if (criterions.length) {
            criterionsStr = JSON.stringify(criterions);
            rubricjson.val(criterionsStr);
        }

        return criterionsStr;
    };

    /**
     * Add a new blank criterion to the form.
     *
     * @method
     */
    TaskForm.prototype.addCriterion = function () {
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
    TaskForm.prototype.toggleCriterion = function (toggle) {
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
        self.autosaving = false; // Force an autosave.
        self.regenerateAndSave(); 
    };

    /**
     * Delete criterion.
     *
     * @method
     */
    TaskForm.prototype.deleteCriterion = function (button) {
        var self = this;

        var tbltr = button.closest('.tbl-tr');
        tbltr.fadeOut(200, function() {
            $(this).remove();
            self.autosaving = false; // Force an autosave.
            self.regenerateAndSave(); 
        });
    };

    /**
     * Set status to autosaving.
     *
     */
    TaskForm.prototype.statusSaving = function () {
        var self = this;
        self.autosaving = true;
        self.savestatus.html('<div class="badge badge-secondary"><div class="spinner"><div class="circle spin"></div></div> Auto saving</div>');
        // Add the before unload alert back in. The text returned is ignored by browsers but there as a fallback.
        window.onbeforeunload = function() {
            return "Are you sure?";
        };
    }

    /**
     * Set status to autosaved.
     *
     */
    TaskForm.prototype.statusSaved = function () {
        var self = this;
        self.autosaving = false;
        self.savestatus.html('<div class="badge badge-secondary"><i class="fa fa-check" aria-hidden="true"></i> Draft saved</div>');

        window.onbeforeunload = null;
    }    

    /**
     * Set status to save failed.
     *
     */
    TaskForm.prototype.statusSaveFailed = function () {
        var self = this;
        self.autosaving = false;
        self.savestatus.html('<div class="badge badge-secondary"><i class="fa fa-cross" aria-hidden="true"></i> Changes unsaved</div>');
        // Add the before unload alert back in. The text returned is ignored by browsers but there as a fallback.
        window.onbeforeunload = function() {
            return "Are you sure?";
        };
    }

    /**
     * Helper used to preload a template
     *
     * @method loadModal
     * @param {string} templatekey The property of the global templates variable
     * @return {object} jQuery promise
     */
    TaskForm.prototype.loadTemplate = function (templatekey) {
        var self = this;
        return Templates.render(self.templates[templatekey], {});
    }

    return {
        init: init
    };
});