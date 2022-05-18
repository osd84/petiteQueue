<?php

namespace PetitCitron\PetiteQueue;

use DateTime;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use PetitCitron\LightLogger\Logger;
use PetitCitron\LightLogger\NoLogger;

// oldschool bootstrap
if (!defined('ROOT')) {
    define('ROOT', dirname(dirname(__FILE__)));
}

class Queue
{

    protected $freshInstall = false;
    protected $settings = [];
    protected $lastJobId = null;
    protected $connexion = null;
    protected $log = null;
    private $conf = ['database' => ROOT . '/datas/queue.db', 'logging' => false];

    /**
     * $conf [
     *
     *  database => 'path/to/sqlite.db'
     *  logging => true / false
     *
     * ]
     *
     * @param $conf
     */
    public function __construct($conf = null)
    {
        $default = $this->conf;
        if (!empty($conf)) {
            $newConf = array_merge($default, $conf);
            $this->conf = $newConf;
        }
        $this->_install();
        $this->_setupLogger();
    }

    /**
     * Initialize Sqlite Db or open it if don't exist
     * @return bool
     */
    public function _install()
    {
        $con = $this->_getConn();
        $this->freshInstall = false;
        try {
            $con->execute('SELECT * FROM jobs LIMIT 2');
        }
        catch (\PDOException $e) {
            if ($e->getCode() === 'HY000') {
                $this->freshInstall = true;
            }
        }
        if ($this->freshInstall) {
            $query = $con->execute("CREATE TABLE IF NOT EXISTS `jobs` (
                                      `id` INTEGER PRIMARY KEY,
                                      `queue` VARCHAR(255) NOT NULL DEFAULT 'default',
                                      `data` TEXT NOT NULL,
                                      `locked` TINYINT NOT NULL DEFAULT '0',
                                      `attempts` INTEGER DEFAULT NULL,
                                      `created_at` DATETIME DEFAULT NULL)");
            if ($query->errorCode() !== '00000') {
                throw new \LogicException('Sqlite Db CREATE failed');
            }
            return true;
        }
        return false;
    }

    /**
     * Return Db $connexion
     * @return Connection
     */
    public function _getConn()
    {
        if (empty($this->connexion)) {
            $driver = new Sqlite([
                'database' => $this->conf['database'],
                'username' => 'root',
                'password' => 'secret',
            ]);
            $this->connexion = new Connection([
                'driver' => $driver,
            ]);
        }
        return $this->connexion;
    }

    /**
     * Initialize the stupid file logger system or silent it
     * @return void
     */
    public function _setupLogger()
    {
        if ($this->conf['logging']) {
            $this->log = new Logger(ROOT . '/logs/', 'petitequeue.log');
            $this->log->info('petitequeue:Queue - Log enabled');
        }
        else {
            $this->log = new NoLogger();
            $this->log->info('Nothing will be logged');
        }
    }


    /**
     * Add new Job/Task to queue list
     *
     *
     * @param $callable string|array What wee need to call
     *                              'myfunction' => For function call
     *                              'MyClass::static' => For Static Class call
     *                              ['MyClass', 'method'] => For new Object() creation + method call
     * @param $args array The array of args for the Callable
     *                    ['arg1','arg2']
     *
     * @param $options array  Other options, for now 'queue' name for filtering Jobs, default queue name is 'default'
     *                              [ 'queue' => 'critic' ]
     *
     * @return int|string|null
     */
    public function push($callable, $args = [], $options = [])
    {
        if (!is_array($options)) {
            $options = ['queue' => $options];
        }

        $datetime = new DateTime;
        $queue = $this->setting($options, 'queue', 'default');
        $con = $this->_getConn();

        $data = json_encode([
            'class' => $callable,
            'args' => $args,
            'queue_time' => microtime(true),
        ]);

        $q = $con->insert('jobs', [
            'queue' => $queue,
            'data' => $data,
            'locked' => 0,
            'attempts' => 0,
            'created_at' => $datetime->format('Y-m-d H:i:s'),
        ], [
            'string',
            'string',
            'integer',
            'integer',
            'datetime',
        ]);

        if ($q->rowCount() == 1) {
            $this->lastJobId = $q->lastInsertId();
            return $this->lastJobId;
        }

        return null;
    }

