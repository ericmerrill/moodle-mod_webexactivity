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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('modwebexactivityrecordings');

$pageurl = new moodle_url('/mod/webexactivity/admin_recordings.php');

$action = optional_param('action', false, PARAM_ALPHA);
$view = optional_param('view', false, PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

switch ($action) {
    case 'delete':
        // Delete recording. Check for confirmation, show form is not present.
        $confirm = optional_param('confirm', 0, PARAM_INT);
        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\recording($recordingid);

        if (!$confirm) {
            $view = 'deleterecording';
            break;
        } else {
            // TODO Log event.

            $recording->delete();
            redirect($pageurl->out(false));
        }
        break;

    case 'undelete':
        // Mark recording as not deleted.
        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\recording($recordingid);

        $recording->undelete();
        redirect($pageurl->out(false));
        break;

}

// Setup the sql table.
$table = new \mod_webexactivity\admin_recordings_table('webexactivityadminrecordingstable');
$table->define_baseurl($pageurl);

// Content.
$table->set_sql('*', '{webexactivity_recording}', '1=1', array());
$table->define_columns(array('name', 'hostid', 'timecreated', 'duration', 'filesize', 'fileurl',
                             'streamurl', 'deleted', 'webexid'));
$table->define_headers(array(get_string('name'), get_string('host', 'webexactivity'), get_string('date'),
                             get_string('duration', 'search'), get_string('size'), get_string('download'),
                             get_string('stream', 'webexactivity'), get_string('delete'), get_string('activity')));

// Options.
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('fileurl');
$table->no_sorting('streamurl');

$table->is_downloadable(true);


// Setup for downloading.
if ($download) {
    // Redefine headers for download.
    $table->define_headers(array(get_string('name'), get_string('host', 'webexactivity'), get_string('date'),
                                 get_string('duration', 'search'), get_string('size'), get_string('download'),
                                 get_string('stream', 'webexactivity'), get_string('deletetime', 'webexactivity'),
                                 get_string('activity')));
    $table->is_downloading($download, get_string('webexrecordings', 'webexactivity'));
    $table->out(50, false);
    die();
}

// Standard page output.
echo $OUTPUT->header();

if (!$view) {
    // By default, just print the table.
    $table->out(50, false);
} else if ($view === 'deleterecording') {
    // Show the delete recording confirmation page.
    $params = array('action' => 'delete', 'confirm' => 1, 'recordingid' => $recordingid);
    $confirmurl = new moodle_url($pageurl, $params);

    $params = new stdClass();
    $params->name = $recording->name;
    $params->time = format_time($recording->duration);
    $message = get_string('confirmrecordingdelete', 'webexactivity', $params);
    echo $OUTPUT->confirm($message, $confirmurl, $pageurl);
}

echo $OUTPUT->footer();
