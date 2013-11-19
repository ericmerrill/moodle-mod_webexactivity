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

require_once($CFG->libdir.'/xmlize.php');

class service_connector {
    private $success = null;
    private $error = array();
    private $response = null;

    public function __construct() {

    }

    public function retrieve($xml) {
        $this->clear_status();
        $handle = $this->create_curl_handle();

        if (!$handle) {
            $this->sucess = false;
            $this->error['message'] = 'Bad curl setup';
            return false;
        }

        curl_setopt($handle, CURLOPT_POSTFIELDS, $xml);

        $this->response = curl_exec($handle);

        if ($this->response === false) {
            $this->error[] = curl_errno($handle) .':'. curl_error($handle);
            return false;
        }
        curl_close($handle);

        $this->update_success();

        return $this->success;
    }

    private function clear_status() {
        $this->success = null;
        $this->error = array();
        $this->response = null;
    }

    private function create_curl_handle() {
        $url = get_config('webexactivity', 'url');
        if ($url === false) {
            return false;
        }
        $url = 'https://'.$url.'.webex.com';

        $handle = curl_init($url.'/WBXService/XMLService');
        curl_setopt($handle, CURLOPT_TIMEOUT, 120);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_USERAGENT, 'Moodle');
        curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

        return $handle;
    }

    private function update_success() {
        if ($this->response === null || $this->response === false) {
            $this->success = false;
            return;
        }

        $response = xmlize($this->response);

        if (!isset($response['serv:message']['#']['serv:header'][0]['#']['serv:response'][0]['#'])) {
            $this->success = false;
            return;
        }

        $response = $response['serv:message']['#']['serv:header'][0]['#']['serv:response'][0]['#'];

        if (!isset($response['serv:result'][0]['#'])) {
            $this->success = false;
            return;
        }

        $success = $response['serv:result'][0]['#'];
        if (strcmp($success, 'SUCCESS') === 0) {
            $this->success = true;
        } else {
            $this->success = false;
            if (isset($response['serv:reason'][0]['#'])) {
                $this->error['message'] = $response['serv:reason'][0]['#'];
            }
            if (isset($response['serv:exceptionID'][0]['#'])) {
                $this->error['exception'] = $response['serv:exceptionID'][0]['#'];
            }
        }
    }

    public function get_errors() {
        return $this->error;
    }

    public function get_success() {
        return $this->success;
    }

    public function get_response() {
        return $this->response;
    }

    public function get_response_array() {
        return xmlize($this->response);
    }
}
