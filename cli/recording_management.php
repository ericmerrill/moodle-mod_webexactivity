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
 * A tool for managing downloads in Webex.
 *
 * @package    mod_webexactivity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_webexactivity\recording;

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(['all' => false,
                                                'delete-remote' => false,
                                                'delete-remote-force' => false,
                                                'download' => false,
                                                'endtime' => false,
                                                'file-external' => false,
                                                'file-internal' => false,
                                                'file-both' => false,
                                                'moodle-meeting' => false,
                                                'no-moodle-meeting' => false,
                                                'limit' => 0,
                                                'offset' => 0,
                                                'recordingid' => false,
                                                'starttime' => false,
                                                'help' => false],
                                               ['a' => 'all',
                                                'd' => 'download',
                                                'e' => 'endtime',
                                                'l' => 'limit',
                                                'o' => 'offset',
                                                'r' => 'recordingid',
                                                's' => 'starttime',
                                                'h' => 'help']);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Script to manage Webex recordings.
Lists recordings found if no action options included.

Selection options:
-r, --recordingid
-s, --starttime
-e, --endtime
-l, --limit
-o, --offset
--file-external
--file-internal
--file-both
--moodle-meeting
--no-moodle-meeting
-a, --all               If set, include all recordings in DB, even those not associated with a
                        activity instance.

Actions:
-d, --download
--delete-remote
--delete-remote-force

--force
-h, --help              Print out this help


Example:
\$sudo -u www-data /usr/bin/php mod/webexactivity/cli/recording_management.php
";

    echo $help;
    die;
}

$recordingid = $options['recordingid'];

$starttime = $options['starttime'];
if (!is_numeric($starttime)) {
    $starttime = strtotime($starttime);
}

$endtime = $options['endtime'];
if (!is_numeric($endtime)) {
    $endtime = strtotime($endtime);
}

if (is_numeric($starttime) && is_numeric($endtime) && ($starttime >= $endtime)) {
    mtrace("Endtime is before starttime. Aborting.");
    exit(1);
}


if ($recordingid) {
    $records = $DB->get_recordset('webexactivity_recording', ['id' => $recordingid]);
} else {
    $select = '1 = 1';
    $params = [];
    if (is_numeric($starttime)) {
        $select .= ' AND timecreated >= ?';
        $params[] = $starttime;
    }
    if (is_numeric($endtime)) {
        $select .= ' AND timecreated <= ?';
        $params[] = $endtime;
    }

    // TODO - maybe OR groups?
    if ($options['moodle-meeting']) {
        $select .= ' AND webexid IS NOT NULL';
    }
    if ($options['no-moodle-meeting']) {
        $select .= ' AND webexid IS NULL';
    } else if (!$options['all']) {
        $select .= ' AND webexid IS NOT NULL';
    }

    // TODO - maybe OR groups?
    if ($options['file-external']) {
        $select .= ' AND filestatus = ?';
        $params[] = recording::FILE_STATUS_WEBEX;
    }
    if ($options['file-internal']) {
        $select .= ' AND filestatus = ?';
        $params[] = recording::FILE_STATUS_INTERNAL;
    }
    if ($options['file-both']) {
        $select .= ' AND filestatus = ?';
        $params[] = recording::FILE_STATUS_INTERNAL_AND_WEBEX;
    }

    $limit = $options['limit'];
    $start = $options['offset'];

    $records = $DB->get_recordset_select('webexactivity_recording', $select, $params, 'id ASC', '*', $start, $limit);
}

$delete = (bool)$options['delete-remote'];
$deleteforce = (bool)$options['delete-remote-force'];
$download = (bool)$options['download'];

foreach ($records as $rec) {
    $recording = new recording($rec);

    render_recording($recording);

    if ($download) {
        mtrace("Creating download adhoc task " . ($delete ? "with" : "without") . " delete");
        $recording->create_download_task(null, $delete);
    } else if ($deleteforce) {
        mtrace("Creating a delete adhoc task with force");
        $recording->create_delete_task(true);
    } else if ($delete) {
        if ($recording->has_internal_file(true)) {
            $recording->create_delete_task();
            mtrace("Creating a delete adhoc task without force");
        } else {
            mtrace("ERROR: Recording does not have internal file. Skipping. Use delete-remote-force to force.");
        }
    }
}


function render_recording($recording) {
    $keys = ['id',
             'webexid',
             'recordingid',
             'hostid',
             'name',
             'timecreated',
             'timemodified',
             'fileurl',
             'filesize',
             'visible',
             'filestatus'];

    mtrace('--------------------------------------------');
    foreach ($keys as $key) {
        $val = $recording->$key;
        if (is_null($val)) {
            $val = '*NULL*';
        }
        mtrace(str_pad($key, 13).' : '.$val);
    }
//     mtrace(var_export($recording->record, true));
}
