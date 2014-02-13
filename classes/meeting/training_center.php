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

    /** 
     * Prefix for retrieved XML fields.
     **/
    const XML_PREFIX = 'train';

    /**
     * Builds the meeting object.
     *
     * @param object/int    $meeting Object of meeting record, or id of record to load.
     */
    public function __construct($meeting = false) {
        parent::__construct($meeting);

        if (!isset($this->type)) {
            $this->type = \mod_webexactivity\webex::WEBEXACTIVITY_TYPE_TRAINING;
        }
    }

    /**
     * Process a response from WebEx into the meeting.
     *
     * @param array    $response XML array of the response from WebEx for meeting information.
     */
    protected function process_response($response) {
        if (!parent::process_response($response)) {
            return false;
        }

        if (empty($response)) {
            return true;
        }

        // Type specific code goes here.

        return true;
    }

}
