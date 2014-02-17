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

namespace mod_webexactivity\type\base;

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
class meeting {
    /** @var object Record object containing the information about the meeting. */
    protected $meetingrecord = null;

    /** @var array An array of expected keys. */
    protected $keys = array(
            'id' => null,
            'course' => 0,
            'name' => '',
            'intro' => null,
            'introformat' => 0,
            'creatorwebexuser' => null,
            'type' => null,
            'meetingkey' => null,
            'guestkey' => null,
            'eventid' => null,
            'hostkey' => null, // Unused?
            'starttime' => null,
            'endtime' => null,
            'duration' => null,
            'hosts' => null, // Unused?
            'allchat' => 1, // Used for MC.
            'studentdownload' => 1,
            'laststatuscheck' => 0,
            'status' => \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_NEVER_STARTED,
            'timemodified' => 0);

    /** @var object A webex object to do network connections and other support services. */
    protected $webex;

    /** @var bool Track if there is a change that needs to go to WebEx. */
    protected $webexchange;

    /** 
     * The XML generator class name to use. Can be redefined by child classes.
     **/
    const GENERATOR = '\mod_webexactivity\type\base\xml_gen';

    /** 
     * Prefix for retrieved XML fields.
     **/
    const XML_PREFIX = '';

    /**
     * Builds the meeting object.
     *
     * @param object/int    $meeting Object of meeting record, or id of record to load.
     */
    public function __construct($meeting = false) {
        global $DB;

        $this->webex = new \mod_webexactivity\webex();

        if ($meeting === false) {
            $this->meetingrecord = new \stdClass();
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
    // Magic Methods.
    // ---------------------------------------------------
    public function __set($name, $val) {
        switch ($name) {
            case 'starttime':
                // If the current time is not set, new meeting.
                if (!isset($this->starttime)) {
                    // If the time is past, or near past, set it to the near future.
                    if ($val < (time() + 60)) {
                        $val = time() + 60;
                    }
                } else if ($val < (time() + 60)) {
                    $curr = $this->starttime;
                    // If the current time is already set, and the time is past or near past.
                    if ($curr > time()) {
                        // If the current time is in the future, assume they want to start it now.
                        $val = time() + 60;
                    } else {
                        // If they are both in the past, leave it as the old setting. Can't change it.
                        $val = $curr;
                    }
                }
                break;
            case 'xml':
            case 'guestuserid':
                debugging('Meeting property "'.$name.'" removed.', DEBUG_DEVELOPER);
                return false;
                break;
            case 'status':
                if (isset($this->status) && ($val != $this->status)) {
                    if ($val === \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
                        $cm = get_coursemodule_from_instance('webexactivity', $this->id);
                        $context = \context_module::instance($cm->id);
                        $params = array(
                            'context' => $context,
                            'objectid' => $this->id
                        );
                        $event = \mod_webexactivity\event\meeting_started::create($params);
                        $event->add_record_snapshot('webexactivity', $this->meetingrecord);
                        $event->trigger();
                    } else if ($val === \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_STOPPED) {
                        $cm = get_coursemodule_from_instance('webexactivity', $this->id);
                        $context = \context_module::instance($cm->id);
                        $params = array(
                            'context' => $context,
                            'objectid' => $this->id
                        );
                        $event = \mod_webexactivity\event\meeting_ended::create($params);
                        $event->add_record_snapshot('webexactivity', $this->meetingrecord);
                        $event->trigger();
                    }
                }
                break;
        }

        switch ($name) {
            case 'starttime':
            case 'duration':
            case 'name':
            case 'intro':
                if (!isset($this->$name) || ($this->$name !== $val)) {
                    $this->webexchange = true;
                }
                break;
        }

        $this->meetingrecord->$name = $val;
        if (!array_key_exists($name, $this->keys)) {
            debugging('Unknown meeting value set "'.$name.'"', DEBUG_DEVELOPER);
            return false;
        }
        return true;
        // TODO.
    }

    public function __get($name) {
        if (!array_key_exists($name, $this->keys)) {
            debugging('Unknown meeting value requested "'.$name.'"', DEBUG_DEVELOPER);
            return false;
        }

        return $this->meetingrecord->$name;
        // TODO.
    }

    public function __isset($name) {
        return isset($this->meetingrecord->$name);
    }



    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public function save_to_webex() {
        $data = $this->meetingrecord;
        $gen = static::GENERATOR;
        $webexuser = $this->get_meeting_webex_user();

        if (isset($this->meetingkey)) {
            // Updating meeting.
            $xml = $gen::update_meeting($data);
        } else {
            // Creating meeting.
            $xml = $gen::create_meeting($data);
            $this->meetingrecord->creatorwebexuser = $webexuser->id;
        }

        $response = $this->webex->get_response($xml, $webexuser);

        $status = $this->process_response($response);

        if ($status) {
            $this->webexchange = false;
            return true;
        } else {
            return false;
        }
    }

    public function delete_from_webex() {
        $gen = static::GENERATOR;
        $webexuser = $this->get_meeting_webex_user();

        $xml = $gen::delete_meeting($this->meetingkey);

        $response = $this->webex->get_response($xml, $webexuser);

        if (empty($response)) {
            return true;
        }

        return false;
    }

    public function get_info($save = false) {
        if (!isset($this->meetingkey)) {
            return false;
        }

        $gen = static::GENERATOR;
        $webexuser = $this->get_meeting_webex_user();

        $xml = $gen::get_meeting_info($this->meetingkey);

        if (!$response = $this->webex->get_response($xml, $webexuser)) {
            return false;
        }

        if (!$this->process_response($response)) {
            return false;
        }

        if ($save) {
            $this->save();
        }

        return $response;
    }

    /**
     * Return the time status (upcoming, in progress, past, long past, available).
     *
     * @return int   Constant represents status.
     */
    public function get_time_status() {
        $time = time();
        $grace = get_config('webexactivity', 'meetingclosegrace');

        if (isset($this->endtime)) {
            $endtime = $this->endtime;
        } else {
            $endtime = $this->starttime + ($this->duration * 60) + ($grace * 60);
        }

        $starttime = $this->starttime - (20 * 60);

        if ($this->status == \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
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

    /**
     * Return if the meeting is available to join/host.
     *
     * @param bool    $host Set to true if host, false if not.
     * @return bool   True if available, false if not.
     */
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

    /**
     * Process a response from WebEx into the meeting. Must be overridden.
     *
     * @param array    $response XML array of the response from WebEx for meeting information.
     * @return bool    True on success, false on failure/error.
     */
    protected function process_response($response) {
        $prefix = static::XML_PREFIX;

        if (empty($prefix)) {
            debugging('Function process_response must be implemented by child class.', DEBUG_DEVELOPER);
        }

        if ($response === false) {
            return false;
        }

        if (empty($response)) {
            return true;
        }

        if (isset($response[$prefix.':additionalInfo']['0']['#'][$prefix.':guestToken']['0']['#'])) {
            $this->guestkey = $response[$prefix.':additionalInfo']['0']['#'][$prefix.':guestToken']['0']['#'];
        }

        if (isset($response[$prefix.':eventID']['0']['#'])) {
            $this->eventid = $response[$prefix.':eventID']['0']['#'];
        }

        if (isset($response[$prefix.':hostKey']['0']['#'])) {
            $this->hostkey = $response[$prefix.':hostKey']['0']['#'];
        }

        return true;

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
        $data->meetingkey = $this->meetingkey;
        $data->hostusers = array($user);

        $gen = static::GENERATOR;
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

        $recordingrecords = $DB->get_records('webexactivity_recording', array('webexid' => $this->id, 'deleted' => 0));

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
        $url = $baseurl.'/m.php?AT=HM&MK='.$this->meetingkey;
        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function get_moodle_join_url($user, $returnurl = false) {
        $baseurl = \mod_webexactivity\webex::get_base_url();

        $url = $baseurl.'/m.php?AT=JM&MK='.$this->meetingkey;
        $url .= '&AE='.$user->email.'&AN='.$user->firstname.'%20'.$user->lastname;
        if ($returnurl) {
            $url .= '&BU='.urlencode($returnurl);
        }

        return $url;
    }

    public function get_external_join_url() {
        $baseurl = \mod_webexactivity\webex::get_base_url();

        if (!isset($this->eventid)) {
            $this->get_info(true);
        }

        $url = $baseurl.'/k2/j.php?ED='.$this->eventid.'&UID=1';

        return $url;
    }

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public function get_meeting_webex_user() {
        global $DB, $USER;

        if (isset($this->creatorwebexuser)) {
            $webexuser = $DB->get_record('webexactivity_user', array('id' => $this->creatorwebexuser));
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
            if (!array_key_exists($key, $this->keys)) {
                debugging('Unknown meeting variable '.$key, DEBUG_DEVELOPER);
            }
        }
    }

    public function save() {
        if ($this->webexchange) {
            if (!$this->save_to_webex()) {
                return false;
            }
        }

        if (!$this->save_to_db()) {
            return false;
        }

        return true;
    }

    public function save_to_db() {
        global $DB;

        $this->timemodified = time();

        if (isset($this->id)) {
            if ($DB->update_record('webexactivity', $this->meetingrecord)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity', $this->meetingrecord)) {
                $this->id = $id;
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

        if (!isset($this->id)) {
            return true;
        }

        if (!$DB->delete_records('webexactivity', array('id' => $this->id))) {
            return false;
        }

        unset($this->id);
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
