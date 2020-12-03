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
use Mustache_Engine;


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

    public function notify_if_needed($force = false) {
        if ($force) {
            $this->notify();
            return;
        }

        $recording = $this->recording;

        $config = get_config('webexactivity', 'notifydownload');

        // Notify none.
        if ($config == self::NOTIFY_NONE) {
            return;
        }

        // We only send this email once. TODO - force.
        if (!empty($recording->notified)) {
            return;
        }

        // Notify all.
        if ($config == self::NOTIFY_ALL) {
            $this->notify();
            return;
        }

        $assoc = $recording->get_association();

        // Send for locally associated recordings.
        if (($config == self::NOTIFY_ASSOCIATED) && ($assoc == recording::ASSOC_LOCAL)) {
            $this->notify();
            return;
        }

        // Send for recordings with no association.
        if (($config == self::NOTIFY_UNASSOCIATED) && ($assoc == recording::ASSOC_NONE)) {
            $this->notify();
            return;
        }

        return;
    }

    public function notify() {
        $recording = $this->recording;

        $users = $this->get_email_users();
        if (empty($users)) {
            $this->log('No email addresses found to send to.');
            return;
        }

        $subject = $this->get_email_subject();
        $body = $this->get_email_body();

        foreach ($users as $user) {
            $this->send($subject, $body, $user);
        }

        $recording->notified = true;
        $recording->save_to_db();
    }

    protected function send($subject, $body, $touser) {
        $this->log('Emailing '.$touser->email);

        $success = email_to_user(
            $touser,
            $this->get_from_user(),
            $subject,
            format_text_email($body, 1),
            purify_html($body)
        );

        return $success;
    }

    public function get_email_users() {
        global $DB;

        $hostid = $this->recording->hostid;

        // See if we already know the user.
        $webexuser = user::load_webex_id($hostid);
        if (!empty($webexuser)) {
            if (!empty($webexuser->moodleuserid)) {
                // Use the Moodle email address if it exists.
                if ($user = $DB->get_record('user', ['id' => $webexuser->moodleuserid])) {
                    return [$user];
                }
            }

            // Now use the Webex user email address if we have it.
            if (!empty($webexuser->email)) {
                return [$this->get_user_for_email($webexuser->email)];
            }
        }

        // We don't have the user's object or email address. Now we ask Webex directly.
        $user = user::search_webex_for_webexid($hostid);
        if (!empty($user) && !empty($user->email)) {
            return [$this->get_user_for_email($user->email)];
        }

        return false;
    }

    protected function get_user_for_email($email) {
        global $DB;

        if ($user->get_record('user', ['email' => $email])) {
            return $email;
        }

        // Make a fake user for emails that don't have a matching user.
        $user = new \stdClass();
        $user->id = mt_rand(99999800, 99999999); // we have to pass an id
        $user->email = $email;
        $user->username = $email;
        $user->mailformat = 1;

        return $user;
    }

    protected function get_email_subject() {
        return $this->process_template_source(get_config('webexactivity', 'notifysubject'));
    }

    protected function get_email_body() {
        return $this->process_template_source(get_config('webexactivity', 'notifyemail'));
    }

    protected function process_template_source($source) {
        if (!empty($this->recording->fileurl)) {
            $oldfileurl = $this->recording->fileurl;
        } else if (!empty($this->recording->oldfileurl)) {
            $oldfileurl = $this->recording->oldfileurl;
        } else {
            $oldfileurl = false;
        }

        if (!empty($this->recording->streamurl)) {
            $oldstreamurl = $this->recording->streamurl;
        } else if (!empty($this->recording->oldstreamurl)) {
            $oldstreamurl = $this->recording->oldstreamurl;
        } else {
            $oldstreamurl = false;
        }

        $context = ['RECORDINGNAME' => $this->recording->name,
                    'NEWURL' => $this->recording->get_recording_url(),
                    'NEWSTREAMURL' => $this->recording->get_recording_url(false, true),
                    'NEWDOWNLOADURL' => $this->recording->get_recording_url(true),
                    'OLDSTREAMURL' => $oldstreamurl,
                    'OLDDOWNLOADURL' => $oldfileurl,
                    'RECORDINGDATETIME' => userdate($this->recording->timecreated),
                    'MEETINGNAME' => FALSE]; // TODO.
error_log(var_export($context, true));
        // Copied and modified from renderer_base::render_from_template().
        $mustache = new Mustache_Engine();

        try {
            // Grab a copy of the existing helper to be restored later.
            $uniqidhelper = $mustache->getHelper('uniqid');
        } catch (\Mustache_Exception_UnknownHelperException $e) {
            // Helper doesn't exist.
            $uniqidhelper = null;
        }

        $mustache->addHelper('uniqid', new \core\output\mustache_uniqid_helper());


        $template = $mustache->loadLambda($source);

        $renderedtemplate = trim($template->render($context));

        // If we had an existing uniqid helper then we need to restore it to allow
        // handle nested calls of render_from_template.
        if ($uniqidhelper) {
            $mustache->addHelper('uniqid', $uniqidhelper);
        }

        return $renderedtemplate;
    }

    protected function log($msg) {
        if (defined('CLI_SCRIPT')) {
            mtrace('Recording ' . $this->recording->id . ': ' . $msg);
        }
    }

    protected function get_from_user() {
        return \core_user::get_noreply_user();
    }

    // Make a fake user for emails that don't have a matching user.
//     protected function get_fake_user($email) {
//         $user = new \stdClass();
//         $user->id = mt_rand(99999800, 99999999); // we have to pass an id
//         $user->email = $email;
//         $user->username = $email;
//         $user->mailformat = 1;
//
//         return $user;
//     }




}
