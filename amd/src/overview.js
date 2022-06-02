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
 * Provides the mod_psgrading/overview module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/overview
 */
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log, Ajax) {    
    'use strict';

    /**
     * Initializes the overview component.
     */
    function init() {
        Log.debug('mod_psgrading/overview: initializing');

        var rootel = $('.psgrading-overview');

        if (!rootel.length) {
            Log.error('mod_psgrading/overview: .psgrading-overview not found!');
            return;
        }

        var overview = new Overview(rootel);
        overview.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Overview(rootel) {
        var self = this;
        self.rootel = rootel;
        self.matrix = rootel.find('.psgrading-matrix');
    }

    /**
     * Run the Audience Selector.
     *
     */
    Overview.prototype.main = function () {
        var self = this;

        // Change reporting period.
        self.rootel.on('change', '.reportingperiod-select', function(e) {
          var select = $(this);
          var url = select.find(':selected').data('viewurl');
          if (url) {
              window.location.replace(url);
          }
        });

        // Change group.
        self.rootel.on('change', '.group-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('overviewurl');
            if (url) {
                window.location.replace(url);
            }
        });

        // Change student.
        self.rootel.on('change', '.student-select', function(e) {
            var select = $(this);
            var url = select.find(':selected').data('overviewurl');
            if (url) {
                window.location.replace(url);
            }
        });

        // Matrix horizontal highlighting.
        //self.rootel.find('.psgrading-matrix .column.y-header .subjects .cell, .psgrading-matrix .column.y-header .cell-engagement').hover(
        self.rootel.find('.psgrading-matrix.has-reportgrades .column.y-header .subjects .cell').hover(
            function() {
                self.matrix.addClass( 'hover-' + $(this).data('subject') );
            }, function() {
                self.matrix.removeClass(function (index, className) {
                    return (className.match (/(^|\s)hover-\S+/g) || []).join(' ');
                });
            }
        );

    };


    return {
        init: init
    };
});