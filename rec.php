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
$stream = false;
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
        if ($item === 'stream') {
            $stream = true;
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

$canhost = has_capability('mod/webexactivity:hostmeeting', $context);

$candownload = true;
if ($meeting) {
    $candownload = $meeting->studentdownload;
    $candownload = $candownload || $canhost;


}

if ((!$recording->visible || $recording->deleted) && !$canhost) {
    throw new webexactivity_exception('recordingnotavailable');
}

// Determine whether to stream or download.
if ($stream) {
    if (!$recording->is_streamable()) {
        $stream = false;
    } else {
        $download = false;
    }
}
if ($download) {
    if (!$recording->is_downloadable()) {
        $download = false;
    } else {
        $stream = false;
    }
    if (!$candownload) {
        $download = false;
    }
}

// Pick a default if nothing selected.
if (!$download && !$stream) {
    // Pick the default behaviour.
    if ($recording->is_streamable()) {
        $stream = true;
    } else if ($recording->is_downloadable() && $candownload) {
        $download = true;
    }

}

$filename = $recording->name;
if ($download) {

    $file = $recording->get_internal_file();

    if (!$file) {
        if (!empty($recording->fileurl)) {
            // We don't have an internal file, and we do have a remote one, so send them away to download it.
            $params = array(
                'context' => $context,
                'objectid' => $recording->id
            );
            $event = \mod_webexactivity\event\recording_downloaded::create($params);
            $event->add_record_snapshot('webexactivity_recording', $recording->record);
            $event->trigger();
            redirect($recording->fileurl);
        }

        throw new webexactivity_exception('recordingnotavailable');
    }

    $filename = $file->get_filename();

    if ($sendfile) {
        // This indicates to send the actual file.
        // Record the file download to the log.
        $params = array(
            'context' => $context,
            'objectid' => $recording->id
        );
        $event = \mod_webexactivity\event\recording_downloaded::create($params);
        $event->add_record_snapshot('webexactivity_recording', $recording->record);
        $event->trigger();

        // Send the file.
        send_stored_file($file, 0, 0, $download);
        exit();
    }
} else if ($stream) {
    $params = array(
        'context' => $context,
        'objectid' => $recording->id
    );
    $event = \mod_webexactivity\event\recording_viewed::create($params);
    $event->add_record_snapshot('webexactivity_recording', $recording->record);
    $event->trigger();

    redirect($recording->get_stream_url());
} else {
    // Seems we can't stream or download this file.
    throw new webexactivity_exception('recordingnotavailable');
}



$returnurl = new moodle_url('/mod/webexactivity/rec.php'.$args);
$PAGE->set_url($returnurl);
if ($cm) {
    $PAGE->set_cm($cm, $course);
} else {
    $PAGE->set_context($context);
}
$heading = get_string('downloadingfile', 'webexactivity', $filename);
$title = $heading;
if ($course && $meeting) {
    $title = $course->shortname.': '.$meeting->name.': '.$title;
}
$PAGE->set_title($heading);


echo $OUTPUT->header();
echo $OUTPUT->heading($heading, 2);

$downloadurl = $recording->get_true_fileurl($download);

$PAGE->requires->js_call_amd('mod_webexactivity/downloader', 'init', [$downloadurl]);

// TODO - Don't show for mp4 files.
echo '<div class="downloadalt">'.get_string('downloadalt', 'webexactivity', $downloadurl).'</div>';

$extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
$extension = strtoupper($extension);
if ((strcasecmp($extension, 'arf') === 0) || (strcasecmp($extension, 'wrf') === 0)) {
    echo '<div class="downloadinfo">'.get_string('playerinfo', 'webexactivity', $extension).'</div>';
}


echo '<div class="t-btns">
    <button type="button" class="btn btn-default" onclick="javascript:window.close()">'.get_string('closewindow', 'webexactivity').
        '</button></div>';

// echo var_export($context, true);


echo $OUTPUT->footer();


exit();
