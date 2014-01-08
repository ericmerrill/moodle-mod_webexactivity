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
    private $latesterrors = null;

    public function __construct() {
    }


    // ---------------------------------------------------
    // User Functions.
    // ---------------------------------------------------
    public function get_webex_user($moodleuser, $checkauth = false) {
        $webexuser = $this->get_webex_user_record($moodleuser);

        // User not in table, make.
        if ($webexuser === false) {
            return false;
        }

        if ($checkauth) {
            $status = $this->check_user_auth($webexuser);
            if ($status) {
                return $webexuser;
            } else {
                $webexuser = $this->update_user_password($webexuser);
                return $webexuser;
            }
        } else {
            return $webexuser;
        }
    }

    public function get_webex_user_record($moodleuser) {
        global $DB;

        if (!is_object($moodleuser) || !isset($moodleuser->id)) {
            return false;
        }

        $webexuser = $DB->get_record('webexactivity_users', array('moodleuserid' => $moodleuser->id));

        if ($webexuser !== false) {
            $webexuser->password = self::decrypt_password($webexuser->password);
            return $webexuser;
        }

        $prefix = get_config('webexactivity', 'prefix');

        $data = new \stdClass();
        $data->firstname = $moodleuser->firstname;
        $data->lastname = $moodleuser->lastname;
        $data->webexid = $prefix.$moodleuser->username;
        $data->email = $moodleuser->email;
        $data->password = self::generate_password();

        $xml = xml_generator::create_user($data);

        $response = $this->get_response($xml);

        if ($response) {
            if (isset($response['use:userId']['0']['#'])) {
                $webexuser = new \stdClass();
                $webexuser->moodleuserid = $moodleuser->id;
                $webexuser->webexuserid = $response['use:userId']['0']['#'];
                $webexuser->webexid = $data->webexid;
                $webexuser->password = self::encrypt_password($data->password);
                if ($webexuser->id = $DB->insert_record('webexactivity_users', $webexuser)) {
                    return $webexuser;
                } else {
                    return false;
                }
            }
        } else {
            // Failure creating user. Check to see if exists.
            if (!isset($this->latesterrors['exception'])) {
                // No info, just end here.
                return false;
            }
            $exception = $this->latesterrors['exception'];
            // User already exists with this username or email.

            if ((stripos($exception, '030004') !== false) || (stripos($exception, '030005') === false)) {
                $xml = xml_generator::get_user_info($data->webexid);

                if (!($response = $this->get_response($xml))) {
                    return false;
                }

                if (strcasecmp($data->email, $response['use:email']['0']['#']) === 0) {
                    $newwebexuser = new \stdClass();
                    $newwebexuser->moodleuserid = $moodleuser->id;
                    $newwebexuser->webexid = $data->webexid;
                    $newwebexuser->webexuserid = $response['use:userId']['0']['#'];
                    $newwebexuser->password = '';
                    if ($newwebexuser->id = $DB->insert_record('webexactivity_users', $newwebexuser)) {
                        $newwebexuser = $this->update_user_password($newwebexuser);

                        return $newwebexuser;
                    } else {
                        return false;
                    }
                }
            }
        }

        return false;
    }

    public function check_user_auth($webexuser) {
        $xml = xml_generator::check_user_auth($webexuser);

        if (!($response = $this->get_response($xml))) {
            return false;
        }

        $response = $this->get_response($xml);

        if ($response) {
            return true;
        } else {
            return false;
        }
    }

    public function update_user_password($webexuser) {
        global $DB;

        $webexuser->password = self::generate_password();

        $xml = xml_generator::update_user_password($webexuser);

        $response = $this->get_response($xml);

        if ($response !== false) {
            $newwebexuser = new \stdClass();
            $newwebexuser->id = $webexuser->id;
            $newwebexuser->password = self::encrypt_password($webexuser->password);
            if ($DB->update_record('webexactivity_users', $newwebexuser)) {
                return $webexuser;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function get_login_url($webexuser, $backurl = false, $forwardurl = false) {
        $xml = xml_generator::get_user_login_url($webexuser->webexid);

        if (!($response = $this->get_response($xml, $webexuser))) {
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


    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public static function get_base_url() {
        $host = get_config('webexactivity', 'url');

        if ($host === false) {
            return false;
        }
        $url = 'https://'.$host.'.webex.com/'.$host;

        return $url;
    }

    private static function generate_password() {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array();
        $length = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $length);
            $pass[] = $alphabet[$n];
        }
        return implode($pass).'!2Da';
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
    // Recording Functions.
    // ---------------------------------------------------
    public function get_recordings() {
        $params = new \stdClass();
        $params->startdate = time() - (2 * 24 * 3600);
        $params->enddate = time() + (12 * 3600);

        $xml = xml_generator::list_recordings($params);

        if (!($response = $this->get_response($xml))) {
            return false;
        }

        return $this->proccess_recording_response($response);
    }

    public function proccess_recording_response($response) {
        global $DB;

        $recordings = $response['ep:recording'];

        foreach ($recordings as $recording) {
            $recording = $recording['#'];

            if (!isset($recording['ep:sessionKey'][0]['#'])) {
                continue;
            }

            $key = $recording['ep:sessionKey'][0]['#'];
            $meeting = $DB->get_record('webexactivity', array('meetingkey' => $key));
            if (!$meeting) {
                continue;
            }

            $rec = new \stdClass();
            $rec->webexid = $meeting->id;
            $rec->meetingkey = $key;
            $rec->recordingid = $recording['ep:recordingID'][0]['#'];
            $rec->hostid = $recording['ep:hostWebExID'][0]['#'];
            $rec->name = $recording['ep:name'][0]['#'];
            $rec->timecreated = strtotime($recording['ep:createTime'][0]['#']);
            $rec->streamurl = $recording['ep:streamURL'][0]['#'];
            $rec->fileurl = $recording['ep:fileURL'][0]['#'];
            $rec->duration = $recording['ep:duration'][0]['#'];

            if (!$DB->get_record('webexactivity_recording', array('recordingid' => $rec->recordingid))) {
                $DB->insert_record('webexactivity_recording', $rec);
            }
        }
    }

/*    public function retrieve_recordings() {
        global $DB;

        $this->meetingrecord->laststatuscheck = time();
        $DB->update_record('webexactivity', $this->meetingrecord);

        if (!$this->meetingrecord->meetingkey) {
            return;
        }

        $xml = xml_generator::list_recordings($this->meetingrecord->meetingkey);

        if (!($response = $this->get_response($xml))) {
            return false;
        }

        $response = $this->get_response($xml);
        $recordings = $response['ep:recording'];

        foreach ($recordings as $recording) {
            $recording = $recording['#'];

            $rec = new \stdClass();
            $rec->webexid = $this->meetingrecord->id;
            $rec->meetingkey = $this->meetingrecord->meetingkey;
            $rec->recordingid = $recording['ep:recordingID'][0]['#'];
            $rec->hostid = $recording['ep:hostWebExID'][0]['#'];
            $rec->name = $recording['ep:name'][0]['#'];
            $rec->timecreated = strtotime($recording['ep:createTime'][0]['#']);
            $rec->streamurl = $recording['ep:streamURL'][0]['#'];
            $rec->fileurl = $recording['ep:fileURL'][0]['#'];
            $rec->duration = $recording['ep:duration'][0]['#'];

            if (!$DB->get_record('webexactivity_recording', array('recordingid' => $rec->recordingid))) {
                $DB->insert_record('webexactivity_recording', $rec);
            }
        }

    }*/



    // ---------------------------------------------------
    // Connection Functions.
    // ---------------------------------------------------
    public function get_response($basexml, $webexuser = false) {
        global $USER;

        $xml = xml_generator::auth_wrap($basexml, $webexuser);

        list($status, $response, $errors) = $this->fetch_response($xml);

        if ($status) {
            return $response;
        } else {
            // Bad user password, reset it and try again.
            if ($webexuser && (isset($errors['exception'])) && ($errors['exception'] === '030002')) {
                $webexuser = $this->update_user_password($webexuser);
                $xml = xml_generator::auth_wrap($basexml, $webexuser);
                list($status, $response, $errors) = $this->fetch_response($xml);
                if ($status) {
                    return $response;
                }
            }

            print "<pre>"; // TODO Temp.
            print_r($errors);
            print "</pre>";
            return false;
        }
    }

    private function fetch_response($xml) {
        $connector = new service_connector();
        $status = $connector->retrieve($xml);

        if ($status) {
            $response = $connector->get_response_array();
            if (isset($response['serv:message']['#']['serv:body']['0']['#']['serv:bodyContent']['0']['#'])) {
                $response = $response['serv:message']['#']['serv:body']['0']['#']['serv:bodyContent']['0']['#'];
            } else {
                $response = false;
                $status = false;
            }
        } else {
//            print $xml;
            $response = false;
        }
        $errors = $connector->get_errors();
        $this->latesterrors = $errors;

        return array($status, $response, $errors);
    }
}