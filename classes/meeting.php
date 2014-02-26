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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/xmlize.php');

/**
 * Static factories to build meetings of the correct types.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting {
    /**
     * Loads a meeting object of the propper type.
     *
     * @param stdClass|int     $meeting Meeting record, or id of record, to load.
     * @return bool|meeting    A meeting object or false on failure.
     */
    public static function load($meeting) {
        global $DB;

        if (is_numeric($meeting)) {
            $record = $DB->get_record('webexactivity', array('id' => $meeting));
        } else if (is_object($meeting)) {
            $record = $meeting;
        } else {
            debugging('Unable to load meeting', DEBUG_DEVELOPER);
            return false;
        }

        switch ($record->type) {
            case webex::WEBEXACTIVITY_TYPE_MEETING:
                $meeting = new type\meeting_center\meeting($record);
                return $meeting;
                break;
            case webex::WEBEXACTIVITY_TYPE_TRAINING:
                $meeting = new type\training_center\meeting($record);
                return $meeting;
                break;
            case webex::WEBEXACTIVITY_TYPE_SUPPORT:
                debugging('Support center not yet supported', DEBUG_DEVELOPER);
                break;
            default:
                debugging('Unknown Type', DEBUG_DEVELOPER);

        }

        return false;
    }

    /**
     * Create a meeting object of the propper type.
     *
     * @param int     $type  The type to create.
     * @return bool|meeting  A meeting object or false on failure.
     */
    public static function create_new($type) {
        switch ($type) {
            case webex::WEBEXACTIVITY_TYPE_MEETING:
                return new type\meeting_center\meeting();
                break;
            case webex::WEBEXACTIVITY_TYPE_TRAINING:
                return new type\training_center\meeting();
                break;
            case webex::WEBEXACTIVITY_TYPE_SUPPORT:
                debugging('Support center not yet supported', DEBUG_DEVELOPER);
                break;
            default:
                debugging('Unknown Type', DEBUG_DEVELOPER);
        }

        return false;
    }
}
