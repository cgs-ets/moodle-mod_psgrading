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
    function init(userid, taskid, quickmark) {
        Log.debug('mod_psgrading/mark: initializing');

        var rootel = $('#page-mod-psgrading-mark');

        if (!rootel.length) {
            Log.error('mod_psgrading/mark: #page-mod-psgrading-mark not found!');
            return;
        }

        var mark = new Mark(rootel, userid, taskid, quickmark);
        mark.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Mark(rootel, userid, taskid, quickmark) {
        var self = this;
        self.rootel = rootel;
        self.form = self.rootel.find('form[data-form="psgrading-mark"]');
        self.userid = userid;
        self.taskid = taskid;
        self.quickmark = quickmark;
        self.loadingmyconnect = false;
    }

    /**
     * Run the Audience Selector.
     *
     */
    Mark.prototype.main = function () {
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

        // Change group.
        self.rootel.on('change', '.group-select', function(e) {
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
            Log.debug("Adding leave page check...");
            window.onbeforeunload = function() {
                return 'You have unsaved changes!';
            }
        });

        // Save.
        self.rootel.on('click', 'input[name="save"]', function(e) {
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('save');
        });

        // Save and show next.
        self.rootel.on('click', 'input[name="saveshownext"]', function(e) {
          if (self.quickmark == '1') {
            e.preventDefault() // Added to do this via ajax instead.
            $(this).val('Saving... please wait');
            $(this).prop('disabled', true);
            self.regenerateCriterionJSON();
            window.onbeforeunload = null;

            // Do it via ajax...
            var data = $('form[data-form="psgrading-mark"]').serializeArray().reduce(function(obj, item) {
              obj[item.name] = item.value;
              return obj;
            }, {});

            data['taskid'] = self.taskid
            data['userid'] = self.userid
            Ajax.call([{
              methodname: 'mod_psgrading_apicontrol',
              args: { 
                  action: 'save_mark',
                  data: JSON.stringify(data),
              },
              done: function(response) {
                if (response) {
                  if (response >= 1) {
                    alert("Changes saved!")
                  }
                  // Notify parent that saving is done.
                  window.top.postMessage('saveshownext', '*')
                }
              },
              fail: function(reason) {
                alert(reason);
                $(this).val('Save and show next');
                $(this).prop('disabled', false);
                Log.debug(reason);
              }
            }]);
          } else {
            // Original code
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('saveshownext');
            // End original code
          }
        });

        // Cancel.
        self.rootel.on('click', 'input[name="cancel"]', function(e) {
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('cancel');
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
        
        // Handle infinite load for myconnect attachments.
        var scrolltimer;
        var frame = self.rootel.find('.myconnect-selector .frame');
        frame.scroll(function() {
            var el = $(this);
            clearTimeout(scrolltimer);
            scrolltimer = setTimeout(function () {
                if(el.scrollTop() + el.innerHeight() >= el[0].scrollHeight) {
                    self.loadNextMyConnectAttachments();
                }
            }, 500);
        });

        // MyConnect Selector events.
        self.rootel.on('click', '.myconnect-selector .btn-exit', function(e) {
            e.preventDefault();
            self.closeMyConnectSelector();
        });

        // MyConnect Selector browse.
        self.rootel.on('click', '#myconnect-evidence .btn-browse', function(e) {
            e.preventDefault();
            self.openMyConnectSelector();
        });

        // Select attachment.
        self.rootel.on('mousedown', '.myconnect-selector .attachment', function(e) {
            var attachment = $(this);

            if (attachment.hasClass('selected')) {
                attachment.removeClass('selected');
            } else {
                attachment.addClass('selected');
            }

            var btnAdd = self.rootel.find('.myconnect-selector .btn-add');
            btnAdd.addClass('disabled');

            var numSelected = self.rootel.find('.myconnect-selector .attachment.selected').length;
            if (numSelected) {
                btnAdd.removeClass('disabled');
            }

        });

        // Add attachments as evidence.
        self.rootel.on('click', '.myconnect-selector .btn-add', function(e) {
            e.preventDefault();
            self.addMyConnectAttachments();
        });

        // Save comment to bank.
        self.rootel.on('click', '.myconnect-carousel .selector', function(e) {
            e.preventDefault();
            var button = $(this);
            self.removeMyConnectAttachment(button);
        });


        self.rootel.find('input[name="didnotsubmit"]').change(function() {
          if(this.checked) {
            self.rootel.addClass('didnotsubmit');
          } else {
            self.rootel.removeClass('didnotsubmit');
          }      
        });

        


    };


    /**
     * Select a criterion level
     *
     * @method
     */
    Mark.prototype.selectLevel = function (level) {
        var self = this;

        var criterion = level.closest('.criterion');

        // If already selected, remove selection.
        if (level.hasClass('selected')) {
            criterion.find('.level').removeClass('selected');
        } else {
            criterion.find('.level').removeClass('selected');
            level.addClass('selected');
        }
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
    Mark.prototype.saveComment = function () {
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
    Mark.prototype.deleteComment = function (button) {
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


    /**
     * Select a criterion level
     *
     * @method
     */
    Mark.prototype.loadNextMyConnectAttachments = function () {
        var self = this;

        if (self.loadingmyconnect) {
            return;
        }

        self.loadingmyconnect = true;
        var page = self.rootel.find('input[name="myconnectnextpage"]');
        var attachments = self.rootel.find('.myconnect-selector .attachments');
        var selectedmyconnectfiles = self.rootel.find('input[name="selectedmyconnectjson"]');

        var data = {
            'username': self.rootel.find('.selected-student').data('username'),
            'page': page.val(),
            'selectedmyconnectfiles': selectedmyconnectfiles.val(),
        };

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'load_next_myconnect_attachments',
                data: JSON.stringify(data),
            },
            done: function(html) {
                // Append the attachments and update Masonry.
                var content = $(html);
                attachments.append(content);
                if (typeof self.msnry !== 'undefined') {
                    self.msnry.appended( content );
                    // Fix for masonry not correclty laying out images due to images not being loaded.
                    imagesLoaded( document.querySelector('.myconnect-selector'), function( instance ) {
                        self.msnry.layout();
                    });
                }

                // Potentially more.
                if (html) {
                    page.val(page.val() + 1);
                } else {
                    page.val(-1);
                }

                self.loadingmyconnect = false;
            },
            fail: function(reason) {
                Log.debug(reason);
                self.loadingmyconnect = false;
            }
        }]);
    };

    /**
     * Open the MyConnect selector.
     *
     * @method
     */
    Mark.prototype.openMyConnectSelector = function () {
        var self = this;

        // Show the frame.
        self.rootel.find('.myconnect-selector').show();
        $('body').css("overflow", "hidden");

        // Initialise masonry.
        if(typeof Masonry !== 'undefined' && typeof self.msnry === 'undefined') {
            self.msnry = new Masonry( '.myconnect-selector .attachments', {
                itemSelector: '.attachment-wrap',
                columnWidth: 10,
                horizontalOrder: true
            });
        } else {
            self.msnry.layout();
        }

        // Preselect attachments that have already been added.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        if (myconnectevidencejson.val() && myconnectevidencejson.val() != '[""]') {
            var ids = JSON.parse(myconnectevidencejson.val());
            for (i = 0; i < ids.length; i++) {
                var id = ids[i];
                self.rootel.find('.myconnect-selector .attachment[data-id="' + id + '"]').addClass('selected');
            }
        }

        // If page is bigger than content and there is potentially more, go ahead and load.
        /*var myconnectinterval = setInterval(function() {
            var el = self.rootel.find('.myconnect-selector .frame');
            var windowHeight = el.innerHeight();
            var contentHeight = el[0].scrollHeight;
            var nextPage = self.rootel.find('input[name="myconnectnextpage"]').val();
            if (windowHeight > contentHeight && nextPage > 0) {
                // There is room for more.
                self.loadNextMyConnectAttachments();
            } else {
                clearInterval(myconnectinterval);
            }
        }, 3000);*/

        self.loadNextMyConnectAttachments();
        
    };

    /**
     * Close the MyConnect selector.
     *
     * @method
     */
    Mark.prototype.closeMyConnectSelector = function () {
        var self = this;
        self.rootel.find('.myconnect-selector').hide();
        $('body').css("overflow", "");
        document.getElementById("myconnect-evidence").scrollIntoView();
        // Clear preselected.
        self.rootel.find('.myconnect-selector .attachment').removeClass('selected');
    };

    /**
     * Add MyConnect attachment to carousel.
     *
     * @method
     */
    Mark.prototype.addMyConnectAttachments = function () {
        var self = this;

        // Get selected attachments.
        var selectedattachments = self.rootel.find('.myconnect-selector .attachment.selected');

        // Clear selected.
        selectedattachments.removeClass('selected');

        // Get the carousel for selected attachments and clear it.
        var carousel = self.rootel.find('.myconnect-carousel');
        carousel.html('');

        // Loop through the selected attachments.
        var attids = new Array();
        selectedattachments.each(function() {
            var attachment = $(this).clone();

            // Add the id to the array.
            attids.push(attachment.data('id'));

            //Add the attachment to the carousel.
            carousel.append(attachment);
        });
        var attids = attids.filter(self.onlyUnique);

        // Encode id array to json for the hidden input.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        var attidsStr = '';
        if (attids.length) {
            attidsStr = JSON.stringify(attids);
            myconnectevidencejson.val(attidsStr);
        }

        // Update carousel.
        self.updateCarousel();

        // Close the selector.
        self.closeMyConnectSelector();
    };

    /**
     * Remove MyConnect attachment from carousel.
     *
     * @method
     */
    Mark.prototype.removeMyConnectAttachment = function (button) {
        var self = this;

        var attachment = button.closest('.attachment');
        var id = attachment.data('id');

        // Update the json.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        var ids = JSON.parse(myconnectevidencejson.val());
        ids = self.removeFromArray(ids, id);
        var idsStr = JSON.stringify(ids);
        myconnectevidencejson.val(idsStr);

        // Remove the attachment element.
        self.rootel.find('.myconnect-carousel .attachment[data-id="' + id + '"]').remove();

        // Update carousel.
        self.updateCarousel();
    };


    /**
     * Update carousel.
     *
     * @method
     */
    Mark.prototype.updateCarousel = function () {
        var self = this;
        var myconnectevidence = self.rootel.find('#myconnect-evidence');
        myconnectevidence.removeClass('has-attachments');
        var numattachments = myconnectevidence.find('.attachment').length;
        if (numattachments) {
            myconnectevidence.addClass('has-attachments');
        }
    }

    /**
     * Unique array helper.
     *
     * @method
     */
    Mark.prototype.onlyUnique = function (value, index, self) {
        return self.indexOf(value) === index;
    }

    /**
     * Remove element from array by value helper.
     *
     * @method
     */
    Mark.prototype.removeFromArray = function(array, item) {
        var index = array.indexOf(item);
        if (index !== -1) {
          array.splice(index, 1);
        }
        return array;
    };


    return {
        init: init
    };
});