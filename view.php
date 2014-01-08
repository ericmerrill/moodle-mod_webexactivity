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
$action = optional_param('action', false, PARAM_ALPHA);
$view = optional_param('view', false, PARAM_ALPHA);


$cm = get_coursemodule_from_id('webexactivity', $id, 0, false, MUST_EXIST);
$webexrecord = $DB->get_record('webexactivity', array('id' => $cm->instance), '*', MUST_EXIST);
$webexmeeting = new \mod_webexactivity\webex_meeting($webexrecord);
$webex = new \mod_webexactivity\webex();
$meetingavail = $webexmeeting->meeting_is_available();

//$webexmeeting->retrieve_recordings();

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexactivity:view', $context);

$canhost = has_capability('mod/webexactivity:hostmeeting', $context);

$returnurl = new moodle_url('/mod/webexactivity/view.php', array('id' => $id));

// Do redirect actions here.
switch ($action) {
    case 'hostmeeting':
        if (!$meetingavail) {
            break;
        }
        if (!$canhost) {
            // TODO Error here.
            return;
        }

        $webexuser = $webex->get_webex_user($USER);
        $hosturl = $webexmeeting->get_meeting_host_url($returnurl);
        $authurl = $webex->get_login_url($webexuser, false, $hosturl);
        redirect($authurl);
        break;
    case 'joinmeeting':
        if (!$meetingavail) {
            break;
        }
        $joinurl = $webexmeeting->get_meeting_join_url($returnurl, $USER);
        redirect($joinurl);
        break;
}


add_to_log($course->id, 'webexactivity', 'view', 'view.php?id='.$cm->id, $webexrecord->id, $cm->id);

$PAGE->set_url('/mod/webexactivity/view.php', array('id' => $cm->id));

$PAGE->set_title($course->shortname.': '.$webexrecord->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($webexrecord);


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($webexrecord->name), 2);

echo $OUTPUT->box_start();

if (!$view) {
    echo '<table align="center" cellpadding="5">' . "\n";
    
    $formelements = array(
        get_string('description','webexactivity')  => $webexrecord->intro,
        get_string('starttime', 'webexactivity')   => userdate($webexrecord->starttime),
        get_string('duration', 'webexactivity')    => $webexrecord->duration
    );
    
    foreach ($formelements as $key => $val) {
       echo '<tr valign="top">' . "\n";
       echo '<td align="right"><b>' . $key . ':</b></td><td align="left">' . $val . '</td>' . "\n";
       echo '</tr>' . "\n";
    }

    if ($meetingavail) {
        // Output links.
        if ($canhost) {
            // Host link.
            echo '<tr><td colspan=2 align="center">';
            $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'action' => 'hostmeeting'));
            $params = array('url' => $urlobj->out());
            echo get_string('hostmeetinglink', 'webexactivity', $params);
            echo '</td></tr>';
        }
        // Join Link.
        echo '<tr><td colspan=2 align="center">';
        $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'action' => 'joinmeeting'));
        $params = array('url' => $urlobj->out());
        echo get_string('joinmeetinglink', 'webexactivity', $params);
        echo '</td></tr>';
        
        if ($canhost) {
            echo '<tr><td colspan=2 align="center">';
            $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'view' => 'guest'));
            $params = array('url' => $urlobj->out());
            echo get_string('getexternallink', 'webexactivity', $params);
            echo '</td></tr>';
        }
    }

    echo '</table>';

    $params = array('webexid' => $webexrecord->id);
    if (!$canhost) {
        $params['visible'] = 1;
    }

    if ($recordings = $DB->get_records('webexactivity_recording', $params)) {
        echo '<hr>';
        echo '<table align="center" cellpadding="5">';
        echo '<tr><td align="center">';
        echo get_string('recordings', 'webexactivity');
        echo '</td></tr>';
        //echo '</b></td></td>';

        foreach ($recordings as $recording) {
            /*echo '<tr><td>';
            echo userdate($recording->timecreated);
            echo '</td><td>';
            echo '<a target="_blank" href="'.$recording->fileurl.'">'.get_string('recordingfileurl', 'webexactivity').'</a> ';
            echo '<a target="_blank" href="'.$recording->streamurl.'">'.get_string('recordingstreamurl', 'webexactivity').'</a>';
            echo '</td></tr>';*/
            echo '<tr><td align="center">';
            echo '<a target="_blank" href="'.$recording->streamurl.'">'.get_string('recordingstreamurl', 'webexactivity').'</a>';
            echo ' - '.$recording->name;
            echo ' - '.userdate($recording->timecreated);
            echo ' '.get_string('recordinglength', 'webexactivity', round($recording->duration/60));
            echo '</td></tr>';
        }
        echo '</table>';
    }

} else if ($view === 'guest') {
    echo get_string('externallinktext', 'webexactivity');
    echo $webexmeeting->get_meeting_join_url();
    
}



echo $OUTPUT->box_end();

echo $OUTPUT->footer();
