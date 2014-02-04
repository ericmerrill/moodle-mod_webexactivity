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

class webex_training extends webex_meeting_shell {

    protected $gen = '\mod_webexactivity\xml_gen_training';

    public function __construct($meeting = false) {
        parent::__construct($meeting);

        if ($this->values['type'] === null) {
            $this->values['type'] = webex::WEBEXACTIVITY_TYPE_TRAINING;
        }
    }

    protected function process_response($response) {
        global $DB;

        if ($response === false) {
            return false;
        }

        if (empty($response)) {
            return true;
        }

        if (isset($response['train:sessionkey']['0']['#'])) {
            $this->values['meetingkey'] = $response['train:sessionkey']['0']['#'];
        }

        if (isset($response['train:additionalInfo']['0']['#']['train:guestToken']['0']['#'])) {
            $this->values['guesttoken'] = $response['train:additionalInfo']['0']['#']['train:guestToken']['0']['#'];
        }

        if (isset($response['train:eventID']['0']['#'])) {
            $this->values['eventid'] = $response['train:eventID']['0']['#'];
        }

        if (isset($response['train:hostKey']['0']['#'])) {
            $this->values['hostkey'] = $response['train:hostKey']['0']['#'];
        }

        return true;
    }

}
