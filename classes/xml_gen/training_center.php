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

class training_center extends base {


    public static function get_meeting_info($meetingkey) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.GetTrainingSession">'.
               '<sessionKey>'.$meetingkey.'</sessionKey>'.
               '</bodyContent></body>';

        return $xml;
    }

    public static function create_meeting($data) {
        if (!$sessionxml = self::training_session_xml($data)) {
            return false;
        }

        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.CreateTrainingSession">';
        $xml .= $sessionxml;
        $xml .= '</bodyContent></body>';

        return $xml;
    }

    public static function update_meeting($data) {
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

        // Only include the time if it isn't in the past.
        if (isset($data->starttime) && ($data->starttime >= (time() + 10))) {
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

    public static function delete_meeting($meetingkey) {
        $xml = '<body><bodyContent xsi:type="java:com.webex.service.binding.training.DelTrainingSession">'.
               '<sessionKey>'.$meetingkey.'</sessionKey>'.
               '</bodyContent></body>';

        return $xml;
    }

}