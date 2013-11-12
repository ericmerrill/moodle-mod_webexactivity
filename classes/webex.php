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

class webex {

    public function __construct() {
    }

    // ---------------------------------------------------
    // User Functions.
    // ---------------------------------------------------
    public function create_user($moodleuser) {
        global $DB;

        if (self::get_webex_user($moodleuser)) {
            return true;
        }

        $data = new \stdClass();
        $data->firstname = $moodleuser->firstname;
        $data->lastname = $moodleuser->lastname;
        $data->webexid = $moodleuser->username;
        $data->email = $moodleuser->email;
        $data->password = self::generate_password();

        $xml = xml_generator::create_user($data);

        $response = $this->get_response($xml);

        if ($response) {
            if (isset($response['use:userId']['0']['#'])) {
                $webexuser = new \stdClass();
                $webexuser->moodleuserid = $moodleuser->id;
                $webexuser->webexid = $response['use:userId']['0']['#'];
                $webexuser->username = $data->webexid;
                $webexuser->password = self::encrypt_password($data->password);
                if ($DB->insert_record('webexactivity_users', $webexuser)) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }

        return false;
    }

    public function test_user($webexuser) {

    }

    public static function get_webex_user($moodleuser) {
        global $DB;

        if (!is_object($moodleuser) || !isset($moodleuser->id)) {
            return false;
        }

        return $DB->get_record('webexactivity_users', array('moodleuserid' => $moodleuser->id));
    }

    private static function generate_password() {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array();
        $length = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $length);
            $pass[] = $alphabet[$n];
        }
        return implode($pass).'!2D';
    }

    public static function encrypt_password($password) {
        // BOOOOOO Weak!!
        return base64_encode($password);
    }

    public static function decrypt_password($encrypted) {
        // BOOOOOO Weak!!
        return base64_decode($encrypted);
    }


    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public function create_or_update_meeting($webexrecord) {
        global $DB;

        if (isset($webexrecord->meetingkey) && $webexrecord->meetingkey) {
            // Update.
            return true;
        }

        $xml = xml_generator::create_meeting($webexrecord);

        $response = $this->get_response($xml);

        if ($response) {
            if (isset($response['meet:meetingkey']['0']['#'])) {
                $webexrecord->meetingkey = $response['meet:meetingkey']['0']['#'];
                $DB->update_record('webexactivity', $webexrecord);
                return true;
            }
        } else {
            return false;
        }
    }

    public function create_or_update_training($webexrecord) {
        global $DB;

        if (isset($webexrecord->meetingkey) && $webexrecord->meetingkey) {
            // Update.
            return true;
        }

        $xml = xml_generator::create_training_session($webexrecord);

        $response = $this->get_response($xml);

        if ($response) {
            if (isset($response['train:sessionkey']['0']['#'])) {
                $webexrecord->meetingkey = $response['train:sessionkey']['0']['#'];
                $DB->update_record('webexactivity', $webexrecord);
                return true;
            }
        } else {
            return false;
        }
    }

    public function create_meeting($data) {

    }

    public function update_meeting($data) {

    }

    // ---------------------------------------------------
    // Connection Functions.
    // ---------------------------------------------------
    private function get_response($xml) {
        $connector = new service_connector();
        $stat = $connector->retrieve($xml);

        if ($stat) {
            $response = $connector->get_response_array();
            if (isset($response['serv:message']['#']['serv:body']['0']['#']['serv:bodyContent']['0']['#'])) {
                return $response['serv:message']['#']['serv:body']['0']['#']['serv:bodyContent']['0']['#'];
            } else {
                return false;
            }
        } else {
            print "<pre>"; // TODO Temp.
            print_r($connector->get_errors());
            print "</pre>";
            return false;
        }
    }
}