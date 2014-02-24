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
require_once($CFG->libdir.'/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$action = optional_param('action', false, PARAM_ALPHA);
$view = optional_param('view', false, PARAM_ALPHA);

$error = false;

// Webex codes.
$webexres = array();
$webexres['AT'] = optional_param('AT', false, PARAM_ALPHA);
$webexres['ST'] = optional_param('ST', false, PARAM_ALPHA);
$webexres['RS'] = optional_param('RS', false, PARAM_ALPHA);


$cm = get_coursemodule_from_id('webexactivity', $id, 0, false, MUST_EXIST);
$webexrecord = $DB->get_record('webexactivity', array('id' => $cm->instance), '*', MUST_EXIST);
$webexmeeting = \mod_webexactivity\meeting::load($webexrecord);
$webex = new \mod_webexactivity\webex();

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Basic completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexactivity:view', $context);

$canhost = has_capability('mod/webexactivity:hostmeeting', $context);

$returnurl = new moodle_url('/mod/webexactivity/view.php', array('id' => $id));
$PAGE->set_url($returnurl);

// Errors from the WebEx URL API docs.
if ($webexres['ST'] === 'FAIL') {
    $error = true;

    if ($webexres['AT'] === 'JM') {
        switch ($webexres['RS']) {
            case 'MeetingNotInProgress':
                // If running, mark meeting as stopped, WebEx wouldn't let us join.
                if ($webexmeeting->status === \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
                    $webexmeeting->status = \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_STOPPED;
                    $webexmeeting->save();
                }
            case 'InvalidMeetingKeyOrPassword':
            case 'MeetingLocked':
            case 'InvalidMeetingKey':
                $error = get_string('error_'.$webexres['AT'].'_'.$webexres['RS'], 'webexactivity');
                break;
            default:
                debugging('An unknown webex occurred: '.$webexres['RS'], DEBUG_DEVELOPER);
                $error = get_string('error_unknown', 'webexactivity');
                break;

        }
    } else if ($webexres['AT'] === 'HM') {
        switch ($webexres['RS']) {
            case 'AccessDenied':
                $error = get_string('error_'.$webexres['AT'].'_'.$webexres['RS'], 'webexactivity');
                break;
            default:
                debugging('An unknown webex occurred: '.$webexres['RS'], DEBUG_DEVELOPER);
                $error = get_string('error_unknown', 'webexactivity');
                break;
        }
    } else if ($webexres['AT'] === 'LI') {
        switch ($webexres['RS']) {
            case 'AlreadyLogon':
                $params = array('id' => $id);
                if ($action === 'hostmeetingerror') {
                    $params['action'] = 'hostmeeting';
                }
                $hosturl = new moodle_url($returnurl, $params);

                $logouturl = \mod_webexactivity\webex_user::get_logout_url($hosturl->out(false));

                redirect($logouturl);
                break;
            case 'AccessDenied':
            case 'AccountLocked':
            case 'AutoLoginDisabled':
            case 'InvalidSessionTicket':
            case 'InvalidTicket':
                $error = get_string('error_'.$webexres['AT'].'_'.$webexres['RS'], 'webexactivity');
            default:
                debugging('An unknown webex occurred: '.$webexres['RS'], DEBUG_DEVELOPER);
                $error = get_string('error_unknown', 'webexactivity');
                break;
        }
    } else if ($webexres['AT'] === 'LO') {
        // We don't car about Logout errors.
        $error = false;
    } else {
        debugging('Unknown webex AT command error: '.$webexres['AT'], DEBUG_DEVELOPER);
        $error = get_string('error_unknown', 'webexactivity');
    }

} else if ($webexres['ST'] === 'SUCCESS') {
    if ($webexres['AT'] === 'JM') {
        // Mark the meeting as running, we were able to join it.
        $webexmeeting->status = \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS;
        $webexmeeting->laststatuscheck = time();
        $webexmeeting->save();

        $params = array(
            'context' => $context,
            'objectid' => $webexmeeting->id
        );
        $event = \mod_webexactivity\event\meeting_joined::create($params);
        $event->add_record_snapshot('webexactivity', $webexrecord);
        $event->trigger();

        redirect($returnurl->out(false));
    } else if ($webexres['AT'] === 'HM') {
        // Mark the meeting as running, we started it.
        $webexmeeting->status = \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS;
        $webexmeeting->laststatuscheck = time();
        $webexmeeting->save();

        $params = array(
            'context' => $context,
            'objectid' => $webexmeeting->id
        );
        $event = \mod_webexactivity\event\meeting_hosted::create($params);
        $event->add_record_snapshot('webexactivity', $webexrecord);
        $event->trigger();

        redirect($returnurl->out(false));
    }
}



// Do redirect actions here.
switch ($action) {
    case 'hostmeeting':
        if (!$webexmeeting->is_available(true)) {
            break;
        }

        require_capability('mod/webexactivity:hostmeeting', $context);

        $webexuser = $webex->get_webex_user($USER);
        $webexmeeting->add_webexuser_host($webexuser);
        $hosturl = $webexmeeting->get_host_url($returnurl);

        $params = array('id' => $id, 'action' => 'hostmeetingerror');
        $failurl = new moodle_url($returnurl, $params);
        $authurl = $webexuser->get_login_url($failurl->out(false), $hosturl);

        redirect($authurl);
        break;

    case 'joinmeeting':
        if (!$webexmeeting->is_available()) {
            break;
        }
        $joinurl = $webexmeeting->get_moodle_join_url($USER, $returnurl);

        redirect($joinurl);
        break;

    case 'viewrecording':
        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if (!$recording->visible) {
            require_capability('mod/webexactivity:hostmeeting', $context);
        }
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        $params = array(
            'context' => $context,
            'objectid' => $recordingid
        );
        $event = \mod_webexactivity\event\recording_viewed::create($params);
        $event->add_record_snapshot('webexactivity_recording', $recording->record);
        $event->trigger();

        redirect($recording->streamurl);
        break;

    case 'downloadrecording';
        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if (!$recording->visible) {
            require_capability('mod/webexactivity:hostmeeting', $context);
        }
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        $params = array(
            'context' => $context,
            'objectid' => $recordingid
        );
        $event = \mod_webexactivity\event\recording_downloaded::create($params);
        $event->add_record_snapshot('webexactivity_recording', $recording->record);
        $event->trigger();

        redirect($recording->streamurl);
        break;

    case 'hiderecording':
        require_capability('mod/webexactivity:hostmeeting', $context);

        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        $recording->visible = 0;
        $recording->save();

        redirect($returnurl->out(false));
        break;

    case 'showrecording':
        require_capability('mod/webexactivity:hostmeeting', $context);

        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        $recording->visible = 1;
        $recording->save();

        redirect($returnurl->out(false));
        break;

    case 'editrecording':
        require_capability('mod/webexactivity:hostmeeting', $context);

        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        // Load the form for recording editing.
        $mform = new \mod_webexactivity\editrecording_form();

        if ($mform->is_cancelled()) {
            $action = false;
            $view = false;
        } else if ($fromform = $mform->get_data()) {
            $recording->name = $fromform->name;
            if (isset($fromform->visible)) {
                $recording->visible = 1;
            } else {
                $recording->visible = 0;
            }
            $recording->save();
        } else {
            $view = 'editrecording';

            $data = new stdClass();
            $data->name = $recording->name;
            $data->id = $id;
            $data->recordingid = $recording->id;
            $data->action = 'editrecording';
            $data->visible = $recording->visible;
            $mform->set_data($data);
            break;
        }

        break;
    case 'deleterecording':
        require_capability('mod/webexactivity:hostmeeting', $context);

        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);
        $recwebexid = $recording->webexid;
        if ($recwebexid !== $cm->instance) {
            throw new invalid_parameter_exception('Recording ID does not match instance.');
            break;
        }

        $confirm = optional_param('confirm', 0, PARAM_INT);

        // If not confirmed, display form below.
        if (!$confirm) {
            $view = 'deleterecording';
            break;
        } else {
            $params = array(
                'context' => $context,
                'objectid' => $recordingid
            );
            $event = \mod_webexactivity\event\recording_deleted::create($params);
            $event->add_record_snapshot('webexactivity_recording', $recording->record);
            $event->trigger();

            $recording->delete();
            redirect($returnurl->out(false));
        }
        break;

}

