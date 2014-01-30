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

class xml_gen {
    public function __construct() {
    }

    public static function auth_wrap($xml, $user = false) {
        return self::standard_wrap(self::get_auth_header($user).$xml);
    }

    private static function standard_wrap($xml) {
        $outxml = '<?xml version="1.0" encoding="UTF-8"?>'.
                  '<serv:message xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'.
                  ' xmlns:serv="http://www.webex.com/schemas/2002/06/service">';
        $outxml .= $xml;
        $outxml .= '</serv:message>';

        return $outxml;
    }

    private static function get_auth_header($user = false) {
        global $CFG;

        $config = get_config('webexactivity');

        $outxml = '<header><securityContext>';

        if ($user == false) {
            $outxml .= '<webExID>'.$config->apiusername.'</webExID>';
            $outxml .= '<password>'.$config->apipassword.'</password>';
        } else {
            $outxml .= '<webExID>'.$user->webexid.'</webExID>';
            $outxml .= '<password>'.$user->password.'</password>';
        }

        $outxml .= '<siteID>'.$config->siteid.'</siteID>';
        $outxml .= '<partnerID>'.$config->partnerid.'</partnerID>';

        $outxml .= '</securityContext></header>';

        return $outxml;
    }

    // ---------------------------------------------------
    // User Functions.
    // ---------------------------------------------------
    public static function get_user_info($username) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.GetUser">'.
               '<webExId>'.$username.'</webExId>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function get_user_login_url($username) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.GetloginurlUser">'.
               '<webExID>'.$username.'</webExID>';
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function create_user($data) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.CreateUser">'.
               '<firstName>'.$data->firstname.'</firstName>'.
               '<lastName>'.$data->lastname.'</lastName>'.
               '<webExId>'.$data->webexid.'</webExId>'.
               '<email>'.$data->email.'</email>'.
               '<password>'.$data->password.'</password>'.
               '<privilege><host>true</host></privilege>'.
               '<active>ACTIVATED</active>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function update_user_password($webexuser) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.SetUser">'.
               '<webExId>'.$webexuser->webexid.'</webExId>'.
               '<password>'.$webexuser->password.'</password>'.
               '<active>ACTIVATED</active>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function check_user_auth($webexuser) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.GetUser">'.
               '<webExId>'.$webexuser->webexid.'</webExId>'.
               '</bodyContent></body>';

        return $xml;
    }

    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public static function get_meeting_info($meeingkey) {
        debugging('Function get_meeting_info must be implemented by child class.', DEBUG_DEVELOPER);
    }

    public static function create_meeting($data) {
        debugging('Function create_meeting must be implemented by child class.', DEBUG_DEVELOPER);
    }

    public static function update_meeting($data) {
        debugging('Function update_meeting must be implemented by child class.', DEBUG_DEVELOPER);
    }

    public static function delete_meeting($meetingkey) {
        debugging('Function delete_meeting must be implemented by child class.', DEBUG_DEVELOPER);
    }

    public static function list_open_sessions() {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.ep.LstOpenSession">'.
               '</bodyContent></body>';

        return $xml;
    }

    // ---------------------------------------------------
    // Recording Functions.
    // ---------------------------------------------------
    public static function list_recordings($data) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.ep.LstRecording">';

        if (isset($data->meetingkey)) {
            $xml .= '<sessionKey>'.$data->meetingkey.'</sessionKey>';
        }
        if (isset($data->startdate) && isset($data->enddate)) {
            $xml .= '<createTimeScope>';
            $xml .= '<createTimeStart>'.self::time_to_date_string($data->startdate).'</createTimeStart>';
            $xml .= '<createTimeEnd>'.self::time_to_date_string($data->enddate).'</createTimeEnd>';
            $xml .= '</createTimeScope>';
        }
        $xml .= '<returnSessionDetails>true</returnSessionDetails>';
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function recording_detail($recordingid) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.ep.GetRecordingInfo">';
        $xml .= '<recordingID>'.$recordingid.'</recordingID>';
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function delete_recording($recordingid) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.ep.DelRecording">';
        $xml .= '<recordingID>'.$recordingid.'</recordingID>';
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function update_recording($data) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.ep.SetRecordingInfo">';
        $xml .= '<recording><recordingID>'.$data->recordingid.'</recordingID><description>Des 1</description></recording>';

        if (isset($data->name)) {
            $xml .= '<basic>';
            $xml .= '<topic>'.htmlentities($data->name).'</topic>';
            $xml .= '<agenda>Agenda 1</agenda>';
            $xml .= '</basic>';
        }
        $xml .= '<fileAccess><attendeeDownload>false</attendeeDownload></fileAccess>';
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public static function time_to_date_string($time) {
        return date('m/d/Y H:i:s', $time);
    }

}