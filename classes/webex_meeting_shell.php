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

class webex_meeting_shell {
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
            'status' => WEBEXACTIVITY_STATUS_NEVER_STARTED,
            'timemodified' => 0);

    protected $webex;

    public function __construct($meeting = false) {
        global $DB;

        $this->webex = new webex();

        if (is_numeric($meeting)) {
            $this->meetingrecord = $DB->get_record('webexactivity', array('id' => $meeting));
        }
        if (is_object($meeting)) {
            $this->meetingrecord = $meeting;
        }

    }

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public function get_meeting_webex_user() {
        global $DB, $USER;

        if (isset($this->meetingrecord->creatorwebexuser) && $this->meetingrecord->creatorwebexuser) {
            $webexuser = $DB->get_record('webexactivity_users', array('id' => $this->meetingrecord->creatorwebexuser));
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

    public function set_value($name, $val) {
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

    public function save() {
        global $DB;

        $this->meetingrecord = new \stdClass();

        foreach ($this->values as $key => $val) {
            $this->meetingrecord->$key = $val;
        }

        if (isset($this->meetingrecord->id)) {
            if ($DB->update_record('webexactivity', $this->meetingrecord)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity', $this->meetingrecord)) {
                $this->meetingrecord->id = id;
                $this->values['id'] = $id;
                return true;
            }
            return false;
        }
    }
}
