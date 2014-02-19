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

admin_externalpage_setup('modwebexactivityusers');

$pageurl = new moodle_url('/mod/webexactivity/admin_users.php');

$action = optional_param('action', false, PARAM_ALPHA);

switch ($action) {
    case 'login':
        // First log the user out (in case they are logged in, then bring back to logintrue.
        $webexid = required_param('webexid', PARAM_ALPHAEXT);
        $returnurl = new moodle_url($pageurl, array('action' => 'logintrue', 'webexid' => $webexid));
        redirect(\mod_webexactivity\webex_user::get_logout_url($returnurl->out(false)));
        break;

    case 'logintrue':
        // Actually log the user in.
        $webexid = required_param('webexid', PARAM_ALPHAEXT);
        $webexuser = new \mod_webexactivity\webex_user($webexid);

        redirect($webexuser->get_login_url());
        break;
}



echo $OUTPUT->header();

class mod_webexactivity_users_tables extends table_sql implements renderable {


    public function col_login($user) {
        $pageurl = new moodle_url('/mod/webexactivity/admin_users.php', array('action' => 'login', 'webexid' => $user->webexid));
        return '<a href="'.$pageurl->out(false).'" target=_blank>Login</a>';
    }

}

$table = new mod_webexactivity_users_tables('webexactivityadminrecordingstable2');
$table->define_baseurl($pageurl);
$table->set_sql('*', '{webexactivity_user} AS wu LEFT JOIN {user} AS u ON wu.moodleuserid = u.id', '1=1', array());

$table->define_columns(array('firstname', 'lastname', 'email', 'webexid', 'login'));
$table->define_headers(array('First Name', 'Last Name', 'Email', 'Webex ID', ''));
$table->no_sorting('login');

$table->out(50, false);



echo $OUTPUT->footer();

