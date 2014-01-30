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

namespace mod_webexactivity;

class webex_meeting {
    private $meetingrecord = null;

    private $webex;

    public function __construct($meeting) {
        global $DB;

        $this->webex = new webex();

        if (is_numeric($meeting)) {
            $this->meetingrecord = $DB->get_record('webexactivity', array('id' => $meeting));
        } else if (is_object($meeting)) {
            $this->meetingrecord = $meeting;
        }

        if (!$this->meetingrecord) {
            // TODO Throw exception.
            return false;
        }
    }


    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public static function get_base_url() {
        $host = get_config('webexactivity', 'url');

        if ($host === false) {
            return false;
        }
        $url = 'https://'.$host.'.webex.com/'.$host;

        return $url;
    }

    public function get_meeting_webex_user() {
        global $DB, $USER;

        if (isset($this->meetingrecord->creatorwebexuser) && $this->meetingrecord->creatorwebexuser) {
            $webexuser = $DB->get_record('webexactivity_user', array('id' => $this->meetingrecord->creatorwebexuser));
        } else {
            $webexuser = $this->webex->get_webex_user($USER);
        }

        return $webexuser;
    }

    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------



    // Unused.
    // TODO ?
    /*public function add_hosts($users) {
        $webexuser = $this->get_meeting_webex_user();

        $meeting = clone $this->meetingrecord;
        $meeting->hostusers = $users;

        $xml = xml_generator::update_training_session($meeting);

        $this->meetingrecord->xml = $xml;
        $response = $this->webex->get_response($xml, $webexuser);

        if ($response === false) {
            return false;
        }
    }*/

    // ---------------------------------------------------
    // Recording Functions.
    // ---------------------------------------------------
    // TODO ?
    /*public function retrieve_recordings() {
        global $DB;

        $this->meetingrecord->laststatuscheck = time();
        $DB->update_record('webexactivity', $this->meetingrecord);

        if (!$this->meetingrecord->meetingkey) {
            return;
        }

        $params = new \stdClass();
        $params->meetingkey = $this->meetingrecord->meetingkey;

        $xml = xml_generator::list_recordings($params);

        if (!($response = $this->webex->get_response($xml))) {
            return false;
        }

        $recordings = $response['ep:recording'];

        foreach ($recordings as $recording) {
            $recording = $recording['#'];

            $rec = new \stdClass();
            $rec->webexid = $this->meetingrecord->id;
            $rec->meetingkey = $this->meetingrecord->meetingkey;
            $rec->recordingid = $recording['ep:recordingID'][0]['#'];
            $rec->hostid = $recording['ep:hostWebExID'][0]['#'];
            $rec->name = $recording['ep:name'][0]['#'];
            $rec->timecreated = strtotime($recording['ep:createTime'][0]['#']);
            $rec->streamurl = $recording['ep:streamURL'][0]['#'];
            $rec->fileurl = $recording['ep:fileURL'][0]['#'];
            $rec->duration = $recording['ep:duration'][0]['#'];

            if (!$DB->get_record('webexactivity_recording', array('recordingid' => $rec->recordingid))) {
                $DB->insert_record('webexactivity_recording', $rec);
            }
        }
    }*/

}