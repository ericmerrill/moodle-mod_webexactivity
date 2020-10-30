<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/clilib.php");
require_once("{$CFG->libdir}/cronlib.php");
require_once($CFG->dirroot . '/repository/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false

    ], [
        'h' => 'help'
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Ad hoc cron tasks.

Options:
 -h, --help                Print out this help
     --showsql             Show sql queries before they are executed
     --showdebugging       Show developer level debugging information
 -e, --execute             Run all queued adhoc tasks
 -k, --keep-alive=N        Keep this script alive for N seconds and poll for new adhoc tasks
 -i  --ignorelimits        Ignore task_adhoc_concurrency_limit and task_adhoc_max_runtime limits

Example:
\$sudo -u www-data /usr/bin/php admin/cli/adhoc_task.php --execute

EOT;

    echo $help;
    die;
}


$curl = new curl();

$res = $curl->download_one("https://oakland.webex.com/mw3300/mywebex/nbrPrepare.do?siteurl=oakland&recordid=204951382&prepareTicket=SDJTSwAAAASTTbyfg6E7osQSF%2BAFBN4sFpWfY8sNC%2BdFv3vn4rOHJg%3D%3D&timestamp=1604020911350", []);
var_dump(strlen($res));
var_dump($curl->get_raw_response());
