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

// Code to view the passed webex.

require('../../config.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.


$cm = get_coursemodule_from_id('webexactivity', $id, 0, false, MUST_EXIST);
$webex = $DB->get_record('webexactivity', array('id' => $cm->instance), '*', MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexactivity:view', $context);

add_to_log($course->id, 'webexactivity', 'view', 'view.php?id='.$cm->id, $webex->id, $cm->id);

$PAGE->set_url('/mod/webexactivity/view.php', array('id' => $cm->id));

$PAGE->set_title($course->shortname.': '.$webex->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($webex);


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($webex->name), 2);

echo $OUTPUT->box_start();

$connector = new \mod_webexactivity\service_connector();
$stat = $connector->retrieve(\mod_webexactivity\xml_generator::get_user_info('adm_merrill'));
if ($stat) {
    print "<pre>";
    print_r($connector->get_response_array());
    print "</pre>";
} else {
    print "<pre>";
    print_r($connector->get_errors());
    print "</pre>";
}

$stat = $connector->retrieve(\mod_webexactivity\xml_generator::get_user_info('adm_merrill2'));
if ($stat) {
    print "<pre>";
    print_r($connector->get_response_array());
    print "</pre>";
} else {
    print "<pre>";
    print_r($connector->get_errors());
    print "</pre>";
}

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
