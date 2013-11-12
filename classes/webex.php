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
        global $USER;
        $this->create_user($USER);
    }

    // ---------------------------------------------------
    // User Functions.
    // ---------------------------------------------------
    public function create_user($moodleuser) {
        if (self::get_webex_user($moodleuser)) {
            return true;
        }

        $data = new \stdClass();
        $data->firstname = $moodleuser->firstname;
        $data->lastname = $moodleuser->lastname;
        $data->webexid = $moodleuser->username;
        $data->email = $moodleuser->email;
        $data->password = self::generate_password();

        print p(xml_generator::create_user($data));
    }

    public function test_user($webexuser) {

    }

    public function get_webex_user($moodleuser) {
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
        return implode($pass);
    }


    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public function create_meeting($data) {

    }

    public function update_meeting($data) {

    }
}