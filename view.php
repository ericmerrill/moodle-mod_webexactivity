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
$webex = $DB->get_record('webexactivity', array('id' => $cm->instance), '*', MUST_EXIST);
$webexobj = new \mod_webexactivity\webex($webex);
$meetingavail = $webexobj->meeting_is_available();

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

        $webexuser = $webexobj->get_webex_user($USER);
        $hosturl = \mod_webexactivity\webex::get_meeting_host_url($webex, $returnurl);
        $authurl = $webexobj->get_login_url($webex, $webexuser, false, $hosturl);
        redirect($authurl);
        break;
    case 'joinmeeting':
        if (!$meetingavail) {
            break;
        }
        $joinurl = \mod_webexactivity\webex::get_meeting_join_url($webex, $returnurl, $USER);
        redirect($joinurl);
        break;
}


add_to_log($course->id, 'webexactivity', 'view', 'view.php?id='.$cm->id, $webex->id, $cm->id);

$PAGE->set_url('/mod/webexactivity/view.php', array('id' => $cm->id));

$PAGE->set_title($course->shortname.': '.$webex->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($webex);


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($webex->name), 2);

echo $OUTPUT->box_start();



if (!$view) {
    echo '<table align="center" cellpadding="5">' . "\n";
    
    $formelements = array(
        get_string('description','webexactivity')  => $webex->intro,
        get_string('starttime', 'webexactivity')   => userdate($webex->starttime),
        get_string('duration', 'webexactivity')    => $webex->length
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

} else if ($view === 'guest') {
    echo get_string('externallinktext', 'webexactivity');
    echo \mod_webexactivity\webex::get_meeting_join_url($webex);
    
}



echo $OUTPUT->box_end();

echo $OUTPUT->footer();
