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

        $xml = type\base\xml_gen::update_user_password($this);

        $response = $webex->get_response($xml);

        if ($response !== false) {
            $this->save_to_db();
        } else {
            return false;
        }

    }

    public function get_login_url($backurl = false, $forwardurl = false) {
        $xml = \mod_webexactivity\type\base\xml_gen::get_user_login_url($this->webexid);

        $webex = new \mod_webexactivity\webex();

        if (!($response = $webex->get_response($xml, $this))) {
            return false;
        }

        $returnurl = $response['use:userLoginURL']['0']['#'];

        if ($backurl) {
            $encoded = urlencode($backurl);
            $returnurl = str_replace('&BU=', '&BU='.$encoded, $returnurl);
        }

        if ($forwardurl) {
            $encoded = urlencode($forwardurl);
            $returnurl = str_replace('&MU=GoBack', '&MU='.$encoded, $returnurl);
        }

        return $returnurl;
    }

    public static function get_logout_url($backurl = false) {
        $url = webex::get_base_url();

        $url .= '/p.php?AT=LO';
        if ($backurl) {
            $encoded = urlencode($backurl);
            $url .= '&BU='.$encoded;
        }

        return $url;
    }

    /**
     * Check if the auth credentials of the WebEx user are good.
     *
     * @return bool    True if auth succeeded, false if failed.
     */
    public function check_user_auth() {
        $xml = type\base\xml_gen::check_user_auth($this);

        $webex = new \mod_webexactivity\webex();

        $response = $webex->get_response($xml);

        if ($response) {
            return true;
        } else {
            return false;
        }
    }

    // TODO create_user.
    // TODO update_user?


    public static function encrypt_password($password) {
        // BOOOOOO Weak!!
        return base64_encode($password);
    }

    public static function decrypt_password($encrypted) {
        // BOOOOOO Weak!!
        return base64_decode($encrypted);
    }

    // ---------------------------------------------------
    // Support Methods.
    // ---------------------------------------------------
    public function save_to_db() {
        global $DB;

        // TODO Time modified.

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
