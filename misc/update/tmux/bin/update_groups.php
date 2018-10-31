<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\NNTP;
use App\Models\Group;
use Blacklight\ColorCLI;
use App\Models\ShortGroup;
use Blacklight\ConsoleTools;
use Illuminate\Support\Facades\DB;

$start = now();
$consoleTools = new ConsoleTools();
$colorCli = new ColorCLI();

// Create the connection here and pass
$nntp = new NNTP();
if ($nntp->doConnect() !== true) {
    $colorCli->error('Unable to connect to usenet.');
    exit();
}

$colorCli->header('Getting first/last for all your active groups.');
$data = $nntp->getGroups();
if ($nntp->isError($data)) {
    $colorCli->error('Failed to getGroups() from nntp server.');
    exit();
}

$colorCli->header('Inserting new values into short_groups table.');

DB::unprepared('TRUNCATE TABLE short_groups');
DB::commit();

// Put into an array all active groups
$result = array_pluck(Group::query()->where('active', '=', 1)->orWhere('backfill', '=', 1)->get(['name']), 'name');

foreach ($data as $newgroup) {
    if (\in_array($newgroup['group'], $result, false)) {
        ShortGroup::query()->insert([
                    'name' => $newgroup['group'],
                    'first_record' => $newgroup['first'],
                    'last_record' => $newgroup['last'],
                    'updated' => now(),
                ]);
        $colorCli->primary('Updated '.$newgroup['group']);
    }
}

$colorCli->header('Running time: '.now()->diffInSeconds($start).' seconds');
