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

        var rootel = $('#page-mod-psgrading-mark');

        if (!rootel.length) {
            Log.error('mod_psgrading/mark: #page-mod-psgrading-mark not found!');
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
        self.form = self.rootel.find('form[data-form="psgrading-mark"]');
        self.userid = userid;
        self.taskid = taskid;
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
        self.rootel.on('click', '#btn-save', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('save');
            self.form.submit();
        });

        // Save and show next.
        self.rootel.on('click', '#btn-saveshownext', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.regenerateCriterionJSON();
            self.rootel.find('[name="action"]').val('saveshownext');
            self.form.submit();
        });

        // Reset.
        self.rootel.on('click', '#btn-reset', function(e) {
            e.preventDefault();
            window.onbeforeunload = null;
            self.rootel.find('[name="action"]').val('reset');
            self.form.submit();
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
        
        // Handle infinite load for myconnect posts.
        var scrolltimer;
        var frame = self.rootel.find('.myconnect-selector .frame');
        frame.scroll(function() {
            var el = $(this);
            clearTimeout(scrolltimer);
            scrolltimer = setTimeout(function () {
                if(el.scrollTop() + el.innerHeight() >= el[0].scrollHeight) {
                    self.loadNextMyConnectPosts();
                }
            }, 500);
        });

        // If page is bigger than content and there is potentially more, go ahead and load.
        var myconnecttimer = setInterval(function() {
            var el = self.rootel.find('.myconnect-selector .frame');
            var windowHeight = el.innerHeight();
            var contentHeight = el[0].scrollHeight;
            var nextPage = self.rootel.find('input[name="myconnectnextpage"]').val();
            if (windowHeight > contentHeight && nextPage > 0) {
                // There is room for more.
                self.loadNextMyConnectPosts();
            } else {
                clearTimeout(myconnecttimer);
            }
        }, 3000);

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

        // Select posts.
        self.rootel.on('mousedown', '.myconnect-selector .post', function(e) {
            var post = $(this);

            if (post.hasClass('selected')) {
                post.removeClass('selected');
            } else {
                post.addClass('selected');
            }

            var btnAdd = self.rootel.find('.myconnect-selector .btn-add');
            btnAdd.addClass('disabled');

            var numSelected = self.rootel.find('.myconnect-selector .post.selected').length;
            if (numSelected) {
                btnAdd.removeClass('disabled');
            }

        });

        // Add posts as evidence.
        self.rootel.on('click', '.myconnect-selector .btn-add', function(e) {
            e.preventDefault();
            self.addMyConnectPosts();
        });

        // Save comment to bank.
        self.rootel.on('click', '.myconnect-carousel .selector', function(e) {
            e.preventDefault();
            var button = $(this);
            self.removeMyConnectPost(button);
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
        criterion.find('.level').removeClass('selected');
        level.addClass('selected');
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
    Mark.prototype.loadNextMyConnectPosts = function () {
        var self = this;

        if (self.loadingmyconnect) {
            return;
        }

        self.loadingmyconnect = true;
        var page = self.rootel.find('input[name="myconnectnextpage"]');
        var posts = self.rootel.find('.myconnect-selector .posts');

        var data = {
            'username': self.rootel.find('.selected-student').data('username'),
            'page': page.val(),
        };

        Ajax.call([{
            methodname: 'mod_psgrading_apicontrol',
            args: { 
                action: 'load_next_myconnect_posts',
                data: JSON.stringify(data),
            },
            done: function(html) {
                // Append the posts and update Masonry.
                var content = $(html);
                posts.append(content);
                if (typeof self.msnry !== 'undefined') {
                    //self.msnry.appended( content );
                    self.msnry.layout();
                }

                // Potentially more.
                if (html) {
                    page.val(page.val() + 1);
                    self.loadingmyconnect = false;
                } else {
                    page.val(-1);
                }
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
            self.msnry = new Masonry( '.myconnect-selector .posts', {
                itemSelector: '.post-wrap',
                columnWidth: 10,
                horizontalOrder: true
            });
        } else {
            self.msnry.layout();
        }

        // Preselect posts that have already been added.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        if (myconnectevidencejson.val()) {
            var postids = JSON.parse(myconnectevidencejson.val());
            for (i = 0; i < postids.length; i++) {
                var id = postids[i];
                self.rootel.find('.myconnect-selector .post[data-id="' + id + '"]').addClass('selected');
            }
        }
        
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
    };

    /**
     * Add MyConnect post to carousel.
     *
     * @method
     */
    Mark.prototype.addMyConnectPosts = function () {
        var self = this;

        // Get selected posts.
        var selectedPosts = self.rootel.find('.myconnect-selector .post.selected');

        // Clear selected.
        selectedPosts.removeClass('selected');

        // Get the carousel for selected posts and clear it.
        var carousel = self.rootel.find('.myconnect-carousel');
        carousel.html('');

        // Loop through the selected posts.
        var postids = new Array();
        selectedPosts.each(function() {
            var post = $(this).clone();

            // Add the id to the array.
            postids.push(post.data('id'));

            //Add the post to the carousel.
            carousel.append(post);
        });
        var postids = postids.filter(self.onlyUnique);

        // Encode id array to json for the hidden input.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        var postidsStr = '';
        if (postids.length) {
            postidsStr = JSON.stringify(postids);
            myconnectevidencejson.val(postidsStr);
        }

        // Update carousel.
        self.updateCarousel();

        // Close the selector.
        self.closeMyConnectSelector();
    };

    /**
     * Remove MyConnect post from carousel.
     *
     * @method
     */
    Mark.prototype.removeMyConnectPost = function (button) {
        var self = this;

        var post = button.closest('.post');
        var id = post.data('id');

        // Update the json.
        var myconnectevidencejson = self.rootel.find('input[name="myconnectevidencejson"]');
        var postids = JSON.parse(myconnectevidencejson.val());
        postids = self.removeFromArray(postids, id);
        var postidsStr = JSON.stringify(postids);
        myconnectevidencejson.val(postidsStr);

        // Remove the post element.
        self.rootel.find('.myconnect-carousel .post[data-id="' + id + '"]').remove();

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
        myconnectevidence.removeClass('has-posts');
        var numPosts = myconnectevidence.find('.post').length;
        if (numPosts) {
            myconnectevidence.addClass('has-posts');
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