    public function setting($settings, $key, $default = null)
    {
        if (!is_array($settings)) {
            $settings = ['queue' => $settings];
        }

        $settings = array_merge($this->settings, $settings);

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * Execute all jobs found in Queue
     * Errors are catch
     * Callable of Jobs **must** return a !empty() out for being consider as success executed
     * Success jobs are drop from sqlite Db
     * Failed jobs are flag attempt = 1, locked = 1 and **stay** on sqlite Db
     *
     * @param $queue string Filtering execution only for some flagged queue jobs, ex : 'critic'
     *
     * @return array
     */
    public function run($queue = null)
    {
        $jobs = $this->jobs($queue);
        $gTotal = count($jobs);
        $gSuccess = 0;
        $gFailed = 0;
        $gLock = 0;

        foreach ($jobs as $job) {

            // skip locked jobs
            if ($job['locked'] == '1') {
                $gLock++;
                continue;
            }

            // lock current job
            $this->markLocked($job['id']);

            $success = $this->_perform($job);

            if ($success) {
                $gSuccess++;
            }
            else {
                $gFailed++;
            }

        }
        return ['success' => $gSuccess, 'failed' => $gFailed, 'total' => $gTotal, 'lock' => $gLock];
    }

    /**
     * Get One job from Queue
     * @param $jobId int|string
     *
     * @return mixed|null
     */
    public function job($jobId)
    {
        $con = $this->_getConn();

        $jobId = intval($jobId);
        $job = $con->get('SELECT * FROM jobs WHERE id = ?', [$jobId], ['integer']);
        if (empty($job)) {
            return null;
        }
        return $job;
    }

    /**
     * Force the "run", execution for a specific Job, even a 'locked' job
     * @param $jobId int|string
     *
     * @return bool
     */
    public function force($jobId)
    {
        $job = $this->job($jobId);
        if(empty($job)) {
            throw new \LogicException('Job not found, force fail');
        }
        return $this->_perform($job);
    }


    /**
     * Internal motor for job execution
     * @param $job array  a job entry of jobs Sqlite table
     *
     * @return bool
     */
    public function _perform($job)
    {
        $elemToCall = json_decode($job['data'], true);
        $success = false;

        // is static Class method
        if (is_array($elemToCall['class']) && count($elemToCall['class']) == 2) {
            $className = $elemToCall['class'][0];
            $methodName = $elemToCall['class'][1];
            $instance = new $className;
            try {
                $jobReturn = $instance->$methodName(...$elemToCall['args']);
                if (!empty($jobReturn)) {
                    $this->log->info("'$className'" . ' method was successful called');
                    $success = true;
                }
                else {
                    $this->log->error("'$className'" . ' method call failed, please make sure ' . $methodName . ' return something !');
                }
            }
            catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        }
        elseif (strpos($elemToCall['class'], '::')) {
            $infos = explode('::', $elemToCall['class']);
            $className = $infos[0];
            $methodName = $infos[1];
            try {
                $jobReturn = $className::$methodName(...$elemToCall['args']);
                if (!empty($jobReturn)) {
                    $this->log->info("'$className'" . ' static method was successful called');
                    $success = true;
                }
                else {
                    $this->log->error("'$className'" . ' static method call failed, please make sure ' . $methodName . ' return something !');
                }
            }
            catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        }
        // is basic Class method
        elseif (is_string($elemToCall['class']))
        {
            // is classic function
            $className = $elemToCall['class'];
            try {
                $jobReturn = call_user_func($className, ...$elemToCall['args']);
                if (!empty($jobReturn)) {
                    $this->log->info("'$className'" . ' function was successful called');
                    $success = true;
                }
                else {
                    $this->log->error("'$className'" . ' function call failed, please make sure ' . $className . ' return something !');
                }
            }
            catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        }
        else {
            $this->log->error('Oups, Job call failed. Make sure you import Class or function in scope !');
        }

        if ($success) {
            $this->drop($job['id']);
        }
        else {
            $this->addAttempts($job['id']);
        }

        return $success;
    }


    /**
     * Return all Jobs in Queue
     *
     * @param $queue string Filtering execution only for some flagged queue jobs, ex : 'critic'
     *
     * @return array
     */
    public function jobs($queue = null)
    {
        $con = $this->_getConn();

        if (empty($queue)) {
            $jobs = $con->getAll('SELECT * FROM jobs');
        }
        else {
            $jobs = $con->getAll('SELECT * FROM jobs WHERE queue = :queue',
                [$queue], ['string']);
        }

        if (empty($jobs)) {
            return [];
        }
        return $jobs;
    }

    /**
     * Flag the job as "Locked", it will be not exec on next "run()" call
     * @param $jobId int|string
     *
     * @return \Cake\Database\StatementInterface
     */
    public function markLocked($jobId)
    {
        $con = $this->_getConn();
        $jobId = intval($jobId);
        return $con->execute('UPDATE jobs SET locked = 1 WHERE id = ?', [$jobId], ['integer']);
    }

    /**
     * Remove job from Queue and Sqlite db
     * @param $jobId  int|string
     *
     * @return int 1 : success || 0 : fail
     */
    public function drop($jobId)
    {
        $con = $this->_getConn();
        $jobId = intval($jobId);
        return $con->delete('jobs', ['id' => $jobId], ['integer'])->count();
    }

    /**
     * Remove all jobs from Queue and Sqlite db Filtering by 'queue' flag
     * @param $queueName string
     *
     * @return int number of deleted jobs
     */
    public function flush($queueName)
    {
        $con = $this->_getConn();
        return $con->delete('jobs', ['queue' => trim($queueName)], ['string'])->count();
    }


    /**
     * Remove all jobs from Queue and Sqlite db
     *
     * @return int number of deleted jobs
     */
    public function clear()
    {
        $con = $this->_getConn();
        return $con->execute('DELETE FROM jobs WHERE 1')->count();
    }

    /**
     * Increment 'attempts' field / counter on Job Sqlite Db
     * @param $jobId int|string
     *
     * @return \Cake\Database\StatementInterface
     */
    public function addAttempts($jobId)
    {
        $con = $this->_getConn();
        $jobId = intval($jobId);
        return $con->execute('UPDATE jobs SET attempts = attempts + 1 WHERE id = ?', [$jobId], ['integer']);
    }

    /**
     * Return True if the current Queue have setup a new empty Sqlite db
     *
     * @return bool
     */
    public function isFreshInstall()
    {
        return $this->freshInstall;
    }
}
