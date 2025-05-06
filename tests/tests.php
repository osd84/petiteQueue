<?php

use PetitCitron\BrutalTestRunner\BrutalTestRunner;
use osd84\PetiteQueue\Queue;

require dirname(__DIR__) . '/vendor/autoload.php';

// oldschool bootstrap

// setup BrutalTestRunner
// import test tool
$btr = new BrutalTestRunner(true);
$btr->header(__FILE__);


// setup fake bdd
define('ROOT2', dirname(__FILE__, 2));
$testBdd = ROOT2 . '/datas/queue-test.db';
if(file_exists($testBdd)) {
    unlink($testBdd);
}

// 1st running
$queue = new Queue(['database' => $testBdd, 'logging' => true, 'logfile' => 'petitequeue-custom.log']);
$feshInstall = $queue->isFreshInstall();
$btr->assertEqual(true, $feshInstall, 'Detect as a Fresh DB sqlite install');

// push in queue
$jobId = $queue->push('MyClass::staticMethod', ['some','args']);
$btr->assertEqual(1, $jobId, 'push as jobs.id 1');
$jobId = $queue->push('myFunction', ['some','args']);
$btr->assertEqual(2, $jobId, 'push as jobs.id 2');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$btr->assertEqual(3, $jobId, 'push as jobs.id 3');

// get jobs list
$jobs = $queue->jobs();
$jobsIds = array_column($jobs, 'id');
$btr->assertEqual(['1','2','3'], $jobsIds, 'We have 3 jobs in queue');

// get jobs by queue filter
$jobs = $queue->jobs('critic');
$jobsIds = array_column($jobs, 'id');
$btr->assertEqual(['3'], $jobsIds, "We have 1 job in queue 'critic'");
$jobs = $queue->jobs('default');
$btr->assertEqual(2, count($jobs), "We have 2 job in queue 'default'");

// running queue
// We need to import Classe, function and other object used by Queue here
require_once (ROOT2 . '/tests/MyClass.php');

$result = $queue->run();
$btr->assertEqual(['success' => 3, 'failed' => 0, 'total' => 3, 'lock' => 0], $result, 'All jobs are successfully run');
$jobs = $queue->jobs();
$btr->assertEqual(0, count($jobs), "Queue jobs list is empty");

// test with a failed jobs
$jobId = $queue->push('myFunctionWhoFail');
$jobId = $queue->push('myFunctionWhoFailBecauseNoReturn');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->run();
$btr->assertEqual(['success' => 1, 'failed' => 2, 'total' => 3, 'lock' => 0], $result, 'All jobs are run with 2 fails');
$jobs = $queue->jobs();
$locked = count(array_filter($jobs, function($x) { return $x['locked'] == '1'; }));
$btr->assertEqual($locked, 2, 'Failed jobs are lock');
$attempts = count(array_filter($jobs, function($x) { return $x['attempts'] == '1'; }));
$btr->assertEqual($locked, 2, 'Failed jobs are attempts flag');
$result = $queue->run();
$btr->assertEqual(['success' => 0, 'failed' => 0, 'total' => 2, 'lock' => 2], $result, 'All locked jobs are skip');
$jobs = $queue->jobs();
$force = $queue->force($jobs[0]['id']);
$btr->assertEqual(false, $force, 'Try to force Failed jobs, also fail');
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$force = $queue->force($jobId);
$btr->assertEqual(true, $force, 'Force a jobs success');
$btr->assertEqual(2, count($queue->jobs()), 'Failed job still here');

// drop 1 job
$result = $queue->drop(1);
$btr->assertEqual(1, $result, 'Force drop a job');
$btr->assertEqual(1, count($queue->jobs()), 'Job successful deleted');

// clear all jobs
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->clear();
$btr->assertEqual(2, $result, 'Clear all jobs');
$btr->assertEqual(0, count($queue->jobs()), 'Jobs successful deleted');

// flush all jobs queue
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args']);
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$jobId = $queue->push(['MyClass', 'simpleMethod'], ['some','args'], ['queue' => 'critic']);
$result = $queue->flush('critic');
$btr->assertEqual(2, $result, "Flush all queue 'critic'");
$btr->assertEqual(1, count($queue->jobs()), 'Jobs successful deleted');

// log system work
$btr->assertEqual(true, file_exists(ROOT2 . '/logs/petitequeue-custom.log'), 'Log system work');

// print test results
$btr->footer();

