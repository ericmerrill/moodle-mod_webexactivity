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

class webex_recording {
    private $recording = null;

    // Load these lazily.
    //private $meeting = null;
    private $webex = null;

    public function __construct($recording) {
        global $DB;

        if (is_object($recording)) {
            $this->recording = $recording;
        } else if (is_numeric($recording)) {
            $this->recording = $DB->get_record('webexactivity_recording', array('id' => $recording));
        } else {
            debugging('Recording constructor passed unknown type.', DEBUG_DEVELOPER);
        }

        if (!$this->recording) {
            // TODO Throw exception.
            return false;
        }
    }

    private function load_webex() {
        if (isset($this->webex)) {
            return true;
        }

        $this->webex = new webex();

        return true;
    }

    /*private function load_meeting() {
        if (isset($this->meeting)) {
            return true;
        }

        $this->load_webex();

        $this->meeting = $this->webex->load_meeting($this->get_value('webexid'));

        if (!$this->meeting) {
            $this->meeting = null;
            debugging('Unable to load recording meeting', DEBUG_DEVELOPER);
            return false;
            // TODO error handling.
        }
    }*/

    public function show() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 1;

        $this->set_value('visible', 1);

        return $DB->update_record('webexactivity_recording', $update);
    }

    public function hide() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 0;

        $this->set_value('visible', 0);

        return $DB->update_record('webexactivity_recording', $update);
    }

    public function delete($user = false) {
        global $DB;

        $this->load_webex();

        $xml = xml_gen::delete_recording($this->get_value('recordingid'));

        $webexuser = $this->get_recording_webex_user();

        $response = $this->webex->get_response($xml, $webexuser);

        if ($response === false) {
            // TODO error handling.
            return false;
        }

        $DB->delete_records('webexactivity_recording', array('id' => $this->get_value('id')));

        return true;
    }

    public function set_name($name) {
        global $DB;

        $this->load_webex();

        $this->recording->name = $name;

        $update = new \stdClass;
        $update->id = $this->get_value('id');
        $update->name = $this->get_value('name');
        $DB->update_record('webexactivity_recording', $update);

        $params = new \stdClass;
        $params->recordingid = $this->get_value('recordingid');
        $params->name = $name;

        $xml = xml_gen::update_recording($params);
//print_r($xml);
        $webexuser = $this->get_recording_webex_user();

        $response = $this->webex->get_response($xml, $webexuser);

print_r($response);
    }

    public function get_recording_webex_user() {
        global $DB, $USER;

        if (isset($this->recording->hostid)) {
            $webexuser = $DB->get_record('webexactivity_user', array('webexid' => $this->recording->hostid));
        } else {
            $webexuser = $this->webex->get_webex_user($USER);
        }

        return $webexuser;
    }

    public function get_value($name) {
        return $this->recording->$name;
    }

    public function set_value($name, $val) {
        $this->recording->$name = $val;
    }
}