// Record that the page was viewed.
add_to_log($course->id, 'webexactivity', 'view', 'view.php?id='.$cm->id, $webexmeeting->id, $cm->id);

// Basic page setup.
$PAGE->set_title($course->shortname.': '.$webexmeeting->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($webexrecord);

// Start output.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($webexmeeting->name), 2);

// Display any errors.
if ($error !== false) {
    echo $OUTPUT->box_start('webexerror');

    if (is_string($error)) {
        echo $OUTPUT->error_text($error);
    }

    echo $OUTPUT->box_end();
}

echo $OUTPUT->box_start();

if (!$view) {
    // The standard view.
    echo '<table align="center" cellpadding="5">' . "\n";

    $formelements = array(
        get_string('description', 'webexactivity')  => $webexmeeting->intro,
        get_string('starttime', 'webexactivity')   => userdate($webexmeeting->starttime),
        get_string('duration', 'webexactivity')    => $webexmeeting->duration
    );

    foreach ($formelements as $key => $val) {
        echo '<tr valign="top">' . "\n";
        echo '<td align="right"><b>' . $key . ':</b></td><td align="left">' . $val . '</td>' . "\n";
        echo '</tr>' . "\n";
    }

    // Output links.
    $timestatus = $webexmeeting->get_time_status();
    if ($canhost && $webexmeeting->is_available(true)) {
        // Host link.
        echo '<tr><td colspan=2 align="center">';
        $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'action' => 'hostmeeting'));
        $params = array('url' => $urlobj->out(false));
        echo get_string('hostmeetinglink', 'webexactivity', $params);
        echo '</td></tr>';
    }
    // Join Link.
    if ($webexmeeting->is_available(false)) {
        echo '<tr><td colspan=2 align="center">';
        $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'action' => 'joinmeeting'));
        $params = array('url' => $urlobj->out(false));
        echo get_string('joinmeetinglink', 'webexactivity', $params);
        echo '</td></tr>';
    } else if ($timestatus === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_UPCOMING) {
        echo '<tr><td colspan=2 align="center">';
        echo get_string('meetingupcoming', 'webexactivity');
        echo '</td></tr>';
    } else if ($timestatus === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_PAST) {
        echo '<tr><td colspan=2 align="center">';
        echo get_string('meetingpast', 'webexactivity');
        echo '</td></tr>';
    }

    // View "external guest link" link.
    if ($canhost && $webexmeeting->is_available(true)) {
        echo '<tr><td colspan=2 align="center">';
        $urlobj = new moodle_url('/mod/webexactivity/view.php', array('id' => $id, 'view' => 'guest'));
        $params = array('url' => $urlobj->out(false));
        echo get_string('getexternallink', 'webexactivity', $params);
        echo '</td></tr>';
    }

    echo '</table>';

    // Get and display recordings.
    if ($recordings = $webexmeeting->get_recordings()) {
        $candownload = $webexmeeting->studentdownload;
        $candownload = $candownload || $canhost;

        echo '<hr>';

        echo '<div id="recordings">';

        echo '<div id="recordingsheader">';
        echo get_string('recordings', 'webexactivity');
        echo '</div>';

        foreach ($recordings as $recording) {
            if ($recording->visible) {
                echo '<div class="recording">';
            } else {
                // If hidden and we don't have management, then skip.
                if (!$canhost) {
                    continue;
                }
                echo '<div class="recording hiddenrecording">';
            }

            // Playback buttons.
            echo '<div class="recordingblock buttons">';
            // Play button.
            echo '<div class="play">';
            $params = array('id' => $id, 'recordingid' => $recording->id, 'action' => 'viewrecording');
            $urlobj = new moodle_url('/mod/webexactivity/view.php', $params);
            echo $OUTPUT->action_icon($urlobj->out(false), new \pix_icon('play', 'Play', 'mod_webexactivity'),
                    null, array('target' => '_blank'));
            echo '</div>';

            // Download Button.
            if ($candownload) {
                echo '<div class="download">';
                echo $OUTPUT->action_icon($recording->fileurl, new \pix_icon('download', 'Download', 'mod_webexactivity'),
                        null, array('target' => '_blank'));
                echo '</div>';
            }

            echo '</div>';

            // Recording information.
            echo '<div class="recordingblock details">';
            echo '<div class="name">'.$recording->name.'</div>';
            echo '<div class="date">'.userdate($recording->timecreated).'</div>';
            $params = new \stdClass();
            if ($recording->filesize !== null) {
                $params->size = display_size($recording->filesize);
            } else {
                $params->size = 'Unknown Size';
            }
            $params->time = format_time($recording->duration);
            echo '<div class="length">'.get_string('recordinglength', 'webexactivity', $params).'</div>';
            echo '</div>';

            if ($canhost) {
                // Editing buttons.
                echo '<div class="recordingblock buttons">';

                // Delete, rename, hide.
                $params = array('id' => $id, 'recordingid' => $recording->id, 'action' => 'editrecording');
                $urlobj = new moodle_url('/mod/webexactivity/view.php', $params);
                echo $OUTPUT->action_icon($urlobj->out(false), new \pix_icon('t/editstring', 'Edit recording'));

                if ($recording->visible) {
                    $params['action'] = 'hiderecording';
                    $urlobj = new moodle_url('/mod/webexactivity/view.php', $params);
                    echo $OUTPUT->action_icon($urlobj->out(false), new \pix_icon('t/hide', 'Hide recording'));
                } else {
                    $params['action'] = 'showrecording';
                    $urlobj = new moodle_url('/mod/webexactivity/view.php', $params);
                    echo $OUTPUT->action_icon($urlobj->out(false), new \pix_icon('t/show', 'Show recording'));
                }

                $params['action'] = 'deleterecording';
                $urlobj = new moodle_url('/mod/webexactivity/view.php', $params);
                echo $OUTPUT->action_icon($urlobj->out(false), new \pix_icon('t/delete', 'Delete recording'));

                echo '</div>';
            }

            // Close the recording row.
            echo '</div>';
        }
        echo '</div>';
    }

} else if ($view === 'guest') {
    // Show the external participant link.

    echo get_string('externallinktext', 'webexactivity');
    echo $webexmeeting->get_external_join_url();
} else if ($view === 'editrecording') {
    // Show the editing recording link.
    $recordingid = required_param('recordingid', PARAM_INT);

    $mform->display();
} else if ($view === 'deleterecording') {
    // Show the delete recording confirmation page.
    $recordingid = required_param('recordingid', PARAM_INT);
    $recording = new \mod_webexactivity\webex_recording($recordingid);

    $params = array('id' => $id, 'action' => 'deleterecording', 'confirm' => 1, 'recordingid' => $recordingid);
    $confirmurl = new moodle_url($returnurl, $params);

    $params = new stdClass();
    $params->name = $recording->name;
    $params->time = format_time($recording->duration);
    $message = get_string('confirmrecordingdelete', 'webexactivity', $params);
    echo $OUTPUT->confirm($message, $confirmurl, $returnurl);
}

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
