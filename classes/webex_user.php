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
 * A class that represents a WebEx user.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webex_user {
    private $user = null;

    // Load these lazily.
    //private $webex = null;

    public function __construct($user = null) {
        global $DB;

        if (is_null($user)) {
            $this->user = new \stdClass();
        } else if (is_object($user)) {
            $this->user = $user;
        } else if (is_numeric($user)) {
            $this->user = $DB->get_record('webexactivity_user', array('id' => $user));
        } else if (is_string($user)) {
            $this->user = $DB->get_record('webexactivity_user', array('webexid' => $user));
        } else {
            debugging('User constructor passed unknown type.', DEBUG_DEVELOPER);
        }

        if (!$this->user) {
            // TODO Throw exception.
            return false;
        }
    }

    // ---------------------------------------------------
    // User Methods.
    // ---------------------------------------------------
    public function update_password($password) {
        $this->user->password = self::encrypt_password($password);

        $webex = new webex();

        $xml = xml_gen\base::update_user_password($this);

        $response = $webex->get_response($xml);

        if ($response !== false) {
            $this->save_to_db();
        } else {
            return false;
        }

    }

    // TODO create_user();
    // TODO update_user();?


    public static function encrypt_password($password) {
        // BOOOOOO Weak!!
        return base64_encode($password);
    }

    public static function decrypt_password($encrypted) {
        // BOOOOOO Weak!!
        return base64_decode($encrypted);
    }

    // ---------------------------------------------------
    // Magic Methods.
    // ---------------------------------------------------
    public function save_to_db() {
        global $DB;

        // TODO.
        //$this->recording->timemodified = time();

        if (isset($this->user->id)) {
            if ($DB->update_record('webexactivity_user', $this->user)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity_user', $this->user)) {
                $this->user->id = $id;
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
            case 'password':
                $this->user->password = self::encrypt_password($val);
                break;
            default:
                $this->user->$name = $val;
        }

        $this->save_to_db();
    }

    public function __get($name) {
        switch ($name) {
            case 'password':
                $pass = self::decrypt_password($this->user->password);
                return $pass;
                break;
            case 'record':
                return $this->user;
                break;
        }
        
        return $this->user->$name;
    }

    public function __isset($name) {
        return isset($this->user->$name);
    }


}
