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

    private static function auth_wrap($xml, $user = false) {
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
        } // TODO User support.

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
               '<webExID>'.$username.'</webExID>'.
               '</bodyContent></body>';

        return self::auth_wrap($xml);
    }

    public static function get_user_login_url($username) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.user.GetloginurlUser">'.
               '<webExID>'.$username.'</webExID>'.
               '</bodyContent></body>';

        return self::auth_wrap($xml);
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

        return self::auth_wrap($xml);
    }

    public function update_user($data) {

    }

    public function test_user($data) {

    }

    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public function get_meeting_info($meetingid) {

    }

    public static function create_meeting($data) {
        $startstr = date('m/d/Y H:i:s', $data->starttime);

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.meeting.CreateMeeting">'.
               '<schedule><startDate>'.$startstr.'</startDate><openTime>20</openTime></schedule>'.
               '<metaData><confName>'.$data->name.'</confName><agenda>agenda 1</agenda></metaData>'.
               '</bodyContent></body>';

        return self::auth_wrap($xml);
    }

    // ---------------------------------------------------
    // Meeting Functions.
    // ---------------------------------------------------
    public static function get_training_info($meetingid) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.GetTrainingSession">'.
               '<sessionKey>'.$meetingid.'</sessionKey>'.
               '</bodyContent></body>';

        return self::auth_wrap($xml);
    }

    public static function create_training_session($data) {
        $startstr = date('m/d/Y H:i:s', $data->starttime);

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.CreateTrainingSession">'.
               '<accessControl><listing>PUBLIC</listing></accessControl>'.
               '<schedule><startDate>'.$startstr.'</startDate><openTime>20</openTime></schedule>'.
               '<metaData><confName>'.$data->name.'</confName><agenda>agenda 1</agenda><description>description</description></metaData>'.
               '<repeat><repeatType>SINGLE</repeatType></repeat>'.
               '</bodyContent></body>';

        return self::auth_wrap($xml);
    }
}
