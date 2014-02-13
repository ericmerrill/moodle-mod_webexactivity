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

namespace mod_webexactivity\meeting;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that represents and controls a Training Center meeting instance.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class training_center extends base {

    /** 
     * The XML generator class name to use.
     **/
    const GENERATOR = '\mod_webexactivity\xml_gen\training_center';

    public function __construct($meeting = false) {
        parent::__construct($meeting);

        if (!isset($this->type)) {
            $this->type = \mod_webexactivity\webex::WEBEXACTIVITY_TYPE_TRAINING;
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
            $this->meetingkey = $response['train:sessionkey']['0']['#'];
        }

        if (isset($response['train:additionalInfo']['0']['#']['train:guestToken']['0']['#'])) {
            $this->guestkey = $response['train:additionalInfo']['0']['#']['train:guestToken']['0']['#'];
        }

        if (isset($response['train:eventID']['0']['#'])) {
            $this->eventid = $response['train:eventID']['0']['#'];
        }

        if (isset($response['train:hostKey']['0']['#'])) {
            $this->hostkey = $response['train:hostKey']['0']['#'];
        }

        return true;
    }

}
