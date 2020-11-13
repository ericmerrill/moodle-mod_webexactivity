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
use mod_webexactivity\recording;
use curl;
use stdClass;
use core\lock\lock_config;
use mod_webexactivity\local\exception;


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

    /**
     *
     */
    const DOWNLOAD_STATUS_PENDING = 0;

    /**
     *
     */
    const DOWNLOAD_STATUS_INPROGRESS = 1;

    /**
     *
     */
    const DOWNLOAD_STATUS_COMPLETE = 2;

    /**
     *
     */
    const DOWNLOAD_STATUS_ERROR = -1;

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


    public function download_recording($force = null, $deleteremote = null) {
        \core_php_time_limit::raise();

        if (is_null($force)) {
            // TODO - setting;
            $force = true;
        }

        $lock = $this->get_lock();

        if (empty($lock)) {
            $this->log("Could not get recording lock.");
            return false;
        }

        // Now that we have a lock, get an updated copy of the recording.
        $this->recording = new recording($this->recording->id);

        if ($this->get_status() == self::DOWNLOAD_STATUS_COMPLETE && !$force) {
            if (!$fs->is_area_empty($context->id, 'mod_webexactivity', 'recordings', $this->recording->id)) {
                $this->log("This recording was already downloaded. Skipping.");
                $lock->release();
                return true;
            }
        }

        if ($this->get_status() == self::DOWNLOAD_STATUS_INPROGRESS) {
            $this->log("Download is already in progress.");
            $lock->release();
            return false;
        }

        // Now show that we are in progress, and release the lock.
        $this->set_status(self::DOWNLOAD_STATUS_INPROGRESS);
        $lock->release();

        if (is_null($deleteremote)) {
            $deleteremote = get_config('webexactivity', 'deletedownloadrecordings');;
        }

        // Get the context for this recording.
        $context = $this->recording->get_context();

        // Check if we have already downloaded this recording.
        $fs = get_file_storage();
        $exists = false;
        if (!$fs->is_area_empty($context->id, 'mod_webexactivity', 'recordings', $this->recording->id)) {
            $exists = true;
        }

        if (empty($this->recording->fileurl)) {
            // We don't have a url to download. Abort.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Recording has no fileurl. Aborting.");
            return false;
        }

        if (!($downloadurl = $this->get_download_url($this->recording->fileurl))) {
            // Don't seem to have a download URL.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Failure getting download URL.");
            return false;
        }

        // Download the recording details.
        if (!$details = $this->get_recording_detail()) {
            // Failed to get the details.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Failure getting recording details.");
            return false;
        }

        $this->recording->downloaddetails = $details;

        $filerecord = new stdClass();
        $filerecord->contextid = $context->id;
        $filerecord->component = 'mod_webexactivity';
        $filerecord->filearea = 'recordings';
        $filerecord->itemid = $this->recording->id;
        $filerecord->filepath = '/';

        $dir = make_request_directory();

        $tmpfile = $dir.'/tmp';

        $this->log('Downloading ~' . display_size($this->recording->filesize) . ' into '.$tmpfile);
        $response = download_file_content($downloadurl, null, null, true, 3000, 20, false, $tmpfile);

        if (empty($response) || !empty($response->error)) {
            // Download error of some form.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Error during download.");
            $this->log(var_export($response, false));

            @unlink($tmpfile);
            return false;
        }

        if ($response->status == '400') {
            // Sometimes we get a 400 error of unknown reasons. But just trying again later will clear it up.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Error during download.");
            $this->log(var_export($response, false));

            @unlink($tmpfile);
            // Throw exception to reenrol adhoc task for later processing.
            throw new exception\webexactivity_exception('errordownloadingrecording');
        }

        $downloadedsize = filesize($tmpfile);
        $diff = $downloadedsize - $this->recording->filesize;
        $this->log('Downloaded ~' . display_size($downloadedsize));
        $this->log('Difference ' . $diff . ' bytes');

        if ($diff < -20000) {
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("File downloaded was too small.");
            $this->log(var_export($response, false));

            @unlink($tmpfile);
            return false;
        }

        // Lets find the filename.
        $matches = [];
        $filename = false;
        foreach ($response->headers as $header) {
            if (preg_match('/Content-Disposition:.*?filename="(.*?)"/im', $header, $matches)) {
                $filename = basename($matches[1]);
                $filename = clean_param($filename, PARAM_FILE);
                break;
            }
        }

        if (empty($filename)) {
            // Couldn't determine the filename to download.
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Couldn't determine downloads filename.");
            $this->log(var_export($response, false));

            @unlink($tmpfile);
            return false;
        }

        $filerecord->filename = $filename;

        if ($exists) {
            // Delete the existing files.
            $fs->delete_area_files($context->id, 'mod_webexactivity', 'recordings', $this->recording->id);
        }

        // Convert the downloaded temp file.
        try {
            $newfile = $fs->create_file_from_pathname($filerecord, $tmpfile);
            @unlink($tmpfile);
        } catch (Exception $e) {
            $this->set_status(self::DOWNLOAD_STATUS_ERROR);
            $this->set_error("Error when attempting to create file.");

            @unlink($tmpfile);
            //throw $e;
            return false;
        }

        if (empty($this->recording->fileurl)) {
            $this->recording->filestatus = recording::FILE_STATUS_INTERNAL;
        } else {
            $this->recording->filestatus = recording::FILE_STATUS_INTERNAL_AND_WEBEX;
        }
        // Generate a uniqueid if the recording doesn't already have one.
        if (empty($this->recording->uniqueid)) {
            $this->recording->uniqueid = self::generate_unique_id();
        }

        unset($this->recording->downloaderror);

        $this->set_status(self::DOWNLOAD_STATUS_COMPLETE);

        $this->recording->save();

        $params = [
            'context' => $this->recording->get_context(),
            'objectid' => $this->recording->id
        ];
        $event = event\recording_made_internal::create($params);
        $event->add_record_snapshot('webexactivity_recording', $this->recording->record);
        $event->trigger();

        if ($deleteremote) {
            $this->recording->delete_remote_recording();
        }
    }

    protected function get_download_url($fileurl) {
        $curl = new curl();

        $mainpage = $curl->get($fileurl);

        if (empty($mainpage)) {
            return false;
        }

        $downloadurl = $this->process_prepare_url($mainpage);

        if (empty($downloadurl)) {
            // Couldn't get the download URL.
            return false;
        }

        return $downloadurl;
    }


    protected function get_download_step_1_url(string $page) {

        $matches = [];
        if (!preg_match('/function\s*download\s*\(\s*\)\s*{([\S\s]*?)}/im', $page, $matches)) {
            return false;
        }
        $downloadfunc = $matches[1];

        $parts = $this->get_js_definitions($downloadfunc);

        if (empty($parts) || empty($parts['url'])) {
            return false;
        }

        return $parts['url'];
    }

    protected function process_prepare_url($mainpage) {
        if (empty($pageurl = $this->get_download_step_1_url($mainpage))) {
            return false;
        }

        $count = 0;
        while (true) {
            $count++;
            $curl = new curl();

            if (empty($statuspage = $curl->get($pageurl))) {
                return false;
            }

            $prepareparams = $this->get_prepare_statement($statuspage);
            $prepareparams = $this->process_prepare_statement($mainpage, $prepareparams);

            if (!empty($prepareparams['downloadUrl'])) {
                return $prepareparams['downloadUrl'];
            } else if (!empty($prepareparams['temUrl'])) {
                if ($count > 10) {
                    // Took too many tries to fetch.
                    return false;
                }
                $pageurl = $prepareparams['temUrl'];
                // Sleep for 3 seconds before trying again.
                sleep(3);
                continue;
            }

            // If we got down here, then something went wrong.
            return false;
        }
    }

    protected function get_prepare_statement(string $page) {
        $matches = [];

        $pattern = '/func_prepare\(\s*([\'"]?)(.*?)\g1\s*,\s*([\'"]?)(.*?)\g3\s*,\s*([\'"]?)(.*?)\g5\s*\)/im';
        if (!preg_match($pattern, $page, $matches)) {
            return false;
        }

        $return['status'] = $matches[2];
        $return['url'] = $matches[4];
        $return['ticket'] = $matches[6];

        return $return;
    }

    protected function process_prepare_statement($page, $params) {
        $status = $params['status'];

        $matches = [];
        if (!preg_match('/function\s*func_prepare\s*\(.*?\)\s*{([\S\s]*?)}/im', $page, $matches)) {
            return false;
        }
        $preparefunc = $matches[1];

        if (!preg_match('/case\s*([\'"])' . $status . '\g1\s*:([\s\S]*?)break;/im', $preparefunc, $matches)) {
            return false;
        }
        $prepstatement = trim($matches[2]);

        $params = $this->get_js_definitions($prepstatement, $params);

        if ($status == 'Preparing' && isset($params['temUrl']) && isset($params['url'])) {
            $params['temUrl'] = $params['temUrl'] . $params['url'];
        } else if ($status != 'OKOK') {
            return false;
        }

        return $params;
    }

    protected function get_js_definitions($input, $params = []) {
        $matches = [];
        preg_match_all('/var\s*([a-z0-9_$]*)\s*=\s*(([\'"]?).*\g3)\;/im', $input, $matches);
        foreach ($matches[1] as $key => $name) {
            $params[$name] = $this->parse_js_string($matches[2][$key], $params);
        }

        return $params;
    }

    protected function parse_js_string(string $input, array $params = []) {
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

            if (preg_match('/([a-z_][a-z0-9_$\.]*\(.*?\))/i', $input, $matches)) {
                $funcname = $matches[1];
                $input = substr($funcname, strlen($funcname));

                $output .= $funcname;
                continue;
            } else if (preg_match('/([a-z_$][a-z0-9_$]*)/i', $input, $matches)) {
                $varname = $matches[1];
                $input = substr($input, strlen($varname));

                if (isset($params[$varname])) {
                    $output .= $params[$varname];
                } else {
                    $this->log("Could not find $varname");
                }
                continue;
            } else if (preg_match('/([0-9]*)/i', $input, $matches)) {
                $number = $matches[1];
                $input = substr($input, strlen($number));

                $output .= $number;
                continue;
            }

            $this->log("Unknown char found $curr");
            $input = substr($input, 1);
        }

        return $output;

    }

    protected function get_recording_detail() {
        $webex = new webex();

        $xml = local\type\base\xml_gen::recording_detail($this->recording->recordingid);
        $response = $webex->get_response($xml);

        if (!$response || !isset($response['ep:recording'][0]['#'])) {
            return false;
        }

        $base = $response['ep:recording'][0]['#'];

        return $base;
    }

