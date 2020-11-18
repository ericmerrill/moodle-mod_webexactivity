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
 * A page to serve recording files.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  20202 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Code to view the passed webex.

require('../../config.php');
require_once($CFG->libdir.'/completionlib.php');

use mod_webexactivity\local\exception\webexactivity_exception;

$recording = false;
$download = false;
$sendfile = false;

// Get the id and arguments.
$args = get_file_argument();
if (!empty($args)) {
    $parts = explode('/', ltrim($args, '/\\'));
    $itemid = clean_param(array_shift($parts), PARAM_ALPHANUM);

    foreach ($parts as $item) {
        if ($item === 'download') {
            $download = true;
        }
        if ($item === 'file') {
            $sendfile = true;
        }
    }
    if (!empty($parts[1]) && $parts[1] === 'download') {
        $download = true;
    }
    if ($rec = $DB->get_record('webexactivity_recording', ['uniqueid' => $itemid])) {
        $recording = new \mod_webexactivity\recording($rec);
    }
}

if (empty($recording)) {
    // Recording not found.
    throw new webexactivity_exception('recordingnotfound');
}

$meeting = false;
$course = false;
$cm = false;
if (!empty($recording->webexid)) {
    if ($rec = $DB->get_record('webexactivity', ['id' => $recording->webexid])) {
        $meeting = \mod_webexactivity\meeting::load($rec);
        $course = $DB->get_record('course', ['id' => $meeting->id]);
        $cm = get_coursemodule_from_instance('webexactivity', $recording->webexid);
    }
}

$context = $recording->get_context();

if (empty($recording->publicview)) {
    // Non-public recordings require a login.
    if (empty($meeting) || empty($course) || empty($cm)) {
        throw new webexactivity_exception('recordingnotavailable');
    }

    require_login($course, false, $cm);
}

$canview = has_capability('mod/webexactivity:view', $context);
$canhost = has_capability('mod/webexactivity:hostmeeting', $context);

if ((!$recording->visible || $recording->deleted) && !$canhost) {
    throw new webexactivity_exception('recordingnotavailable');
}

$file = $recording->get_internal_file();

if (!$file) {
    throw new webexactivity_exception('recordingnotavailable');
}

if ($sendfile) {
    // Send the file.
    // TODO - log event.
    send_stored_file($file, 0, 0, $download);
    exit();
}

$returnurl = new moodle_url('/mod/webexactivity/rec.php'.$args);
$PAGE->set_url($returnurl);
if ($cm) {
    $PAGE->set_cm($cm, $course);
} else {
    $PAGE->set_context($context);
}


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string(get_string('downloadingfile', 'webexactivity', $recording->name)), 2);

// TODO - Don't show for mp4 files.
echo '<div class="downloadinfo">'.get_string('playerinfo', 'webexactivity').'</div>';

echo '<div class="t-btns">
    <button type="button" class="btn btn-default" id="mw-btn-recording-download" onclick="javascript:window.close()">Close Window</button>
</div>';

// echo var_export($context, true);


echo $OUTPUT->footer();


exit();
