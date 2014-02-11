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

defined('MOODLE_INTERNAL') || die();

/**
 * A class that represents a WebEx recording.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webex_recording {
    private $recording = null;

    // Load these lazily.
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

    private function show() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 1;

        $this->__set('visible', 1);

        return $DB->update_record('webexactivity_recording', $update);
    }

    private function hide() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 0;

        $this->__set('visible', 0);

        return $DB->update_record('webexactivity_recording', $update);
    }

    public function delete() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->deleted = time();
        return $DB->update_record('webexactivity_recording', $update);
    }

    public function true_delete() {
        global $DB;

        $this->load_webex();

        $xml = xml_gen\base::delete_recording($this->__get('recordingid'));

        $webexuser = $this->get_recording_webex_user();

        $response = $this->webex->get_response($xml, $webexuser);

        if ($response === false) {
            // TODO error handling.
            return false;
        }

        $DB->delete_records('webexactivity_recording', array('id' => $this->__get('id')));

        return true;
    }

    private function set_name($name) {
        global $DB;

        $this->load_webex();

        $this->recording->name = $name;

        $params = new \stdClass;
        $params->recordingid = $this->__get('recordingid');
        $params->name = $name;

        $xml = xml_gen\base::update_recording($params);

        $webexuser = $this->get_recording_webex_user();

        $response = $this->webex->get_response($xml, $webexuser);
    }

    public function get_recording_webex_user() {
        global $USER;

        if (isset($this->recording->hostid)) {
            $webexuser = new \mod_webexactivity\webex_user($this->recording->hostid);
        } else {
            $webexuser = $this->webex->get_webex_user($USER);
        }

        return $webexuser;
    }

    public function save_to_db() {
        global $DB;

        $this->recording->timemodified = time();

        if (isset($this->recording->id)) {
            if ($DB->update_record('webexactivity_recording', $this->recording)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity_recording', $this->recording)) {
                $this->recording->id = $id;
                return true;
            }
            return false;
        }
    }


    // ---------------------------------------------------
    // Magic Methods.
    // ---------------------------------------------------
    public function __set($name, $val) {
        switch ($name) {
            case 'name':
                $this->set_name($val);
                break;
            case 'visible':
                if ($val) {
                    $this->show();
                } else {
                    $this->hide();
                }
                return;
                break;
            case 'record':
                debugging('Recording record can only be set at construction time');
                return;
        }

        $this->recording->$name = $val;
    }

    public function __get($name) {
        switch ($name) {
            case 'visible':
                if ($this->recording->deleted > 0) {
                    return 0;
                }
                break;
            case 'record':
                return $this->recording;
        }

        return $this->recording->$name;
    }

    public function __isset($name) {
        switch ($name) {
            case 'record':
                return isset($this->recording);
        }
        return isset($this->recording->$name);
    }
}
