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

namespace mod_webexactivity\xml_gen;

defined('MOODLE_INTERNAL') || die();

/**
 * A class that (statically) provides meeting center xml.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_center extends base {
    /**
     * Provide the xml to get information about a meeting. Must be overridden.
     *
     * @param string    $meetingkey Meeting key to lookup.
     * @return string   The XML.
     */
    public static function get_meeting_info($meeingkey) {
    }

    /**
     * Provide the xml to create a meeting. Must be overridden.
     *
     * Required keys in $data are:
     * 1/ startdate - Start time range.
     * 2/ duration - Duration in minutes.
     * 3/ name - Name of the meeting.
     *
     * Optional keys in $data are:
     * 1/ intro - Meeting description.
     * 2/ hostusers - Array of users to add as hosts.
     *
     * @param object    $data Meeting data to make.
     * @return string   The XML.
     */
    public static function create_meeting($data) {
    }

    /**
     * Provide the xml to update a meeting. Must be overridden.
     *
     * Required keys in $data are:
     * 1/ meetingkey - Meeting key to update.
     * 
     * Optional keys in $data are:
     * 1/ startdate - Start time range.
     * 2/ duration - Duration in minutes.
     * 3/ name - Name of the meeting.
     * 4/ intro - Meeting description.
     * 5/ hostusers - Array of users to add as hosts.
     *
     * @param object    $data Meeting data to make.
     * @return string   The XML.
     */
    public static function update_meeting($data) {
    }

    /**
     * Provide the xml to delete a meeting. Must be overridden.
     *
     * @param string    $meetingkey Meeting key to delete.
     * @return string   The XML.
     */
    public static function delete_meeting($meetingkey) {
    }

}
