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
 * Strings for component 'WebEx Activity', language 'en'
 *
 * @package    mod_webexactivity
 * @copyright  Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'WebEx Activity';
$string['pluginnamepural'] = 'WebEx Activities';
$string['modulename'] = 'WebEx Activity';
$string['modulename_help'] = 'The WebEx activity allows instructors to schedule meetings into the WebEx web conferencing system*.

When you add the WebEx meeting activity, you define the date and time of the meeting, as well a number of other optional parameters (such as expected duration, a description, etc). Participants (enrolled students) are then able to enter the WebEx meeting by clicking on a "join meeting" link under the activity in Moodle (teachers will see a link that says "host meeting"). If the meeting is recorded, students will be able to view the recording after the meeting is over.

* WebEx is a web conferencing system that allows students and teachers to synchronously collaborate. It transmits real-time audio and video, and includes tools such as whiteboard, chat, and desktop sharing.';
$string['modulenameplural'] = 'WebEx Activities';
$string['webexactivityname'] = 'Meeting name';
$string['pluginadministration'] = 'WebEx Activity administration';

$string['additionalsettings'] = 'Additional meeting settings';
$string['allchat'] = 'Participants can chat with other participants';
$string['apipassword'] = 'WebEx Admin password';
$string['apipassword_help'] = '';
$string['apisettings'] = 'API Settings';
$string['apiusername'] = 'WebEx Admin username';
$string['apiusername_help'] = '';
$string['availabilityendtime'] = 'Extended availability end time';
$string['confirmrecordingdelete'] = 'Are you sure you want to delete the recording <b>{$a->name}</b>, with a length of {$a->time}? This cannot be undone.';
$string['deletelink'] = '<a href="{$a->url}">Delete</a>';
$string['deletionin'] = '<div>{$a->time} until deletion.</div>';
$string['deletionsoon'] = '<div>Will be deleted soon.</div>';
$string['description'] = 'Description';
$string['duration'] = 'Expected duration';
$string['duration_help'] = 'The anticipated duration of the meeting. It is just for informational purposes, and does not effect how long the meeting can run for.';
$string['error_HM_AccessDenied'] = 'Access was denied to host this meeting.';
$string['error_JM_InvalidMeetingKey'] = 'There was an error meeting key error in WebEx and you cannot join this meeting.';
$string['error_JM_InvalidMeetingKeyOrPassword'] = 'There was an error meeting key error in WebEx and you cannot join this meeting.';
$string['error_JM_MeetingLocked'] = 'This meeting is locked and you cannot join it.';
$string['error_JM_MeetingNotInProgress'] = 'The meeting is not currently in progress. It may have not started yet, or already ended.';
$string['error_LI_AccessDenied'] = 'The user could not be logged into WebEx.';
$string['error_LI_AccountLocked'] = 'The WebEx user account is locked.';
$string['error_LI_AutoLoginDisabled'] = 'Auto logins are disable for this user';
$string['error_LI_InvalidSessionTicket'] = 'The session ticket is invalid. Please try again.';
$string['error_LI_InvalidTicket'] = 'The login ticket is invalid. Please try again.';
$string['error_unknown'] = 'An unknown error occurred.';
$string['error_'] = '';
$string['externallinktext'] = '<p>This link is for participants who do not have a Oakland University NetID account, or who are not participants in this course. Students in the course will not need to be emailed this link, as they can just click on the Join meeting link on the previous page. This link should be distributed carefully - anybody with this link will be able to access this meeting.  To invite others to the meeting, copy the URL below and send it to them.  If this is a public meeting, this link can also be placed on a web site.</p>';
$string['getexternallink'] = '<a href="{$a->url}">Get external participant link</a>';
$string['hostmeetinglink'] = '<a href="{$a->url}">Host Meeting</a>';
$string['joinmeetinglink'] = '<a href="{$a->url}">Join Meeting</a>';
$string['longavailability'] = 'Extended Availability';
$string['longavailability_help'] = 'Setting this option will leave the meeting available to host until the Extended availability end time. Allows reusable meetings for things like office hours.';
$string['manageallrecordings'] = 'Manage all WebEx recordings';
$string['manageallrecordings_help'] = 'Manage all recordings from the WebEx server, not just ones with a Moodle activity.';
$string['meetingclosegrace'] = 'Meeting grace period';
$string['meetingclosegrace_help'] = 'The number of minutes after the start time plus duration after which the meeting will be considered complete.';
$string['meetingpast'] = 'This meeting has past.';
$string['meetingsettings'] = 'Meeting settings';
$string['meetingupcoming'] = 'This meeting is not yet available to join.';
$string['page_managerecordings'] = 'Manage Recordings';
$string['page_manageusers'] = 'Manage Users';
$string['partnerid'] = 'Partner ID';
$string['partnerid_help'] = '';
$string['prefix'] = 'Username Prefix';
$string['prefix_help'] = '';
$string['recordingfileurl'] = 'Download';
$string['recordinglength'] = '({$a->time}, {$a->size})';
$string['recordingname'] = 'Recording name';
$string['recordings'] = 'Recordings';
$string['recordingsettings'] = 'Recordings Settings';
$string['recordingstreamurl'] = 'Play';
$string['recordingtrashtime'] = 'Recording trash time';
$string['recordingtrashtime_help'] = 'Number of hours a recording will be held before being deleted permanently.';
$string['settings'] = 'WebEx Activity settings';
$string['siteid'] = 'Site ID';
$string['siteid_help'] = '';
$string['starttime'] = 'Meeting start time';
$string['studentdownload'] = 'Allow students to download recordings';
$string['studentdownload_help'] = 'Allow students access to the download link for recordings.';
$string['studentvisible'] = 'Visible to students';
$string['undeletelink'] = '<a href="{$a->url}">Undelete</a>';
$string['url'] = 'Site URL';
$string['url_help'] = '';