//     protected function recursive_info($input) {
//         if (!is_array($input)) {
//             return $input;
//         }
//
//
//         $output = [];
//         foreach ($input as $key => $value) {
//             if (is_array($value) && count($value) == 0) {
//                 $output[$key] = [];
//             } else if (is_array($value) && count($value) == 1) {
//                 $output[$key] = $this->recursive_info($value);
//             } else {
//                 $output[$key] = $value;
//             }
//         }
//
//         return $output;
//     }

    public function log($msg) {
        if (defined('CLI_SCRIPT')) {
            mtrace('Recording ' . $this->recording->id . ': ' . $msg);
        }
    }

    public function set_status($status) {
        $this->recording->downloadstatus = $status;
        $this->recording->save_to_db();
    }

    public function get_status() {
        if (isset($this->recording->downloadstatus)) {
            return $this->recording->downloadstatus;
        } else {
            return self::DOWNLOAD_STATUS_PENDING;
        }
    }

    public function set_error($msg) {
        $this->log($msg);
        $this->recording->downloaderror = $msg;
        $this->recording->save_to_db();
    }

    public function get_lock() {
        $factory = lock_config::get_lock_factory('enrol_lmb');

        $resource = 'downloadrecording'.$this->recording->id;
        return $factory->get_lock($resource, 10, 100);
    }

    public static function generate_unique_id($len = 8) {
        global $DB;
        $chars = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

        while (true) {
            $output = '';
            for ($i = 0; $i < $len; $i++) {
                $index = rand(0, strlen($chars) - 1);
                $output .= $chars[$index];
            }

            if (!$DB->record_exists('webexactivity_recording', ['uniqueid' => $output])) {
                return $output;
            }
        }
    }
}
