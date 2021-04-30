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
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log, Ajax) {    
    'use strict';

    /**
     * Initializes the mark component.
     */
    function init(userid, taskid) {
        Log.debug('mod_psgrading/mark: initializing');

        var rootel = $('form[data-form="psgrading-mark"]');

        if (!rootel.length) {
            Log.error('mod_psgrading/mark: form[data-form="psgrading-mark"] not found!');
            return;
        }

        var mark = new MarkForm(rootel, userid, taskid);
        mark.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function MarkForm(rootel, userid, taskid) {
        var self = this;
        self.rootel = rootel;
        self.userid = userid;
        self.taskid = taskid;
    }

    /**
     * Run the Audience Selector.
     *
     */
    MarkForm.prototype.main = function () {
        var self = this;

        // Change student.
        self.rootel.on('change', '.student-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('markurl');
            if (url) {
                window.location.replace(url);
            }
        });

        // Change task.
        self.rootel.on('change', '.task-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('markurl');
            if (url) {
                window.location.replace(url);
            }
        });

        // Criterion level select.
        self.rootel.on('click', '.criterions .level', function(e) {
            e.preventDefault();
            var level = $(this);
            self.selectLevel(level);
            // Trigger check if user attempts to leave page.
            window.onbeforeunload = function() {
                return 'You have unsaved changes!';
            }
        });

        // Save.
        self.rootel.on('click', '#btn-save', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('save');
            self.rootel.submit();
        });

        // Save and show next.
        self.rootel.on('click', '#btn-saveshownext', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('saveshownext');
            self.rootel.submit();
        });

        // Reset.
        self.rootel.on('click', '#btn-reset', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('reset');
            self.rootel.submit();
        });

        // Save comment to bank.
        self.rootel.on('click', '#save-to-comment-bank', function(e) {
            e.preventDefault();
            self.saveComment();
        });

        // Append comment.
        self.rootel.on('click', '.comment', function(e) {
            e.preventDefault();
            var comment = $(this);
            var text = comment.find('.text').html();
            var textarea = self.rootel.find('#id_comment');
            if (textarea.val()) {
                text = textarea.val() + '\n' + text;
            }
            textarea.val(text);
        });

        // Delete comment from bank.
        self.rootel.on('click', '.comment .delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var button = $(this);
            self.deleteComment(button);
        });

    };


    /**
     * Select a criterion level
     *
     * @method
     */
    MarkForm.prototype.selectLevel = function (level) {
        var self = this;

        var criterion = level.closest('.criterion');
        criterion.find('.level').removeClass('selected');
        level.addClass('selected');
    };


    /**
     * Regenerate criterion json.
     *
     * @method
     */
    MarkForm.prototype.regenerateCriterionJSON = function () {
        var self = this;

        var criterionjson = $('input[name="criterionjson"]');
        var criterions = new Array();

        self.rootel.find('.criterion.tbl-tr').each(function() {
            var row = $(this);
            var selectedlevel = row.find('.level.selected').first().data('level');
            if (typeof selectedlevel === 'undefined') {
                selectedlevel = 0;
            }
            var criterion = {
                id: row.data('id'),
                selectedlevel: selectedlevel,
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
     * Select a criterion level
     *
     * @method
     */
    MarkForm.prototype.saveComment = function () {
        var self = this;

        var comment = self.rootel.find('textarea[name="comment"]');
        if (!comment.val().length) {
            return;
        }

        var commentbank = self.rootel.find('.comment-bank');
        commentbank.addClass('submitting');

        var data = {
            comment : comment.val(),
            taskid : self.taskid,
        };

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'save_comment',
                data: JSON.stringify(data),
            },
            done: function(html) {
                commentbank.find('.stored').html(html);
                commentbank.removeClass('submitting');
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);

    };

    /**
     * Select a criterion level
     *
     * @method
     */
    MarkForm.prototype.deleteComment = function (button) {
        var self = this;

        var comment = button.closest('.comment');
        comment.css('opacity', '0.4');

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'delete_comment',
                data: comment.data('id'),
            },
            done: function(response) {
                comment.fadeOut(300, function(){
                    $(this).remove();
                });
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);

    };

    return {
        init: init
    };
});