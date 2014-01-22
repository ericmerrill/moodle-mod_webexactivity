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

class xml_generator {
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
    public static function get_meeting_info($meetingid) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.meeting.GetMeeting">'.
               '<meetingKey>'.$meetingid.'</meetingKey>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function create_meeting($data) {
        $startstr = date('m/d/Y H:i:s', $data->starttime);

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.meeting.CreateMeeting">'.
               '<schedule><startDate>'.$startstr.'</startDate><openTime>20</openTime></schedule>'.
               '<metaData><confName>'.$data->name.'</confName><agenda>agenda 1</agenda></metaData>'.
               '</bodyContent></body>';

        return $xml;
    }

    // ---------------------------------------------------
    // Training Center Functions.
    // ---------------------------------------------------
    public static function get_training_info($meetingid) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.GetTrainingSession">'.
               '<sessionKey>'.$meetingid.'</sessionKey>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function create_training_session($data) {
        if (!$sessionxml = self::training_session_xml($data)) {
            return false;
        }

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.CreateTrainingSession">';
        $xml .= $sessionxml;
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function update_training_session($data) {
        if (!$sessionxml = self::training_session_xml($data)) {
            return false;
        }

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.SetTrainingSession">';
        $xml .= $sessionxml;
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    private static function training_session_xml($data) {
        $xml = '';
        if (isset($data->meetingkey)) {
            $xml .= '<sessionKey>'.$data->meetingkey.'</sessionKey>';
        }

        $xml .= '<accessControl><listing>UNLISTED</listing></accessControl>';

        if (isset($data->starttime)) {
            $startstr = self::time_to_date_string($data->starttime);

            $xml .= '<schedule>';
            $xml .= '<startDate>'.$startstr.'</startDate>';
            $xml .= '<openTime>20</openTime>';
            if (isset($data->duration)) {
                $xml .= '<duration>'.$data->duration.'</duration>';
            }
            $xml .= '</schedule>';
        }

        if (isset($data->name)) {
            $xml .= '<metaData>';
            $xml .= '<confName>'.htmlentities($data->name).'</confName>';
            if (isset($data->intro)) {
                $xml .= '<description>'.htmlentities($data->intro).'</description>';
            }
            $xml .= '</metaData>';
        }

        $xml .= '<enableOptions>';

        /*if (isset($data->allchat)) {
            if ($data->allchat) {
                $xml .= '<chatAllAttendees>true</chatAllAttendees>';
            } else {
                $xml .= '<chatAllAttendees>false</chatAllAttendees>';
            }
        }*/

        $xml .= '</enableOptions>';

        if (isset($data->hostusers)) {
            $xml .= '<presenters><participants>';
            foreach ($data->hostusers as $huser) {
                $xml .= '<participant><person>';

                if (isset($huser->firstname) && isset($huser->lastname)) {
                    $xml .= '<name>'.$huser->firstname.' '.$huser->lastname.'</name>';
                }
                if (isset($huser->email)) {
                    $xml .= '<email>'.$huser->email.'</email>';
                }
                if (isset($huser->webexid)) {
                    $xml .= '<webExId>'.$huser->webexid.'</webExId>';
                }
                $xml .= '<type>MEMBER</type></person>'.
                        '<role>HOST</role></participant>';
            }
            $xml .= '</participants></presenters>';
        }

        // TODO Expand.

        $xml .= '<repeat><repeatType>SINGLE</repeatType></repeat>';

        return $xml;
    }

    public static function delete_training_session($data) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.DelTrainingSession">'.
               '<sessionKey>'.$data->meetingkey.'</sessionKey>'.
               '</bodyContent></body>';

        return $xml;
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

    // ---------------------------------------------------
    // Support Functions.
    // ---------------------------------------------------
    public static function time_to_date_string($time) {
        return date('m/d/Y H:i:s', $time);
    }
}
