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

defined('MOODLE_INTERNAL') || die();

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
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
            return true;
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

/**
 * Adds an WebEx Meeting instance.
 *
 * @param stdClass              $data Form data
 * @param mod_assign_mod_form   $form The form
 * @return int The instance id of the new assignment
 */
function webexactivity_add_instance($data, $mform) {
    global $PAGE;

    $meeting = \mod_webexactivity\meeting::create_new($data->type);
    $meeting->starttime = $data->starttime;
    $meeting->duration = $data->duration;
    if (isset($data->longavailability)) {
        $meeting->endtime = $data->endtime;
    } else {
        $meeting->endtime = null;
    }
    $meeting->intro = $data->intro;
    $meeting->introformat = $data->introformat;
    $meeting->name = $data->name;
    $meeting->course = $data->course;
    $meeting->status = \mod_webexactivity\webex::WEBEXACTIVITY_STATUS_NEVER_STARTED;
    if (isset($data->studentdownload) && $data->studentdownload) {
        $meeting->studentdownload = 1;
    } else {
        $meeting->studentdownload = 0;
    }

    if ($meeting->save()) {
        return $meeting->id;
    }

    return false;
}

/**
 * Update an WebEx Meeting instance.
 *
 * @param stdClass              $data Form data
 * @param mod_assign_mod_form   $form The form
 * @return bool                 If the update passed (true) or failed
 */
function webexactivity_update_instance($data, $mform) {
    global $PAGE;

    $cmid = $data->coursemodule;
    $cm = get_coursemodule_from_id('webexactivity', $cmid, 0, false, MUST_EXIST);
    $meeting = \mod_webexactivity\meeting::load($cm->instance);

    $meeting->starttime = $data->starttime;
    $meeting->duration = $data->duration;
    if (isset($data->longavailability)) {
        $meeting->endtime = $data->endtime;
    } else {
        $meeting->endtime = null;
    }
    $meeting->intro = $data->intro;
    $meeting->introformat = $data->introformat;
    $meeting->name = $data->name;
    $meeting->course = $data->course;

    if (isset($data->studentdownload) && $data->studentdownload) {
        $meeting->studentdownload = 1;
    } else {
        $meeting->studentdownload = 0;
    }

    try {
        return $meeting->save();
    } catch (Exception $e) {
        $collision = ($e instanceof \mod_webexactivity\exception\webex_user_collision);
        $password = ($e instanceof \mod_webexactivity\exception\bad_password);
        if ($collision || $password) {
            \mod_webexactivity\webex::password_redirect($PAGE->url);
        } else {
            throw $e;
        }
        throw $e;
    }
}

/**
 * Delete a WebEx instance.
 *
 * @param int   $id     Record id to delete.
 * @return bool
 */
function webexactivity_delete_instance($id) {
    $meeting = \mod_webexactivity\meeting::load($id);
    return $meeting->delete();
}

/**
 * Run the WebEx cron functions.
 *
 * @return bool   true if successful.
 */
function webexactivity_cron() {
    $webex = new \mod_webexactivity\webex();
    $webex->update_recordings();
    $webex->update_open_sessions();
    $webex->remove_deleted_recordings();

    return true;
}
