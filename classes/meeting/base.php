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
 * @copyright 2014 Eric Merrill (merrill@oakland.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity\meeting;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that represents and controls a meeting instance.
 *
 * This should be extended by classes that represent specific meeting types.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {
    protected $meetingrecord = null;

    protected $values = array(
            'id' => null,
            'course' => 0,
            'name' => '',
            'intro' => null,
            'introformat' => 0,
            'creatorwebexuser' => null,
            'type' => null,
            'meetingkey' => null,
            'guestkey' => null, // Unused?
            'eventid' => null,
            'guestuserid' => null, // Unused.
            'hostkey' => null, // Unused?
            'starttime' => null,
            'duration' => null,
            'hosts' => null, // Unused?
            'allchat' => 1, // Unused?
            'studentdownload' => 1,
            'xml' => null, // Temp.
            'laststatuscheck' => 0,
            'status' => \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_NEVER_STARTED,
            'timemodified' => 0);

    protected $webex;

    protected $gen = '\mod_webexactivity\xml_gen\base';

    public function __construct($meeting = false) {
        global $DB;

        $this->webex = new \mod_webexactivity\webex();

        if ($meeting === false) {
            return;
        }

        if (is_numeric($meeting)) {
            $this->meetingrecord = $DB->get_record('webexactivity', array('id' => $meeting));
        } else if (is_object($meeting)) {
            $this->meetingrecord = $meeting;
        } else {
            debugging('meeting\base constructor passed unknown type.', DEBUG_DEVELOPER);
        }

        $this->load_webex_record($meeting);
    }

    // ---------------------------------------------------
    // Accessor Functions.
    // ---------------------------------------------------
    public function set_value($name, $val) {
        if ($name === 'starttime') {
            $curr = $this->get_value('starttime');
            // If the current time is not set, new meeting.
            if ($curr === null) {
                // If the time is past, or near past, set it to the near future.
                if ($val < (time() + 60)) {
                    $val = time() + 60;
                }
            } else if ($val < (time() + 60)) {
                // If the current time is already set, and the time is past or near past.
                if ($curr > time()) {
                    // If the current time is in the future, assume they want to start it now.
                    $val = time() + 60;
                } else {
                    // If they are both in the past, leave it as the old setting. Can't change it.
                    $val = $curr;
                }
            }
        }

        $this->values[$name] = $val;
        if (!array_key_exists($name, $this->values)) {
            debugging('Unknown meeting value set '.$name, DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    public function get_value($name) {
        if (!array_key_exists($name, $this->values)) {
            debugging('Unknown meeting value requested '.$name, DEBUG_DEVELOPER);
            return false;
        }

        return $this->values[$name];
    }

    // ---------------------------------------------------
    // Magic Methods.
    // ---------------------------------------------------
    public function __set($name, $val) {
        $this->set_value($name, $val);
        // TODO.
    }

    public function __get($name) {        
        return $this->get_value($name);
        // TODO.
    }

    public function __isset($name) {
        return isset($this->values[$name]);
    }



    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public function save_to_webex() {
        $data = self::array_to_object($this->values);
        $gen = $this->gen;
        $webexuser = $this->get_meeting_webex_user();

        if (isset($this->values['meetingkey'])) {
            // Updating meeting.
            $xml = $gen::update_meeting($data);
        } else {
            // Creating meeting.
            $xml = $gen::create_meeting($data);
            $this->values['creatorwebexuser'] = $webexuser->id;
        }

        $this->values['xml'] = $xml;
        $response = $this->webex->get_response($xml, $webexuser);

        return $this->process_response($response);
    }

    public function delete_from_webex() {
        $gen = $this->gen;
        $webexuser = $this->get_meeting_webex_user();

        $xml = $gen::delete_meeting($this->values['meetingkey']);

        $response = $this->webex->get_response($xml, $webexuser);

        if (empty($response)) {
            return true;
        }

        return false;
    }

    public function get_info($save = false) {
        if (!isset($this->values['meetingkey'])) {
            return false;
        }

        $gen = $this->gen;
        $webexuser = $this->get_meeting_webex_user();

        $xml = $gen::get_meeting_info($this->values['meetingkey']);

        if (!$response = $this->webex->get_response($xml, $webexuser)) {
            return false;
        }

        if (!$this->process_response($response)) {
            return false;
        }

        if ($save) {
            $this->save_to_db();
        }

        return $response;
    }

    public function get_time_status() {
        $time = time();
        $grace = get_config('webexactivity', 'meetingclosegrace');
        $endtime = $this->meetingrecord->starttime + ($this->meetingrecord->duration * 60) + ($grace * 60);
        $starttime = $this->meetingrecord->starttime - (20 * 60);

        if ($this->meetingrecord->status == \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
            return \mod_webexactivity\webex::WEBEXACTIVITY_TIME_IN_PROGRESS;
        }

        if ($time < $starttime) {
            return \mod_webexactivity\webex::WEBEXACTIVITY_TIME_UPCOMING;
        }

        if ($time > $endtime) {
            if ($time > ($endtime + (24 * 3600))) {
                return \mod_webexactivity\webex::WEBEXACTIVITY_TIME_LONG_PAST;
            } else {
                return \mod_webexactivity\webex::WEBEXACTIVITY_TIME_PAST;
            }
        }

        return \mod_webexactivity\webex::WEBEXACTIVITY_TIME_AVAILABLE;

    }

    public function is_available($host = false) {
        $status = $this->get_time_status();

        if ($host) {
            if (($status === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_AVAILABLE) ||
                    ($status === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_IN_PROGRESS) ||
                    ($status === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_UPCOMING)) {
                return true;
            }
        } else {
            if (($status === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_AVAILABLE) ||
                    ($status === \mod_webexactivity\webex::WEBEXACTIVITY_TIME_IN_PROGRESS)) {
                return true;
            }
        }

        return false;
    }

    public function is_past() {
        if ($this->meetingrecord->status == \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
            return false;
        }

        $endtime = $this->meetingrecord->starttime + ($this->meetingrecord->duration * 60) + ($grace * 60);
        if (time() > $endtime) {
            return true;
        }

        return false;
    }

    protected function process_response($response) {
        debugging('Function process_response must be implemented by child class.', DEBUG_DEVELOPER);
    }

    public function add_webexuser_host($webexuser) {
        global $DB;

        $creator = $this->get_meeting_webex_user();
        if ($webexuser->webexid === $creator->webexid) {
            return true;
        }

        $moodleuser = $DB->get_record('user', array('id' => $webexuser->moodleuserid));
        $user = new \stdClass();
        $user->webexid = $webexuser->webexid;
        $user->email = $moodleuser->email;
        $user->firstname = $moodleuser->firstname;
        $user->lastname = $moodleuser->lastname;

        $data = new \stdClass();
        $data->meetingkey = $this->values['meetingkey'];
        $data->hostusers = array($user);

        $gen = $this->gen;
        $xml = $gen::update_meeting($data);

        if (!($response = $this->webex->get_response($xml, $creator))) {
            return false;
        }

        return true;
    }


    // ---------------------------------------------------
    // Recording Functions.
    // ---------------------------------------------------
    public function delete_recordings() {
        $recordings = $this->get_recordings();

        if (!is_array($recordings)) {
            return true;
        }

        foreach ($recordings as $recording) {
            $recording->delete();
        }

        return true;
    }

    public function get_recordings() {
        global $DB;

        $recordingrecords = $DB->get_records('webexactivity_recording', array('webexid' => $this->get_value('id'), 'deleted' => 0));

        if (!$recordingrecords) {
            return false;
        }

        $out = array();

        foreach ($recordingrecords as $record) {
            $out[] = new \mod_webexactivity\webex_recording($record);
        }

        return $out;
    }

    // ---------------------------------------------------
    // URL Functions.
    // ---------------------------------------------------
    public function get_host_url($returnurl = false) {
        $baseurl = \mod_webexactivity\webex::get_base_url();
        $url = $baseurl.'/m.php?AT=HM&MK='.$this->values['meetingkey'];
        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function get_moodle_join_url($user, $returnurl = false) {
        $baseurl = \mod_webexactivity\webex::get_base_url();

        $url = $baseurl.'/m.php?AT=JM&MK='.$this->values['meetingkey'];
        $url .= '&AE='.$user->email.'&AN='.$user->firstname.'%20'.$user->lastname;
        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function get_external_join_url() {
        $baseurl = \mod_webexactivity\webex::get_base_url();

        if (!isset($this->values['eventid'])) {
            $this->get_info(true);
        }

        $url = $baseurl.'/k2/j.php?ED='.$this->values['eventid'].'&UID=1';

        return $url;
    }

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public function get_meeting_webex_user() {
        global $DB, $USER;

        if (isset($this->values['creatorwebexuser'])) {
            $webexuser = $DB->get_record('webexactivity_user', array('id' => $this->values['creatorwebexuser']));
            if ($webexuser) {
                return new \mod_webexactivity\webex_user($webexuser);
            } else {
                $webexuser = $this->webex->get_webex_user($USER);
            }
        } else {
            $webexuser = $this->webex->get_webex_user($USER);
        }

        return $webexuser;
    }

    protected function load_webex_record($meeting) {
        $this->meetingrecord = $meeting;

        $meetingarray = (array) $meeting;

        foreach ($meetingarray as $key => $val) {
            $this->values[$key] = $val;
            if (!array_key_exists($key, $this->values)) {
                debugging('Unknown meeting variable '.$key, DEBUG_DEVELOPER);
            }
        }
    }

    public function save() {
        if (!$this->save_to_webex()) {
            return false;
        }

        if (!$this->save_to_db()) {
            return false;
        }

        return true;
    }

    public function save_to_db() {
        global $DB;

        $this->values['timemodified'] = time();
        $this->meetingrecord = self::array_to_object($this->values);

        if (isset($this->meetingrecord->id)) {
            if ($DB->update_record('webexactivity', $this->meetingrecord)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity', $this->meetingrecord)) {
                $this->meetingrecord->id = $id;
                $this->values['id'] = $id;
                return true;
            }
            return false;
        }
    }

    public function delete() {
        if (!$this->delete_recordings()) {
            return false;
        }

        if (!$this->delete_from_webex()) {
            return false;
        }

        if (!$this->delete_from_db()) {
            return false;
        }

        return true;
    }

    public function delete_from_db() {
        global $DB;

        if (!isset($this->values['id'])) {
            return true;
        }

        if (!$DB->delete_records('webexactivity', array('id' => $this->values['id']))) {
            return false;
        }

        unset($this->values['id']);
        unset($this->meetingrecord);
        return true;
    }

    public static function array_to_object($arr) {
        $obj = new \stdClass();

        foreach ($arr as $key => $val) {
            $obj->$key = $val;
        }

        return $obj;
    }
}
