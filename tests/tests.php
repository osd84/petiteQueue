<?php

use petitcitron\PetiteQueue\Queue;



// oldschool bootstrap
define('ROOT', dirname(__FILE__, 2));
require_once(ROOT . '/src/bootstrap.php');

// setup BrutalTestRunner
require_once(ROOT .  '/tests/brutaltestrunner/BrutalTestRunner.php');
$GLOBALS['debug'] = false; // default
btrHeader(__FILE__);

// setup fake bdd
$testBdd = ROOT . '/datas/queue-test.db';
if(file_exists($testBdd)) {
    unlink($testBdd);
}

// 1st running
$queue = new Queue(['database' => $testBdd, 'logging' => true]);
$feshInstall = $queue->isFreshInstall();
btrAssertEq(true, $feshInstall, 'Detect as a Fresh DB sqlite install');
// log system work
btrAssertEq(true, file_exists(ROOT . '/logs/petitequeue.log'), 'Log system work');

// push in queue
$jobId = $queue->push('MyClass::staticMethod', ['some','args']);
btrAssertEq(1, $jobId, 'push as jobs.id 1');
$jobId = $queue->push('myFunction', ['some','args']);
btrAssertEq(2, $jobId, 'push as jobs.id 2');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
btrAssertEq(3, $jobId, 'push as jobs.id 3');

// get jobs list
$jobs = $queue->jobs();
$jobsIds = array_column($jobs, 'id');
btrAssertEq(['1','2','3'], $jobsIds, 'We have 3 jobs in queue');

// get jobs by queue filter
$jobs = $queue->jobs('critic');
$jobsIds = array_column($jobs, 'id');
btrAssertEq(['3'], $jobsIds, "We have 1 job in queue 'critic'");
$jobs = $queue->jobs('default');
btrAssertEq(2, count($jobs), "We have 2 job in queue 'default'");

// running queue
// We need to import Classe, function and other object used by Queue here
require_once (ROOT . '/tests/MyClass.php');

$result = $queue->run();
btrAssertEq(['success' => 3, 'failed' => 0, 'total' => 3, 'lock' => 0], $result, 'All jobs are successfully run');
$jobs = $queue->jobs();
btrAssertEq(0, count($jobs), "Queue jobs list is empty");

// test with a failed jobs
$jobId = $queue->push('myFunctionWhoFail');
$jobId = $queue->push('myFunctionWhoFailBecauseNoReturn');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->run();
btrAssertEq(['success' => 1, 'failed' => 2, 'total' => 3, 'lock' => 0], $result, 'All jobs are run with 2 fails');
$jobs = $queue->jobs();
$locked = count(array_filter($jobs, function($x) { return $x['locked'] == '1'; }));
btrAssertEq($locked, 2, 'Failed jobs are lock');
$attempts = count(array_filter($jobs, function($x) { return $x['attempts'] == '1'; }));
btrAssertEq($locked, 2, 'Failed jobs are attempts flag');
$result = $queue->run();
btrAssertEq(['success' => 0, 'failed' => 0, 'total' => 2, 'lock' => 2], $result, 'All locked jobs are skip');
$jobs = $queue->jobs();
$force = $queue->force($jobs[0]['id']);
btrAssertEq(false, $force, 'Try to force Failed jobs, also fail');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$force = $queue->force($jobId);
btrAssertEq(true, $force, 'Force a jobs success');
btrAssertEq(2, count($queue->jobs()), 'Failed job still here');

// drop 1 job
$result = $queue->drop(1);
btrAssertEq(1, $result, 'Force drop a job');
btrAssertEq(1, count($queue->jobs()), 'Job successful deleted');

// clear all jobs
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->clear();
btrAssertEq(2, $result, 'Clear all jobs');
btrAssertEq(0, count($queue->jobs()), 'Jobs successful deleted');

// flush all jobs queue
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args']);
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->flush('critic');
btrAssertEq(2, $result, "Flush all queue 'critic'");
btrAssertEq(1, count($queue->jobs()), 'Jobs successful deleted');

// print test results
btrFooter();

