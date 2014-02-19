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


require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

admin_externalpage_setup('modwebexactivityrecordings');

$pageurl = new moodle_url('/mod/webexactivity/admin_recordings.php');

$action = optional_param('action', false, PARAM_ALPHA);
$view = optional_param('view', false, PARAM_ALPHA);

switch ($action) {
    case 'delete':
        $confirm = optional_param('confirm', 0, PARAM_INT);
        $recordingid = required_param('recordingid', PARAM_INT);
        $recording = new \mod_webexactivity\webex_recording($recordingid);

        if (!$confirm) {
            $view = 'deleterecording';
            break;
        } else {
            $params = array(
                'context' => $context,
                'objectid' => $recordingid
            );
            $event = \mod_webexactivity\event\recording_deleted::create($params);
            $event->add_record_snapshot('webexactivity_recording', $recording->record);
            $event->trigger();

            //$recording->delete();
            redirect($pageurl->out(false));
        }
        break;


}






echo $OUTPUT->header();


class mod_webexactivity_recordings_tables extends table_sql implements renderable {


    public function col_duration($recording) {
        return format_time($recording->duration);
    }

    public function col_filesize($recording) {
        return display_size($recording->filesize);
    }

    public function col_fileurl($recording) {
        return '<a href="'.$recording->fileurl.'">Download</a>';
    }

    public function col_streamurl($recording) {
        return '<a href="'.$recording->streamurl.'">Stream</a>';
    }

    public function col_webexid($recording) {
        if (isset($recording->webexid)) {
            $cm = get_coursemodule_from_instance('webexactivity', $recording->webexid);
            if ($cm) {
                $returnurl = new moodle_url('/mod/webexactivity/view.php', array('id' => $cm->id));
                return '<a href="'.$returnurl->out(false).'">Activity</a>';
            } else {
                return '-';
            }
        } else {
            return '-';
        }
    }

    public function col_timecreated($recording) {
        return userdate($recording->timecreated);
    }

    public function col_delete($recording) {
        $pageurl = new moodle_url('/mod/webexactivity/admin_recordings.php', array('action' => 'delete', 'recordingid' => $recording->id));
        return '<a href="'.$pageurl->out(false).'">Delete</a>';
    }
}

if (!$view) {
    $table = new mod_webexactivity_recordings_tables('webexactivityadminrecordingstable2');
    $table->define_baseurl($pageurl);

    $table->set_sql('*', '{webexactivity_recording}', '1=1', array());
    $table->define_columns(array('name', 'timecreated', 'duration', 'filesize', 'fileurl', 'streamurl', 'Delete', 'webexid'));
    $table->define_headers(array('Name', 'Date', 'Length', 'Size', 'Download', 'Stream', 'Delete', 'Activity'));

    $table->sortable(true, 'timecreated', SORT_DESC);
    $table->no_sorting('fileurl');
    $table->no_sorting('streamurl');

    $table->out(50, false);
} else if ($view === 'deleterecording') {
    // Show the delete recording confirmation page.
    $params = array('action' => 'deleterecording', 'confirm' => 1, 'recordingid' => $recordingid);
    $confirmurl = new moodle_url($pageurl, $params);

    $params = new stdClass();
    $params->name = $recording->name;
    $params->time = format_time($recording->duration);
    $message = get_string('confirmrecordingdelete', 'webexactivity', $params);
    echo $OUTPUT->confirm($message, $confirmurl, $pageurl);
}




echo $OUTPUT->footer();

