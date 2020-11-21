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
 * A simple interface for remote checks.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

try {
    require('../../config.php');

    $meetingkey = optional_param('meetingkeyisassociated', 0, PARAM_INT);

    $result = ['result' => 0, 'error' => 0];

    if (!empty($meetingkey)) {
        if ($DB->record_exists('webexactivity', ['meetingkey' => $meetingkey])) {
            $result['result'] = 1;
        }
    }

    echo json_encode($result);

} catch (Exception $e) {
    echo '{"result":0,"error":1}';
}
