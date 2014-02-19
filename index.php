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
 * An activity to interface with WebEx.
 *
 * @package   mod_webexactvity
 * @copyright Eric Merrill (merrill@oakland.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Code to display all instances in the passed course.

require('../../config.php');

$id = optional_param('id', 0, PARAM_INT); // Course ID.
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// Security.
require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/webexactivity:view', $context);

// Page setup.
$returnurl = new moodle_url('/mod/webexactivity/index.php', array('id' => $id));
$PAGE->set_url($returnurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context($context);
$PAGE->set_title($course->shortname.': WebEx Activies'); // TODO Change string.




echo $OUTPUT->header();
echo $OUTPUT->heading(format_string(get_string("modulenameplural", "webexactivity")));




echo $OUTPUT->footer();
