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
    private $latesterrors = null;
    private $meetingrecord = null;

    private $webex;

    public function __construct($meeting) {
        $this->webex = new webex();

        if (is_numeric($meeting)) {
            $this->meetingrecord = $DB->get_record('webexactivity', array('id' => $meeting));
        }
        if (is_object($meeting)) {
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

    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
/*    public function create_or_update_meeting($user) {
        global $DB;

        if (isset($this->meetingrecord->meetingkey) && $this->meetingrecord->meetingkey) {
            // Update.
            return true;
        }

        $webexuser = $this->webex->get_webex_user($user);

        $xml = xml_generator::create_meeting($this->meetingrecord);

        $response = $this->get_response($xml, $webexuser);

        if ($response) {
            if (isset($response['meet:meetingkey']['0']['#'])) {
                $this->meetingrecord->meetingkey = $response['meet:meetingkey']['0']['#'];
                $DB->update_record('webexactivity', $this->meetingrecord);
                return true;
            }
        } else {
            return false;
        }
    }*/

    public function create_or_update($user) {
        global $DB;

        if ($this->meetingrecord->starttime < time()) {
            $this->meetingrecord->starttime = time() + 60;
        }

        if (isset($this->meetingrecord->course)) {
            $context = \context_course::instance($this->meetingrecord->course);
            $users = get_enrolled_users($context, 'mod/webexactivity:hostmeeting');
            unset($users[$user->id]);
            if ($users && (count($users) > 0)) {
//                $this->meetingrecord->hostusers = $users;
            }
            $users[$user->id] = $user;
//            $this->meetingrecord->hosts = serialize($users);
        }

        $webexuser = $this->webex->get_webex_user($user);

        if (isset($this->meetingrecord->meetingkey) && $this->meetingrecord->meetingkey) {
            $xml = xml_generator::update_training_session($this->meetingrecord);
            $this->meetingrecord->xml = $xml;
            $response = $this->webex->get_response($xml, $webexuser);

            if ($response === false) {
                return false;
            }

            $DB->update_record('webexactivity', $this->meetingrecord);
            return true;
        }

        $xml = xml_generator::create_training_session($this->meetingrecord);
        $this->meetingrecord->xml = $xml;

        $response = $this->webex->get_response($xml, $webexuser);

        if ($response) {
            if (isset($response['train:sessionkey']['0']['#'])) {

                $this->meetingrecord->meetingkey = $response['train:sessionkey']['0']['#'];
                $this->meetingrecord->guesttoken = $response['train:additionalInfo']['0']['#']['train:guestToken']['0']['#'];
                $DB->update_record('webexactivity', $this->meetingrecord);
                return true;
            }
        }

        return false;
    }

    public function delete_training($user) {
        $webexuser = $this->webex->get_webex_user($user);

        if (isset($this->meetingrecord->meetingkey) && $this->meetingrecord->meetingkey) {
            $xml = xml_generator::delete_training_session($this->meetingrecord);
            $response = $this->webex->get_response($xml, $webexuser);

            if ($response === false) {
                return false;
            }
            return true;
        }

        return false;
    }

    public function get_meeting_info() {
        $xml = xml_generator::get_meeting_info($this->meetingrecord->meetingkey);

        if (!($response = $this->webex->get_response($xml))) {
            return false;
        }

        $response = $this->webex->get_response($xml);

        return $response;
    }

    public function get_training_info($user) {
        $webexuser = $this->webex->get_webex_user($user);
        $xml = xml_generator::get_training_info($this->meetingrecord->meetingkey);

        if (!($response = $this->webex->get_response($xml, $webexuser))) {
            return false;
        }

        return $response;
    }

    public function get_meeting_host_url($returnurl = false) {
        $baseurl = self::get_base_url();
        $url = $baseurl.'/m.php?AT=HM&MK='.$this->meetingrecord->meetingkey;
        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function get_meeting_join_url($returnurl = false, $user = false) {
        $baseurl = self::get_base_url();
        $url = $baseurl.'/m.php?AT=JM&MK='.$this->meetingrecord->meetingkey;

        if ($user) {
            $url .= '&AE='.$user->email.'&AN='.$user->firstname.'%20'.$user->lastname;
        }

        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function meeting_is_available() {
        $grace = get_config('webexactivity', 'meetingclosegrace');

        $endtime = $this->meetingrecord->starttime + ($this->meetingrecord->duration * 60) + ($grace * 60);

        if (time() > $endtime) {
            return false;
        }

        return true;
    }

    // ---------------------------------------------------
    // Recording Functions.
    // ---------------------------------------------------
    public function retrieve_recordings() {
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
    }

}