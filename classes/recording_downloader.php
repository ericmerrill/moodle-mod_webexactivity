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

require_once($CFG->libdir . '/filelib.php');

// use \mod_webexactivity\local\type;
// use \mod_webexactivity\local\exception;
use curl;


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


    public function get_recording() {
        $fileurl = $this->recording->fileurl;

        $trueurl = $this->get_download_url($fileurl);
    }

    protected function get_download_url($fileurl) {
        $curl = new curl();

        $response = $curl->get($fileurl);

        if (empty($response)) {
            return false;
        }

        if (empty($firsturl = $this->get_download_step_1_url($response))) {
            return false;
        }

        //var_dump($firsturl);
        $response = $curl->get($firsturl);

        if (empty($response)) {
            return false;
        }

        var_dump($response);


//         if (preg_match('/function\s*func_prepare\s*\([a-zA-Z ,]*\)\s*{([\S\s]*?)}/im', $response, $matches) == 0) {
//             return false;
//         }
//
//         $prepfunc = $matches[1];

        // var_dump($downloadfunc);
//         var_dump($prepfunc);
    }


    protected function get_download_step_1_url(string $page) {


        $matches = [];
        if (preg_match('/function\s*download\s*\(\s*\)\s*{([\S\s]*?)}/im', $page, $matches) == 0) {
            return false;
        }
        $downloadfunc = $matches[1];

        // Get all variable lines out of it.
        $params = [];
        preg_match_all('/var\s*([a-z0-9_$]*)\s*=\s*([\'"]?)(.*)\g2\;/im', $downloadfunc, $matches);
        foreach ($matches[1] as $key => $name) {
            $params[$name] = $matches[3][$key];
        }

        // Get the URL line, it is a bit special;
        if (preg_match('/var\s*url\s*=\s*(["\'][^\n]*)\;/im', $downloadfunc, $matches) == 0) {
            return false;
        }
        $baseurl = $matches[1];

        $resulturl = $this->parse_js_string($baseurl, $params);

        return $resulturl;
    }

    protected function get_prepare_statement(string $page) {

    }

    protected function parse_js_string(string $input, array $params = []) {
        // "https://oakland.webex.com/mw3300/mywebex/nbrPrepare.do?siteurl=oakland" + "&recordid=" + recordId+"&prepareTicket=" + prepareTicket
        $output = "";
        $matches = [];

        $instring = false;

        while (!empty($input)) {
            if ($instring) {
                $curr = substr($input, 0, 1);
                $input = substr($input, 1);

                if ($curr == $instring) {
                    $instring = false;
                    continue;
                }

                if ($curr == '\\') {
                    $curr = substr($input, 0, 1);
                    $input = substr($input, 1);
                }

                $output .= $curr;

                continue;
            }

            // This means we are parsing outside a string;
            $input = trim($input, " \t\n\r\0\x0B+");
            $curr = substr($input, 0, 1);
            if ($curr == "'" || $curr == '"') {
                // Start of a string.
                $instring = $curr;
                $input = substr($input, 1);
                continue;
            }

            if (preg_match('/([a-z0-9_$]*)/i', $input, $matches)) {
                $varname = $matches[1];
                $input = substr($input, strlen($varname));

                if (isset($params[$varname])) {
                    $output .= $params[$varname];
                } else {
                    debugging("Could not find $varname");
                }
                continue;
            }

            debugging("Unknown char found $curr");
            $input = substr($input, 1);
        }

        return $output;
//
//         while (false) {
//             $input = trim($input, " \t\n\r\0\x0B+");
//
//             $firstchar = substr($input, 0, 1);
//             if ($firstchar == "'" || $firstchar == '"') {
//                 // This means we are parsing a string.
//
//                 // An escaped quite character in the source string will totally break this...
//                 preg_match('/(["\'])(.*?)(\g1)/im', $input, $matches);
//
//                 $string = $matches[2];
//
//                 $output .= $string;
//
//                 $input = substr($input, strlen($input) + 2);
//             } else {
//
//             }
//         }
//         //$input = str_replace(' ', '', $input);
//         //$parts = explode('+', $input)
//
//         return $output;
    }

    /**
     * Fetch the response for the provided XML.
     *
     * @param string        $url The url to access.
     * @return string|bool  String on success, false on failure.
     * @throws curl_setup_exception on curl setup failure.
     * @throws connection_exception on connection failure.
     */
//     public function retrieve($url) {
//         $handle = $this->create_curl_handle($url);
//
//         if (!$handle) {
//             throw new exception\curl_setup_exception();
//         }
//
//         $response = curl_exec($handle);
//
//         if ($response === false) {
//             $error = curl_errno($handle) .':'. curl_error($handle);
//             throw new exception\connection_exception($error);
//         }
//         curl_close($handle);
//
//         return $response;
//     }



    /**
     * Setup a new curl handle for use.
     *
     * @return object    The configured curl handle.
     */
//     private function create_curl_handle($url) {
//
//
//         $handle = curl_init($url);
//         curl_setopt($handle, CURLOPT_TIMEOUT, 120);
//         curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($handle, CURLOPT_POST, true);
//         curl_setopt($handle, CURLOPT_USERAGENT, 'Moodle');
//         curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
//         curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
//         curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
//
//         return $handle;
//     }



}
