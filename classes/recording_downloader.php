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
 * A class for downloading a recording internally.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity;

use \mod_webexactivity\local\type;
use \mod_webexactivity\local\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * A class for downloading a recording internally.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_downloader {


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








    /**
     * Fetch the response for the provided XML.
     *
     * @param string        $url The url to access.
     * @return string|bool  String on success, false on failure.
     * @throws curl_setup_exception on curl setup failure.
     * @throws connection_exception on connection failure.
     */
    public function retrieve($url) {
        $handle = $this->create_curl_handle($url);

        if (!$handle) {
            throw new exception\curl_setup_exception();
        }

        $response = curl_exec($handle);

        if ($response === false) {
            $error = curl_errno($handle) .':'. curl_error($handle);
            throw new exception\connection_exception($error);
        }
        curl_close($handle);

        return $response;
    }



    /**
     * Setup a new curl handle for use.
     *
     * @return object    The configured curl handle.
     */
    private function create_curl_handle($url) {


        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_TIMEOUT, 120);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_USERAGENT, 'Moodle');
        curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

        return $handle;
    }

CURLOPT_HEADER

}
