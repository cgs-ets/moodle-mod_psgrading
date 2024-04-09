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
 * @module mod_psgrading/quickmark
 */
define(['jquery', 'core/log', 'core/ajax'], 
    function($, Log) {    
    'use strict';

    /**
     * Initializes the mark component.
     */
    function init(markurls, start) {
        Log.debug('mod_psgrading/quickmark: initializing');

        var rootel = $('#page-mod-psgrading-quickmark');

        if (!rootel.length) {
            Log.error('mod_psgrading/mark: #page-mod-psgrading-quickmark not found!');
            return;
        }

        var mark = new QuickMark();
        mark.main(markurls, start);
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function QuickMark() {
      
    }

    /**
     * Run the Audience Selector.
     *
     */
    QuickMark.prototype.main = function (markurls, start) {
      var self = this;

      console.log(markurls)
      
      var curr = start+1
      // When the first is complete, remove it, popup the second, and load the third, and so on.
      window.onmessage = function(e) {
        if (e.data == 'saveshownext') {
          // Get the 2 wrapping regions.
          var a = document.getElementById("quickmark-a");
          var b = document.getElementById("quickmark-b");

          // If we've reached the last student in the list.
          if (a.classList.contains('last') || b.classList.contains('last')) {
            self.setup(markurls, 0);
            curr = 1;
            return;
          }

          // Swap A and B.
          var next = null;
          if (b.classList.contains('hidden')) {
            a.classList.add("hidden");
            b.classList.remove("hidden");
            next = a;
          } else {
            b.classList.add("hidden");
            a.classList.remove("hidden");
            next = b
          }

          // Load the next markurl if there is one.
          curr++;
          if (curr >= markurls.length) {
            console.log('Last user has been loaded: ' + markurls[curr-1])
            next.classList.add("last");
          } else {
            console.log('Loading next hidden url: ' + markurls[curr])
            next.firstElementChild.setAttribute("src", markurls[curr]);
          }
        }
      };

      self.setup(markurls, start);
    };

    /**
     * Select a criterion level
     *
     * @method
     */
    QuickMark.prototype.setup = function (markurls, start) {
      // Load the first and second.
      if (markurls.length > start) {
        var iframe1 = document.createElement("iframe");
        iframe1.setAttribute("src", markurls[start]);
        var wrap = document.getElementById("quickmark-a");
        wrap.innerHTML = '';
        wrap.appendChild(iframe1);
        wrap.classList.remove("hidden");
        wrap.classList.remove("last");
      }

      if (markurls.length > start+1) {
        var iframe2 = document.createElement("iframe");
        iframe2.setAttribute("src", markurls[start+1]);
        var wrap = document.getElementById("quickmark-b");
        wrap.innerHTML = '';
        wrap.appendChild(iframe2);
        wrap.classList.add("hidden");
        wrap.classList.remove("last");
      }
    };


    return {
        init: init
    };
});