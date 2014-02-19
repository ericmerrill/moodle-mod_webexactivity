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
    /** @var object The database record this object represents. */
    private $recording = null;

    /** @var bool Track if there is a change that needs to go to WebEx. */
    private $webexchange = false;

    /**
     * Builds the recording object.
     *
     * @param object|int    $recording Object of recording record, or id of record to load.
     */
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

    /**
     * Mark this recording for deletion.
     *
     * @return bool    True on success, false on failure.
     */
    public function delete() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->id;
        $update->deleted = time();
        return $DB->update_record('webexactivity_recording', $update);
    }

    /**
     * Un-delete this recording.
     *
     * @return bool    True on success, false on failure.
     */
    public function undelete() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->id;
        $update->deleted = 0;
        return $DB->update_record('webexactivity_recording', $update);
    }

    /**
     * Delete this recording from WebEx.
     *
     * @return bool    True on success, false on failure.
     */
    public function true_delete() {
        global $DB;

        $this->load_webex();

        $xml = type\base\xml_gen::delete_recording($this->__get('recordingid'));

        $webexuser = $this->get_recording_webex_user();

        $webex = new webex();
        $response = $webex->get_response($xml, $webexuser);

        if ($response === false) {
            // TODO error handling.
            return false;
        }

        $DB->delete_records('webexactivity_recording', array('id' => $this->__get('id')));

        return true;
    }

    /**
     * Returns the webex_user that created this recording.
     *
     * @return bool|webex_user    The WebEx user. False on failure.
     */
    public function get_recording_webex_user() {
        global $USER;

        if (isset($this->recording->hostid)) {
            $webexuser = new \mod_webexactivity\webex_user($this->recording->hostid);
        } else {
            $webex = new webex();
            $webexuser = $webex->get_webex_user($USER);
        }

        return $webexuser;
    }

    /**
     * Save the recording to WebEx and Moodle as needed.
     *
     * @return bool    True on success, false on failure/error.
     */
    public function save() {
        if ($this->webexchange) {
            if (!$this->save_to_webex()) {
                return false;
            }
        }
        return $this->save_to_db();
    }

    /**
     * Save the recording to the Moodle database.
     *
     * @return bool    True on success, false on failure/error.
     */
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

    /**
     * Save this recording object into WebEx.
     *
     * @return bool    True on success, false on failure.
     */
    public function save_to_webex() {
        $this->load_webex();

        $params = new \stdClass;
        $params->recordingid = $this->__get('recordingid');
        $params->name = $this->recording->name;

        $xml = type\base\xml_gen::update_recording($params);

        $webexuser = $this->get_recording_webex_user();

        $webex = new webex();
        $response = $webex->get_response($xml, $webexuser);

        if ($response) {
            $this->webexchange = false;
            return true;
        } else {
            return false;
        }
    }

    // ---------------------------------------------------
    // Magic Methods.
    // ---------------------------------------------------

    /**
     * Magic setter method for object.
     *
     * @param string    $name The name of the value to be set.
     * @param mixed     $val  The value to be set.
     */
    public function __set($name, $val) {
        switch ($name) {
            case 'name':
                if (strcmp($val, $this->recording->name) === 0) {
                    return;
                }
                $this->webexchange = true;
                break;
            case 'visible':
                if ($val) {
                    $val = 1;
                } else {
                    $val = 0;
                }
                break;
            case 'record':
                debugging('Recording record can only be set at construction time');
                return;
        }

        $this->recording->$name = $val;
    }

    /**
     * Magic getter method for object.
     *
     * @param string    $name The name of the value to be retrieved.
     */
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

    /**
     * Magic isset method for object.
     *
     * @param string    $name The name of the value to be checked.
     */
    public function __isset($name) {
        switch ($name) {
            case 'record':
                return isset($this->recording);
        }
        return isset($this->recording->$name);
    }

    /**
     * Magic unset method for object.
     *
     * @param string    $name The name of the value to be unset.
     */
    public function __unset($name) {
        unset($this->recording->$name);
    }
}
