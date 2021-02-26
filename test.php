<?php
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
 * A student portfolio tool for CGS.
 *
 * @package   mod_psgrading
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */


require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use \mod_psgrading\forms\form_test;


// Set context.
$context = context_system::instance();

// Set up page parameters.
$PAGE->set_context($context);

$url = new moodle_url('/mod/psgrading/test.php',[]);
$PAGE->set_url($url);
$PAGE->set_title('test');
$PAGE->set_heading('test');



$testform = new form_test($url->out(false), 'post', '', []);

// REASON WHY ERRORS ARE NOT SHOWING HAS TO DO WITH THIS CODE NOT RUNNNG. UNCOMMENT AND RUN...

$testdata = $testform->get_data();


// Check whether loading page or submitting page.
if (empty($testdata)) {
   
} else {
    
}


echo $OUTPUT->header();

$testform->display();

echo $OUTPUT->footer();

exit;



