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
 * A class for sending out notifications for transfered recordings.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity;

require_once($CFG->libdir . '/filelib.php');

// use \mod_webexactivity\local\type;
use mod_webexactivity\recording;
use curl;
use stdClass;
use core\lock\lock_config;
use mod_webexactivity\local\exception;


defined('MOODLE_INTERNAL') || die();

/**
 * A class for sending out notifications for transfered recordings.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_notifier {

    const NOTIFY_NONE = 0;
    const NOTIFY_ASSOCIATED = 1;
    const NOTIFY_UNASSOCIATED = 2;
    const NOTIFY_ALL = 3;

    /** @var recording The recording object we are downloading. */
    private $recording = null;

    /**
     * Builds the recording object.
     *
     * @param recording    $recording Object of recording
     * @throws coding_exception when bad parameter received.
     */
    public function __construct(recording $recording) {
        $this->recording = $recording;
    }

    public function get_email_addresses() {
        global $DB;

        $hostid = $this->recording->hostid;

        // See if we already know the user.
        $webexuser = user::load_webex_id($hostid);
        if (!empty($webexuser)) {
            if (!empty($webexuser->moodleuserid)) {
                // Use the Moodle email address if it exists.
                if ($user = $DB->get_record('user', ['id' => $webexuser->moodleuserid])) {
                    return [$user->email];
                }
            }

            // Now use the Webex user email address if we have it.
            if (!empty($webexuser->email)) {
                return [$webexuser->email];
            }
        }

        // We don't have the user's object or email address. Now we ask Webex directly.
        $user = user::search_webex_for_webexid($hostid);
        if (!empty($user) && !empty($user->email)) {
            return [$user->email];
        }

        return false;
    }

    public function get_email_subject() {
        return $this->subsitute_text(get_config('webexactivity', 'notifysubject'));
    }

    public function get_email_body() {
        return $this->subsitute_text(get_config('webexactivity', 'notifyemail'));
    }

    protected function subsitute_text($input) {
        if (empty($input)) {
            return '';
        }

        return preg_replace_callback(
                '/%%([A-Z]*?)%%/',
                function($matches) {
                    switch ($matches[1]) {
                        case 'RECORDINGNAME':
                            return $this->recording->name;
                            break;
                        case 'NEWURL':
                            return $this->recording->get_recording_url();
                            break;
                        case 'RECORDINGDATETIME':
                            return userdate($this->recording->timecreated);
                            break;
                        default:
                            return $matches[1];
                    }
                },
                $input
        );
    }


    public function log($msg) {
        if (defined('CLI_SCRIPT')) {
            mtrace('Recording ' . $this->recording->id . ': ' . $msg);
        }
    }




    private function get_from_user() {
        return \core_user::get_noreply_user();
    }

    private function get_fake_user($email) {
        $user = new \stdClass();
        $user->id = mt_rand(99999800, 99999999); // we have to pass an id
        $user->email = $email;
        $user->username = $email;
        $user->mailformat = 1;

        return $user;
    }

    public function send($subject, $body, $touser) {
        $success = email_to_user(
            $touser,
            $this->get_from_user(),
            $subject,
            format_text_email($body, 1),
            purify_html($body)
        );

        return $success;
    }


}
