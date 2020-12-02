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

use mod_webexactivity\local\type;
use mod_webexactivity\local\exception;
use mod_webexactivity\task\download_recording;
use mod_webexactivity\task\delete_remote_recording;
use context_system;
use context_module;

defined('MOODLE_INTERNAL') || die();

/**
 * A class that represents a WebEx recording.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording {
    /**
     *
     */
    const FILE_STATUS_WEBEX = 0;

    /**
     *
     */
    const FILE_STATUS_INTERNAL_AND_WEBEX = 1;

    /**
     *
     */
    const FILE_STATUS_INTERNAL = 2;

    /**
     *
     */
    const FILE_STATUS_NONE = 3;

    const ASSOC_NONE = 0;
    const ASSOC_LOCAL = 1;
    const ASSOC_REMOTE = 2;

    /** @var array Array of keys that go in the database object */
    protected $dbkeys = ['id',
                         'webexid',
                         'meetingkey',
                         'recordingid',
                         'hostid',
                         'name',
                         'timecreated',
                         'streamurl',
                         'fileurl',
                         'filesize',
                         'duration',
                         'visible',
                         'deleted',
                         'filestatus',
                         'uniqueid',
                         'additional',
                         'timemodified'];

    /** @var object Object that contains additional data about the object. This will be JSON encoded. */
    protected $additionaldata;

    /** @var stdClass The database record this object represents. */
    private $recording = null;

    /** @var bool Track if there is a change that needs to go to WebEx. */
    private $webexchange = false;

    private $context = false;

    private $file = null;

    /**
     * Builds the recording object.
     *
     * @param stdClass|int    $recording Object of recording record, or id of record to load.
     * @throws coding_exception when bad parameter received.
     */
    public function __construct($recording = null) {
        global $DB;

        if (is_null($recording)) {
            $this->recording = new \stdClass();
        } else if (is_object($recording)) {
            $this->recording = $recording;
        } else if (is_numeric($recording)) {
            $this->recording = $DB->get_record('webexactivity_recording', array('id' => $recording));
        }

        if (!$this->recording) {
            throw new \coding_exception('Unexpected parameter type passed to recording constructor.');
        }

        if (empty($this->recording->additional)) {
            $this->additionaldata = new \stdClass();
        } else {
            $this->additionaldata = json_decode($this->recording->additional);
        }

    }

    /**
     * Get the context for this recording.
     *
     * @return object    The context that relates to this recording.
     */
    public function get_context() {
        if ($this->context !== false) {
            return $this->context;
        }

        // Get the context for this recording.
        if (empty($this->webexid)) {
            // This is a recording with no internal meeting.
            $context = context_system::instance();
        } else {
            list($course, $cm) = get_course_and_cm_from_instance($this->webexid, 'webexactivity');
            $context = context_module::instance($cm->id);
        }

        $this->context = $context;

        return $context;
    }

    /**
     * Mark this recording for deletion.
     *
     * @return bool    True on success, false on failure.
     */
    public function delete() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->id;
        $update->deleted = time();
        return $DB->update_record('webexactivity_recording', $update);
    }

    /**
     * Un-delete this recording.
     *
     * @return bool    True on success, false on failure.
     */
    public function undelete() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->id;
        $update->deleted = 0;
        return $DB->update_record('webexactivity_recording', $update);
    }

    /**
     * Delete this recording from WebEx.
     *
     * @param bool      $remote Delete the remote file as well.
     * @return bool     True on success, false on failure.
     * @throws webexactivity_exception on error.
     */
    public function true_delete($remote = true) {
        global $DB;

        if ($remote) {
            $this->delete_remote_recording();
        }

        $this->delete_internal_recording();

        $DB->delete_records('webexactivity_recording', array('id' => $this->__get('id')));

        return true;
    }

    /**
     * Check if the recording has an internal file associated with it.
     *
     * @param bool      $verify If true, then check that the file exists in the filestore.
     * @return bool     True on if yes, false if no.
     */
    public function has_internal_file($verify = false) {
        if ($this->filestatus != self::FILE_STATUS_INTERNAL && $this->filestatus != self::FILE_STATUS_INTERNAL_AND_WEBEX) {
            return false;
        }

        if ($verify) {
            $file = $this->get_internal_file();

            if (empty($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the actual internal file for this recording from the filestore.
     *
     * @return stored_file|false    File on success, false on failure.
     */
    public function get_internal_file() {
        if (!is_null($this->file)) {
            return $this->file;
        }

        $context = $this->get_context();

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_webexactivity', 'recordings', $this->id, 'sortorder DESC, id ASC', false);
        $file = reset($files);

        if (empty($file)) {
            return false;
        }

        $this->file = $file;

        return $file;
    }

    /**
     * Get the URL to the access page of this recording.
     *
     * @param bool      $download If true, create link specifying download.
     * @param bool      $stream If true, create link specifying streaming.
     * @return string   The URL.
     */
    public function get_recording_url($download = false, $stream = false) {
        $forcedownload = true;

        $args = '';
        if ($download) {
            $args .= '/download';
        }
        if ($stream) {
            $args .= '/stream';
        }

        $url = new \moodle_url('/mod/webexactivity/rec.php');
        $url->set_slashargument('/'.$this->uniqueid.$args);
        return $url->out(false);
    }

    public function has_external_file() {
        if ($this->filestatus != self::FILE_STATUS_WEBEX && $this->filestatus != self::FILE_STATUS_INTERNAL_AND_WEBEX) {
            return false;
        }

        if (empty($this->fileurl)) {
            return false;
        }

        return true;
    }

    public function is_downloadable() {
        if ($this->has_internal_file(true)) {
            return true;
        }
        if (!empty($this->fileurl)) {
            return true;
        }

        return false;
    }

    public function is_streamable() {
        global $PAGE;
        if (!empty($this->streamurl)) {
            return true;
        }

        $mediamanager = \core_media_manager::instance($PAGE);


        if ($mediamanager->can_embed_url($this->get_true_fileurl(false, false))) {
            return true;
        }

        return false;
    }

    public function get_true_fileurl($forcedownload = true, $asstring = true) {
        global $CFG;

        if (!$this->has_internal_file(true)) {
            return false;
        }

        $args = '';
        if ($forcedownload) {
            $args .= '/download';
        }

        $file = $this->get_internal_file();

        $url = new \moodle_url('/mod/webexactivity/rec.php');
        // We need to end it in the filename for certain things.
        $url->set_slashargument('/'.$this->uniqueid.$args.'/file/'.$file->get_filename());
        if ($asstring) {
            return $url->out(false);
        } else {
            return $url;
        }
    }

    public function get_stream_url() {
        return $this->streamurl;
    }

    /**
     * Delete the internal recording file.
     *
     * @return bool    True on success
     */
    public function delete_internal_recording() {
        $fs = get_file_storage();
        return $fs->delete_area_files($this->get_context()->id, 'mod_webexactivity', 'recordings', $this->id);
    }

    /**
     * Delete this recording from WebEx, but leave the internal version.
     *
     * @return bool    True on success, false on failure.
     * @throws webexactivity_exception on error.
     */
    public function delete_remote_recording() {

        $xml = type\base\xml_gen::delete_recording($this->__get('recordingid'));

        $webex = new webex();
        $response = $webex->get_response($xml);

        if ($response === false) {
            throw new exception\webexactivity_exception('errordeletingrecording');
        }

        $params = [
            'context' => $this->get_context(),
            'objectid' => $this->id
        ];
        $event = event\recording_remote_deleted::create($params);
        $event->add_record_snapshot('webexactivity_recording', $this->record);
        $event->trigger();

        if ($this->filestatus == self::FILE_STATUS_INTERNAL_AND_WEBEX) {
            $this->filestatus = self::FILE_STATUS_INTERNAL;
        } else if ($this->filestatus == self::FILE_STATUS_WEBEX) {
            $this->filestatus = self::FILE_STATUS_NONE;
        }

        if (!empty($this->fileurl)) {
            $this->oldfileurl = $this->fileurl;
        }
        if (!empty($this->streamurl)) {
            $this->oldstreamurl = $this->streamurl;
        }

        $this->fileurl = null;
        $this->streamurl = null;
        $this->save_to_db();

        return true;
    }

    /**
     * Save the recording to WebEx and Moodle as needed.
     *
     * @return bool    True on success, false on failure/error.
     */
    public function save() {
        if ($this->webexchange) {
            if (!$this->save_to_webex()) {
                return false;
            }
        }
        return $this->save_to_db();
    }

    /**
     * Save the recording to the Moodle database.
     *
     * @return bool    True on success, false on failure/error.
     */
    public function save_to_db() {
        global $DB;

        $this->recording->timemodified = time();

        if (isset($this->recording->id)) {
            if ($DB->update_record('webexactivity_recording', $this->recording)) {
                return true;
            }
            return false;
        } else {
            if ($id = $DB->insert_record('webexactivity_recording', $this->recording)) {
                $this->recording->id = $id;
                return true;
            }
            return false;
        }
    }

    /**
     * Save this recording object into WebEx.
     *
     * @return bool    True on success, false on failure.
     */
    public function save_to_webex() {
        if (!$this->has_external_file()) {
            // There is no longer a remote recording. It is now internal, so we don't need to save changes to webex.
            $this->webexchange = false;
            return true;
        }

        $params = new \stdClass;
        $params->recordingid = $this->__get('recordingid');
        $params->name = $this->recording->name;

        $xml = type\base\xml_gen::update_recording($params);

        $webex = new webex();
        $response = $webex->get_response($xml);

        if ($response) {
            $this->webexchange = false;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create download adhoc task for this recording.
     *
     * @param bool|null     $force  Redownload if the recording is already present. Use default if null.
     * @param bool|null     $deleteremote  Delete remote if download succeeds. Use default if null.
     */
    public function create_download_task($force = null, $deleteremote = null) {
        $data = new \stdClass();
        $data->recordingid = $this->id;
        $data->forcedownload = $force;
        $data->deleteremote = $deleteremote;

        $task = new download_recording();
        $task->set_custom_data($data);
        $task->set_component('mod_webexactivity');

        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Create delete adhoc task for this recording.
     *
     * @param bool|null     $force  Delete even if not currently downloaded.
     */
    public function create_delete_task($force = null) {
        $data = new \stdClass();
        $data->recordingid = $this->id;
        $data->forcedelete = $force;

        $task = new delete_remote_recording();
        $task->set_custom_data($data);
        $task->set_component('mod_webexactivity');

        \core\task\manager::queue_adhoc_task($task);
    }

    public function download_recording($force = null, $deleteremote = null) {
        $downloader = new recording_downloader($this);
        $downloader->download_recording($force, $deleteremote);
    }

    protected function rename_internal_file($newname) {
        if (!$file = $this->get_internal_file()) {
            return;
        }

        // Get the extension to put on the end.
        $extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
        $newname = clean_param($newname . '.' . $extension, PARAM_FILE);

        if (strcmp($newname, $file->get_filename()) === 0) {
            return;
        }

        try {
            $file->rename($file->get_filepath(), $newname);
        } catch (\Exception $e) {
            return;
        }
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

    public function get_association() {
        global $DB;

        if (!empty($this->meetingkey) && $DB->record_exists('webexactivity', ['meetingkey' => $this->meetingkey])) {
            return self::ASSOC_LOCAL;
        }

        if (!isset($this->remoteserver)) {
            $this->update_remote_server();
        }

        if (empty($this->remoteserver)) {
            return self::ASSOC_NONE;
        }

        return self::ASSOC_REMOTE;
    }

    public function should_be_downloaded() {
        global $DB;

        $setting = get_config('webexactivity', 'downloadnewrecordings');

        if ($setting == recording_downloader::DOWNLOAD_NONE) {
            return false;
        }

        $associaton = $this->get_association();

        if ($associaton == self::ASSOC_LOCAL) {
            // This means we have a local meeting.
            // If we have a local meeting, the answer is yes for all remaining options.
            return true;
        }

        if ($setting == recording_downloader::DOWNLOAD_ASSOCIATED) {
            // This means we don't have a locally associated meeting.
            return false;
        }

        if ($setting == recording_downloader::DOWNLOAD_ALL) {
            // We didn't have a local meeting, so check for a remote one.
            if ($associaton == self::ASSOC_REMOTE) {
                // This means that it was detected on a remote server. Skip it.
                return false;
            }

            return true;
        }

        return false;
    }

    public function update_remote_server() {
        global $DB;
        if (!empty($this->meetingkey) && $DB->record_exists('webexactivity', ['meetingkey' => $this->meetingkey])) {
            unset($this->remoteserver);
            $this->save_to_db();
            return;
        }

        $server = webex::get_remote_server_for_meeting_key($this->meetingkey);
        if (!is_null($server)) {
            $this->remoteserver = $server;
            $this->save_to_db();
            return;
        }

        $this->remoteserver = false;
        $this->save_to_db();
    }

    // ---------------------------------------------------
    // Magic Methods.
    // ---------------------------------------------------

    /**
     * Magic setter method for object.
     *
     * @param string    $name The name of the value to be set.
     * @param mixed     $val  The value to be set.
     */
    public function __set($name, $val) {
        if (!in_array($name, $this->dbkeys)) {
            $this->additionaldata->$name = $val;
            $this->recording->additional = json_encode($this->additionaldata, JSON_UNESCAPED_UNICODE);
            return;
        }

        switch ($name) {
            case 'name':
                if (strcmp($val, $this->recording->name) === 0) {
                    return;
                }
                $this->renamedrecording = 1;
                $this->rename_internal_file($val);
                $this->webexchange = true;
                break;
            case 'visible':
                if ($val) {
                    $val = 1;
                } else {
                    $val = 0;
                }
                break;
            case 'record':
                debugging('Recording record can only be set at construction time');
                return;
        }

        $this->recording->$name = $val;
    }

    /**
     * Magic getter method for object.
     *
     * @param string    $name The name of the value to be retrieved.
     */
    public function __get($name) {
        if ($name == 'record') {
            return $this->recording;
        }

        if (!in_array($name, $this->dbkeys)) {
            return $this->additionaldata->$name;
        }

        switch ($name) {
            case 'visible':
                if ($this->recording->deleted > 0) {
                    return 0;
                }
                break;
        }

        return $this->recording->$name;
    }

    /**
     * Magic isset method for object.
     *
     * @param string    $name The name of the value to be checked.
     */
    public function __isset($name) {
        if (!in_array($name, $this->dbkeys)) {
            return isset($this->additionaldata->$name);
        }

        switch ($name) {
            case 'record':
                return isset($this->recording);
        }
        return isset($this->recording->$name);
    }

    /**
     * Magic unset method for object.
     *
     * @param string    $name The name of the value to be unset.
     */
    public function __unset($name) {
        if (!in_array($name, $this->dbkeys)) {
            unset($this->additionaldata->$name);
            $this->recording->additional = json_encode($this->additionaldata, JSON_UNESCAPED_UNICODE);
            return;
        }

        unset($this->recording->$name);
    }

}
