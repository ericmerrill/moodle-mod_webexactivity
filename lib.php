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

defined('MOODLE_INTERNAL') || die();

define('WEBEXACTIVITY_TYPE_MEETING', 1);
define('WEBEXACTIVITY_TYPE_TRAINING', 2);
define('WEBEXACTIVITY_TYPE_SUPPORT', 3);

function webexactivity_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_ASSIGNMENT;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPMEMBERSONLY:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return false;

        default:
            return null;
    }
}

function webexactivity_add_instance($data, $mform) {
    global $CFG, $DB, $USER;

    $meeting = new \stdClass();
    $meeting->timemodified = time();
    $meeting->starttime = $data->starttime;
    $meeting->length = $data->duration;
    $meeting->intro = $data->intro;
    $meeting->name = $data->name;
    $meeting->course = $data->course;
    $meeting->type = WEBEXACTIVITY_TYPE_TRAINING;
    $meeting->id = $DB->insert_record('webexactivity', $meeting);

    $webex = new \mod_webexactivity\webex();
//    $webex->create_or_update_meeting($meeting, $USER);
    if (!$webex->create_or_update_training($meeting, $USER)) {
        return false;
    }

    return $meeting->id;
}

function webexactivity_update_instance($data) {

}

function webexactivity_delete_instance($id) {

}
