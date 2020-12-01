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
use mod_webexactivity\webex;

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(['all' => false,
                                                'cmid' => false,
                                                'courseid' => false,
                                                'catid' => false,
                                                'check-download-status' => false,
                                                'delete-remote' => false,
                                                'delete-remote-force' => false,
                                                'download' => false,
                                                'download-force' => false,
                                                'endtime' => false,
                                                'file-external' => false,
                                                'file-internal' => false,
                                                'file-both' => false,
                                                'generate-missing-ids' => false,
                                                'hostid' => false,
                                                'moodle-meeting' => false,
                                                'make-public' => false,
                                                'make-private' => false,
                                                'no-moodle-meeting' => false,
                                                'limit' => 0,
                                                'offset' => 0,
                                                'recordid' => false,
                                                'recordingid' => false,
                                                'remove-extra-recordings' => false,
                                                'short' => false,
                                                'starttime' => false,
                                                'uniqueid' => false,
                                                'update-remote-server' => false,
                                                'webexid' => false,
                                                'help' => false],
                                               ['a' => 'all',
                                                'd' => 'download',
                                                'e' => 'endtime',
                                                'l' => 'limit',
                                                'o' => 'offset',
                                                'r' => 'recordid',
                                                's' => 'starttime',
                                                'u' => 'uniqueid',
                                                'h' => 'help']);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Script to manage Webex recordings.
Lists recordings found if no action options included.

--short

Selection options:
-r, --recordid
-u, --uniqueid
--recordingid
--webexid
--cmid
--courseid
--catid
--hostid
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
--download-force
--delete-remote
--delete-remote-force
--make-public
--make-private
--generate-missing-ids
--remove-extra-recordings
--update-remote-server
--check-download-status

--force
-h, --help              Print out this help


Example:
\$sudo -u www-data /usr/bin/php mod/webexactivity/cli/recording_management.php
";

    echo $help;
    die;
}

$recordingid = $options['recordingid'];
$recordid = $options['recordid'];

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

$shortlog = (bool)$options['short'];

if ($recordid) {
    $records = $DB->get_recordset('webexactivity_recording', ['id' => $recordid]);
} else if ($recordingid) {
    $records = $DB->get_recordset('webexactivity_recording', ['recordingid' => $recordingid]);
} else if ($options['uniqueid']) {
    $records = $DB->get_recordset('webexactivity_recording', ['uniqueid' => $options['uniqueid']]);
} else {
    $select = '1 = 1';
    $params = [];

    if ($options['webexid']) {
        $select .= ' AND webexid >= ?';
        $params[] = $options['webexid'];
    }

    if ($options['cmid']) {
        $select .= ' AND webexid IN (SELECT instance FROM {course_modules} WHERE id = ?)';
        $params[] = $options['cmid'];
    }

    if ($options['courseid']) {
        $select .= ' AND webexid IN (SELECT id FROM {webexactivity} WHERE course = ?)';
        $params[] = $options['courseid'];
    }

    if ($options['catid']) {
        $select .= ' AND webexid IN (SELECT id FROM {webexactivity} WHERE course IN (SELECT id FROM {course} WHERE category = ?))';
        $params[] = $options['catid'];
    }

    if ($options['hostid']) {
        $select .= ' AND hostid = ?';
        $params[] = $options['hostid'];
    }

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
$downloadforce = (bool)$options['download-force'];

$webex = new webex();

$count = 0;
foreach ($records as $rec) {
    $count++;
    $recording = new recording($rec);

    render_recording($recording, $shortlog);

    if ($options['remove-extra-recordings'] && is_null($recording->webexid)) {
        // Delete the local copy only.
        $recording->true_delete(false);
        mtrace("Deleted as extra recording.");
        continue;
    }

    if ($options['make-public']) {
        $recording->publicview = 1;
        $recording->save();
        mtrace("Made recording public.");
    }
    if ($options['make-private']) {
        $recording->publicview = 0;
        $recording->save();
        mtrace("Made recording private.");
    }
    if ($options['generate-missing-ids']) {
        // We refetch the recording, just to make sure the unique ID hasn't since been added.
        $newrec = new recording($rec->id);
        if (empty($newrec->uniqueid)) {
            $newrec->uniqueid = recording::generate_unique_id();
            $newrec->save();
            $recording->uniqueid = $newrec->uniqueid;
            mtrace("Created unique id ".$newrec->uniqueid);
        } else {
            mtrace("Recording already has uniqueid ".$recording->uniqueid);
        }

    }

    if ($options['check-download-status']) {
        if ($recording->should_be_downloaded()) {
            mtrace('This recording should be downloaded');
        } else {
            mtrace('This recording should not be downloaded');
        }
    }
    if ($options['update-remote-server']) {
        $recording->update_remote_server();
        if (isset($recording->remoteserver) && $recording->remoteserver === false) {
            mtrace('Recording belongs to this server.');
        } else if (isset($recording->remoteserver)) {
            mtrace('Recording belongs to the server '.$recording->remoteserver);
        } else {
            mtrace('Recording doesn\'t belong to a meeting on any known server.');
        }
    }

    if ($downloadforce) {
        mtrace("Creating download (with force) adhoc task " . ($delete ? "with" : "without") . " delete");
        $recording->create_download_task(true, $delete);
    } else if ($download) {
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
mtrace('--------------------------------------------');
mtrace($count.' matching records found.');

function render_recording($recording, $shortlog) {
    if ($shortlog) {
        $keys = ['id',
                 'uniqueid',
                 'webexid',
                 'meetingkey',
                 'recordingid',
                 'hostid',
                 'name',
                 'timecreated',
                 'timemodified',
                 'filesize',
                 'filestatus'];

        $values = [];
        foreach ($keys as $key) {
            $val = $recording->$key;
            if (is_null($val)) {
                $val = '*NULL*';
            }
            if (strlen($val) > 20) {
                $val = substr($val, 0, 20) . '...';
            }
            $values[] = $val;
        }
        mtrace(implode(", ", $values));
        return;
    }

    $keys = ['id',
             'uniqueid',
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
}
