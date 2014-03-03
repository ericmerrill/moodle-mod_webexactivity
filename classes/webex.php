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
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity;

defined('MOODLE_INTERNAL') || die();

/**
 * A class that provides general WebEx services and constants.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webex {
    /**
     * Type that represents a Meeting Center meeting.
     */
    const WEBEXACTIVITY_TYPE_MEETING = 1;

    /**
     * Type that represents a Training Center meeting.
     */
    const WEBEXACTIVITY_TYPE_TRAINING = 2;

    /**
     * Type that represents a Support Center meeting.
     */
    const WEBEXACTIVITY_TYPE_SUPPORT = 3;

    /**
     * Status that represents a meeting that has never started.
     */
    const WEBEXACTIVITY_STATUS_NEVER_STARTED = 0;

    /**
     * Status that represents a meeting that has stopped.
     */
    const WEBEXACTIVITY_STATUS_STOPPED = 1;

    /**
     * Status that represents a meeting that is in progress.
     */
    const WEBEXACTIVITY_STATUS_IN_PROGRESS = 2;

    /**
     * Time status that represents a meeting that is upcoming.
     */
    const WEBEXACTIVITY_TIME_UPCOMING = 0;

    /**
     * Time status that represents a meeting that is available.
     */
    const WEBEXACTIVITY_TIME_AVAILABLE = 1;

    /**
     * Time status that represents a meeting that is in progress.
     */
    const WEBEXACTIVITY_TIME_IN_PROGRESS = 2;

    /**
     * Time status that represents a meeting that is in the recent past.
     */
    const WEBEXACTIVITY_TIME_PAST = 3;

    /**
     * Time status that represents a meeting that is in the distant past.
     */
    const WEBEXACTIVITY_TIME_LONG_PAST = 4;

    /**
     * The flag for Available for meeting types.
     */
    const WEBEXACTIVITY_TYPE_INSTALLED = 'inst';

    /**
     * The flag for Available to all setting for meeting types.
     */
    const WEBEXACTIVITY_TYPE_ALL = 'all';

    /** @var mixed Storage for the latest errors from a connection. */
    private $latesterrors = null;

    // ---------------------------------------------------
    // User Functions.
    // ---------------------------------------------------

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    /**
     * Return the base URL for the WebEx server.
     *
     * @return string  The base URL.
     */
    public static function get_base_url() {
        $host = get_config('webexactivity', 'url');

        if ($host === false) {
            return false;
        }
        $url = 'https://'.$host.'.webex.com/'.$host;

        return $url;
    }

    /**
     * Generate a password that will pass the WebEx requirements.
     *
     * @return string  The generated password.
     */
    public static function generate_password() {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array();
        $length = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $length);
            $pass[] = $alphabet[$n];
        }
        return implode($pass).'!2Da';
    }

    /**
     * Check and update open sessions/meetings from WebEx.
     *
     * @return bool  True on success, false on failure.
     */
    public function update_open_sessions() {
        global $DB;

        $xml = type\base\xml_gen::list_open_sessions();

        $response = $this->get_response($xml);
        if ($response === false) {
            return false;
        }

        $processtime = time();
        $cleartime = $processtime - 60;

        if (is_array($response) && isset($response['ep:services'])) {
            foreach ($response['ep:services'] as $service) {
                foreach ($service['#']['ep:sessions'] as $session) {
                    $session = $session['#'];

                    $meetingkey = $session['ep:sessionKey'][0]['#'];
                    if ($meetingrecord = $DB->get_record('webexactivity', array('meetingkey' => $meetingkey))) {
                        if ($meetingrecord->status !== self::WEBEXACTIVITY_STATUS_IN_PROGRESS) {
                            $meeting = meeting::load($meetingrecord);

                            $meeting->status = self::WEBEXACTIVITY_STATUS_IN_PROGRESS;
                            $meeting->laststatuscheck = $processtime;
                            $meeting->save();
                        }
                    }
                }
            }
        }

        $select = 'laststatuscheck < ? AND status = ?';
        $params = array('lasttime' => $cleartime, 'status' => self::WEBEXACTIVITY_STATUS_IN_PROGRESS);

        if ($meetings = $DB->get_records_select('webexactivity', $select, $params)) {
            foreach ($meetings as $meetingrecord) {
                $meeting = meeting::load($meetingrecord);

                $meeting->status = self::WEBEXACTIVITY_STATUS_STOPPED;
                $meeting->laststatuscheck = $processtime;
                $meeting->save();
            }
        }
    }

    // ---------------------------------------------------
    // Redirect methods.
    // ---------------------------------------------------
    /**
     * Stores the passed URL and redirects to user edit form.
     *
     * @param moodle_url   $url URL object to return to when done.
     */
    public static function password_redirect($url = false) {
        global $SESSION;

        if (!$url) {
            $url = new \moodle_url('/');
        }

        $SESSION->mod_webexactivity_password_redirect = $url;

        $redirurl = new \moodle_url('/mod/webexactivity/useredit.php', array('action' => 'useredit'));
        redirect($redirurl);
    }

    /**
     * Redirects back to the url stored in the session.
     *
     * @param bool   $home If true, send to / instead of wherever we were.
     */
    public static function password_return_redirect($home = false) {
        global $SESSION;

        $url = false;
        if (isset($SESSION->mod_webexactivity_password_redirect)) {
            $url = $SESSION->mod_webexactivity_password_redirect;
            unset($SESSION->mod_webexactivity_password_redirect);
        }

        if (!$url or $home) {
            $url = new \moodle_url('/');
        }

        redirect($url);
    }

    // ---------------------------------------------------
    // Recording Functions.
    // ---------------------------------------------------
    /**
     * Check and update recordings from WebEx.
     *
     * @return bool  True on success, false on failure.
     */
    public function update_recordings() {
        $params = new \stdClass();
        $params->startdate = time() - (365 * 24 * 3600);
        $params->enddate = time() + (12 * 3600);

        $xml = type\base\xml_gen::list_recordings($params);

        if (!($response = $this->get_response($xml))) {
            return false;
        }

        return $this->proccess_recording_response($response);
    }

    /**
     * Process the response of recordings from WebEx.
     *
     * @param array  The response array from WebEx.
     * @return bool  True on success, false on failure.
     */
    public function proccess_recording_response($response) {
        global $DB;

        if (!is_array($response)) {
            return true;
        }

        $recordings = $response['ep:recording'];

        $processall = (boolean)\get_config('webexactivity', 'manageallrecordings');

        foreach ($recordings as $recording) {
            $recording = $recording['#'];

            if (!isset($recording['ep:sessionKey'][0]['#'])) {
                continue;
            }

            $key = $recording['ep:sessionKey'][0]['#'];
            $meeting = $DB->get_record('webexactivity', array('meetingkey' => $key));
            if (!$meeting && !$processall) {
                continue;
            }

            $rec = new \stdClass();
            if ($meeting) {
                $rec->webexid = $meeting->id;
            } else {
                $rec->webexid = null;
            }

            // TODO Convert to use object?
            $rec->meetingkey = $key;
            $rec->recordingid = $recording['ep:recordingID'][0]['#'];
            $rec->hostid = $recording['ep:hostWebExID'][0]['#'];
            $rec->name = $recording['ep:name'][0]['#'];
            $rec->timecreated = strtotime($recording['ep:createTime'][0]['#']);
            $rec->streamurl = $recording['ep:streamURL'][0]['#'];
            $rec->fileurl = $recording['ep:fileURL'][0]['#'];
            $size = $recording['ep:size'][0]['#'];
            $size = floatval($size);
            $size = $size * 1024 * 1024;
            $rec->filesize = (int)$size;
            $rec->duration = $recording['ep:duration'][0]['#'];
            $rec->timemodified = time();

            if ($existing = $DB->get_record('webexactivity_recording', array('recordingid' => $rec->recordingid))) {
                $update = new \stdClass();
                $update->id = $existing->id;
                $update->name = $rec->name;
                $update->streamurl = $rec->streamurl;
                $update->fileurl = $rec->fileurl;
                $update->timemodified = time();

                $DB->update_record('webexactivity_recording', $update);
            } else {
                $rec->id = $DB->insert_record('webexactivity_recording', $rec);

                if ($meeting) {
                    $cm = get_coursemodule_from_instance('webexactivity', $meeting->id);
                    $context = \context_module::instance($cm->id);
                    $params = array(
                        'context' => $context,
                        'objectid' => $rec->id
                    );
                    $event = \mod_webexactivity\event\recording_created::create($params);
                    $event->add_record_snapshot('webexactivity_recording', $rec);
                    $event->add_record_snapshot('webexactivity', $meeting);
                    $event->trigger();
                }

            }
        }

        return true;
    }

    /**
     * Delete 'deleted' recordings from the WebEx server.
     */
    public function remove_deleted_recordings() {
        global $DB;

        $holdtime = get_config('webexactivity', 'recordingtrashtime');

        $params = array('time' => (time() - ($holdtime * 3600)));
        $rs = $DB->get_recordset_select('webexactivity_recording', 'deleted > 0 AND deleted < :time', $params);

        foreach ($rs as $record) {
            $recording = new recording($record);
            print 'Deleting: '.$recording->name;
            try {
                $recording->true_delete();
                print "\n";
            } catch (\Exception $e) {
                print " : Exception Error\n";
            }
        }

        $rs->close();
    }


    // ---------------------------------------------------
    // Connection Functions.
    // ---------------------------------------------------
    /**
     * Get the response from WebEx for a XML message.
     *
     * @param string         $xml The XML to send to WebEx.
     * @param user|bool      $webexuser The WebEx user to use for auth. False to use the API user.
     * @return array|bool    XML response (as array). False on failure.
     * @throws webex_xml_exception on XML parse error.
     */
    public function get_response($basexml, $webexuser = false) {
        global $USER;

        $xml = type\base\xml_gen::auth_wrap($basexml, $webexuser);

        list($status, $response, $errors) = $this->fetch_response($xml);

        if ($status) {
            return $response;
        } else {
            // Bad user password, reset it and try again.
            if ($webexuser && (isset($errors['exception'])) && ($errors['exception'] === '030002')) {
                if ($webexuser->update_password(self::generate_password())) {
                    $xml = type\base\xml_gen::auth_wrap($basexml, $webexuser);
                    list($status, $response, $errors) = $this->fetch_response($xml);
                    if ($status) {
                        return $response;
                    }
                }

                throw new exception\bad_password_exception();
            }

            if ((isset($errors['exception'])) && ($errors['exception'] === '000015')) {
                // No records found (000015), which is not really a failure, return empty array.
                return array();
            }

            if ((isset($errors['exception'])) && ($errors['exception'] === '030001')) {
                // No user found (030001), which is not really a failure, return empty array.
                return array();
            }

            if ((isset($errors['exception'])) && (($errors['exception'] === '030004') || ($errors['exception'] === '030005'))) {
                // Username or email already exists.
                throw new exception\webex_xml_exception($errors['exception'], $errors['message'], $xml);
            }

            throw new exception\webex_xml_exception($errors['exception'], $errors['message'], $xml);
        }
    }

    /**
     * Connects to WebEx and gets a response for the given, full, XML.
     *
     * To be used by get_response().
     *
     * @param string  $xml The XML message to retrieve.
     * @return array  status bool    True on success, false on failure.
     *                response array The XML response in array form.
     *                errors array   An array of errors.
     */
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
            $response = false;
        }
        $errors = $connector->get_errors();
        $this->latesterrors = $errors;

        return array($status, $response, $errors);
    }

    /**
     * Expose latesterrors to the outside world for use.
     *
     * @return array  The latest errors.
     */
    public function get_latest_errors() {
        return $this->latesterrors;
    }
}
