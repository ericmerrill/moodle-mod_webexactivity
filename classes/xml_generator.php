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
        //print "<pre>";
        print p(self::standard_wrap(self::get_auth_header()));
        //print "</pre>";
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

    	$outxml = '<header><securityContext>';

	    if ($user == false) {
		    $outxml .= '<webExID>'.$CFG->webexuser.'</webExID>'; // TODO Setting.
            $outxml .= '<password>'.$CFG->webexpass.'</password>';
	    } // TODO User support.

        $outxml .= '<siteID>12355757</siteID>'; // TODO Setting.
        $outxml .= '<partnerID>123oa!</partnerID>'; //TODO Setting.

	    $outxml .= '</securityContext></header>';

        return $outxml;	    
    }
}
