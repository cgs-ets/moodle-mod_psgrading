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
 * Provides the mod_psgrading/classlist module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2025 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/classlist
 */

define (['jquery', 'core/log', 'core/ajax'], function ($, Log, Ajax){

    function init() {
        Log.debug('mod_psgrading/classlist: initializing');

        var rootel = $('#page-mod-psgrading-recyclebin');

        if (!rootel.length) {
            Log.error('mod_psgrading/classlist: .psgrading-overview-page not found!');
            return;
        }

        var recycle = new Recycle(rootel);
        recycle.main();

    }

     /**
         * The constructor
         *
         * @constructor
         * @param {jQuery} rootel
         */
     function Recycle(rootel) {
        var self = this;
        self.rootel = rootel;

    }

    Recycle.prototype.main = function () {
        var self = this;

        $('.ps-grading-recycle-task td.ps-grading-undo-del').each(function() {
            $(this).on('click', function(e) {
                console.log('Fila con la clase ps-grading-undo-del clickeada');
                var taskid = e.target.getAttribute('data-task-id');
                console.log(taskid);

                Ajax.call([{
                    methodname: 'mod_psgrading_restore_task',
                    args: {
                        taskid: taskid,
                    },
                    done: function(result) {

                        var rowToDelete = $('.ps-grading-recycle-task td.ps-grading-undo-del[data-task-id="' + taskid + '"]').closest('tr');
                        rowToDelete.remove();

                        // Check if the table has any rows left
                        var remainingRows = $('.ps-grading-recycle-task tr').length;

                        // If no rows are left, remove the table and display the message
                        if (remainingRows <= 1) {  // We subtract 1 because the table likely has a header row
                            $('.ps-grading-recycle-task').remove();  // Remove the entire table
                            $('.ps-grading-pagination').remove();  // Remove the nav
                            $('.ps-grading-recycle-container').append('<div class="alert alert-primary" role="alert">No deleted tasks were found.</div>');
                        }
                    },
                    fail: function(reason) {
                        console.log(reason);
                    }
                }]);
            });
        });


        var opts = {pagerSelector:'#myPager',showPrevNext:true,hidePageNumbers:false,perPage:15};
        self.pagination(opts)

    }

    Recycle.prototype.pagination = function(opts) {
        var $this = $('#ps-grading-recycle'),
            defaults = {
                perPage: 15, // Items per page
                showPrevNext: true, // Show previous/next buttons
                hidePageNumbers: false // Show page numbers
            },
            settings = $.extend(defaults, opts);

        var listElement = $this;
        var perPage = settings.perPage;
        var children = listElement.children();
        var pager = $('.pager'); // Pagination container

        // If childSelector is provided, select the children based on that
        if (typeof settings.childSelector != "undefined") {
            children = listElement.find(settings.childSelector);
        }

        // If pagerSelector is provided, select the pager container based on that
        if (typeof settings.pagerSelector != "undefined") {
            pager = $(settings.pagerSelector);
        }

        var numItems = children.length;
        var numPages = Math.ceil(numItems / perPage);

        pager.data("curr", 0);

        // Show previous link if required
        if (settings.showPrevNext) {
            $('<li class="page-item"><a href="#" class="prev_link page-link" aria-label="Previous"><span aria-hidden="true">«</span></a></li>').appendTo(pager);
        }

        // Create page numbers if not hidden
        var curr = 0;
        while (numPages > curr && !settings.hidePageNumbers) {
            var pageLink = $('<li class="page-item"><a href="#" class="page-link">' + (curr + 1) + '</a></li>');
            pager.append(pageLink);
            curr++;
        }

        // Show next link if required
        if (settings.showPrevNext) {
            $('<li class="page-item"><a href="#" class="next_link page-link" aria-label="Next"><span aria-hidden="true">»</span></a></li>').appendTo(pager);
        }

        // Set the first page as active
        pager.find('.page-item:first').addClass('active');
        pager.find('.prev_link').hide(); // Hide previous button on the first page
        if (numPages <= 1) {
            pager.find('.next_link').hide(); // Hide next button if there's only one page
        }

        // Hide all items initially
        children.hide();
        children.slice(0, perPage).show(); // Show the first set of items

        // Click event for page numbers
        pager.find('li .page-link').click(function() {
            var clickedPage = $(this).parent().index() - 1; // Get the page number (index adjusted)
            goTo(clickedPage);
            return false;
        });

        // Click event for previous link
        pager.find('.prev_link').click(function() {
            previous();
            return false;
        });

        // Click event for next link
        pager.find('.next_link').click(function() {
            next();
            return false;
        });

        // Go to the previous page
        function previous() {
            var goToPage = parseInt(pager.data("curr")) - 1;
            goTo(goToPage);
        }

        // Go to the next page
        function next() {
            var goToPage = parseInt(pager.data("curr")) + 1;
            goTo(goToPage);
        }

        // Go to a specific page
        function goTo(page) {
            var startAt = page * perPage;
            var endOn = startAt + perPage;

            // Hide all items and show the selected page
            children.css('display', 'none').slice(startAt, endOn).show();

            // Update the pagination state
            if (page >= 1) {
                pager.find('.prev_link').show(); // Show previous button if not on the first page
            } else {
                pager.find('.prev_link').hide(); // Hide previous button if on the first page
            }

            if (page < (numPages - 1)) {
                pager.find('.next_link').show(); // Show next button if not on the last page
            } else {
                pager.find('.next_link').hide(); // Hide next button if on the last page
            }

            pager.data("curr", page); // Store the current page index

            // Remove active class from all page items and set the clicked page as active
            pager.find('.page-item').removeClass('active');
            pager.find('.page-item').eq(page + 1).addClass("active");
        }
    };


    return {
        init:init
    }
})