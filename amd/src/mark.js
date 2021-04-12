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
define(['jquery', 'core/log', 'core/templates', 'core/ajax', 'core/str'], 
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
            methodname: 'mod_psgrading_loadmarks',
            args: { 
                taskid: self.taskid, 
                userid: self.userid, 
            },
            done: function() {
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Regenerate evidence json.
     *
     * @method
     */
    Mark.prototype.regenerateEvidenceJSON = function () {
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
     * Regenerate criterion json.
     *
     * @method
     */
    Mark.prototype.regenerateCriterionJSON = function () {
        var self = this;

        var criterionjson = $('input[name="criterionjson"]');
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
            criterionjson.val(criterionsStr);
        }

        return criterionsStr;
    };

    /**
     * Add a new blank criterion to the form.
     *
     * @method
     */
    Mark.prototype.addCriterion = function () {
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
    Mark.prototype.toggleCriterion = function (toggle) {
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
    Mark.prototype.deleteCriterion = function (button) {
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
    Mark.prototype.statusSaving = function () {
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
    Mark.prototype.statusSaved = function () {
        var self = this;
        self.autosaving = false;
        self.savestatus.html('<div class="badge badge-secondary"><i class="fa fa-check" aria-hidden="true"></i> Draft saved</div>');

        window.onbeforeunload = null;
    }    

    /**
     * Set status to save failed.
     *
     */
    Mark.prototype.statusSaveFailed = function () {
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
    Mark.prototype.loadTemplate = function (templatekey) {
        var self = this;
        return Templates.render(self.templates[templatekey], {});
    }

    return {
        init: init
    };
});