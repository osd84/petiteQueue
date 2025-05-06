<?php

namespace osd84\PetiteQueue;

use DateTime;
use PDO;
use PDOException;
use osd84\LightLogger\Logger;
use osd84\LightLogger\NoLogger;

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
    private $conf = ['database' => ROOT . '/datas/queue.db', 'logging' => false, 'logfile' => 'petitequeue.log'];

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
    public function _install(): bool
    {
        $con = $this->_getConn();
        $this->freshInstall = false;
        try {
            $r = $con->query('SELECT * FROM jobs LIMIT 2');
        } catch (PDOException $e) {
            if ($e->getCode() === 'HY000') {
                $this->freshInstall = true;
            }
        }

        if ($this->freshInstall) {
            $sql = "CREATE TABLE IF NOT EXISTS `jobs` (
                `id` INTEGER PRIMARY KEY,
                `queue` VARCHAR(255) NOT NULL DEFAULT 'default',
                `data` TEXT NOT NULL,
                `locked` TINYINT NOT NULL DEFAULT '0',
                `attempts` INTEGER DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL
            )";

            $r = $con->exec($sql);
            $error = $con->errorInfo();
            if ($error[0] !== '00000') {
                throw new \LogicException('Création de la base SQLite échouée');
            }
            return true;
        }
        return false;
    }


    public function _getConn(): PDO
    {
        if (empty($this->connexion)) {
            try {
                $dsn = 'sqlite:' . $this->conf['database'];
                $this->connexion = new PDO($dsn);
                $this->connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new \RuntimeException('Impossible de se connecter à la base de données : ' . $e->getMessage());
            }
        }
        return $this->connexion;
    }

    /**
     * Initialize the stupid file logger system or silent it
     */
    public function _setupLogger(): void
    {
        if ($this->conf['logging']) {
            $this->log = new Logger(ROOT . '/logs/', $this->conf['logfile']);
            $this->log->info('petitequeue:Queue - Log enabled');
        } else {
            $this->log = new NoLogger();
            $this->log->info('Nothing will be logged');
        }
    }


    /**
     * Add new Job/Task to queue list
     *
     *
     * @param $callable array|string What wee need to call
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
    public function push(array|string $callable, array $args = [], array $options = [])
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

        $sql = "INSERT INTO jobs (queue, data, locked, attempts, created_at) 
                VALUES (:queue, :data, :locked, :attempts, :created_at)";

        $stmt = $con->prepare($sql);

        $stmt->execute([
            ':queue' => $queue,
            ':data' => $data,
            ':locked' => 0,
            ':attempts' => 0,
            ':created_at' => $datetime->format('Y-m-d H:i:s')
        ]);

        if ($stmt->rowCount() == 1) {
            $this->lastJobId = $con->lastInsertId();
            $this->log->info('petitequeue:Queue::push() - add Job ' . $data);
            return $this->lastJobId;
        }

        return null;
    }

    public function setting(?array $settings, string $key, ?string $default = null)
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
     * @param $queue string|null Filtering execution only for some flagged queue jobs, ex : 'critic'
     *
     * @return array
     */
    public function run(string $queue = null): array
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
            } else {
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
    public function job(int|string $jobId): ?array
    {
        $con = $this->_getConn();
        $jobId = intval($jobId);

        $stmt = $con->prepare('SELECT * FROM jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);

        $job = $stmt->fetch();

        if ($job === false) {
            $this->log->info("petitequeue:Queue::job() - Job $jobId NotFound ");
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
    public function force(int|string $jobId): bool
    {
        $job = $this->job($jobId);
        if (empty($job)) {
            throw new \LogicException('Job not found, force fail');
        }
        $this->log->info("petitequeue:Queue::force() - Job $jobId");
        return $this->_perform($job);
    }


    /**
     * Internal motor for job execution
     * @param $job array  a job entry of jobs Sqlite table
     *
     * @return bool
     */
    public function _perform(array $job): bool
    {
        $this->log->info("petitequeue:Queue::_perform() - trying {$job['id']} Job");

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
                } else {
                    $this->log->error("'$className'" . ' method call failed, please make sure ' . $methodName . ' return something !');
                }
            } catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        } elseif (strpos($elemToCall['class'], '::')) {
            $infos = explode('::', $elemToCall['class']);
            $className = $infos[0];
            $methodName = $infos[1];
            try {
                $jobReturn = $className::$methodName(...$elemToCall['args']);
                if (!empty($jobReturn)) {
                    $this->log->info("'$className'" . ' static method was successful called');
                    $success = true;
                } else {
                    $this->log->error("'$className'" . ' static method call failed, please make sure ' . $methodName . ' return something !');
                }
            } catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        } // is basic Class method
        elseif (is_string($elemToCall['class'])) {
            // is classic function
            $className = $elemToCall['class'];
            try {
                $jobReturn = call_user_func($className, ...$elemToCall['args']);
                if (!empty($jobReturn)) {
                    $this->log->info("'$className'" . ' function was successful called');
                    $success = true;
                } else {
                    $this->log->error("'$className'" . ' function call failed, please make sure ' . $className . ' return something !');
                }
            } catch (\Exception $e) {
                $this->log->error("'$className' FAIL ! " . $e->getMessage());
            }

        } else {
            $this->log->error('Oups, Job call failed. Make sure you import Class or function in scope !');
        }

        if ($success) {
            $this->drop($job['id']);
        } else {
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
    public function jobs(?string $queue = null): array
    {
        $con = $this->_getConn();

        if (empty($queue)) {
            $stmt = $con->prepare('SELECT * FROM jobs');
            $stmt->execute();
        } else {
            $stmt = $con->prepare('SELECT * FROM jobs WHERE queue = :queue');
            $stmt->execute([':queue' => $queue]);
        }

        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($jobs)) {
            $this->log->info('petitequeue:Queue::jobs() - Aucun job trouvé');
            return [];
        }

        $this->log->info('petitequeue:Queue::jobs() - ' . count($jobs) . ' job(s) trouvé(s)');
        return $jobs;

    }

    /**
     * Flag the job as "Locked", it will be not exec on next "run()" call
     * @param $jobId int|string
     *
     */
    public function markLocked(int $jobId): int
    {
        $con = $this->_getConn();

        $stmt = $con->prepare('UPDATE jobs SET locked = 1 WHERE id = :id');
        $stmt->execute([':id' => intval($jobId)]);

        return $stmt->rowCount();
    }


    /**
     * Remove job from Queue and Sqlite db
     * @param $jobId  int|string
     *
     * @return int 1 : success || 0 : fail
     */
    public function drop(int $jobId): int
    {
        $con = $this->_getConn();

        $stmt = $con->prepare('DELETE FROM jobs WHERE id = :id');
        $stmt->execute([':id' => intval($jobId)]);

        $this->log->info("petitequeue:Queue::drop() - Job #{$jobId} supprimé");

        return $stmt->rowCount();
    }

    /**
     * Remove all jobs from Queue and Sqlite db Filtering by 'queue' flag
     * @param $queueName string
     *
     * @return int number of deleted jobs
     */
    public function flush(string $queueName): int
    {
        $con = $this->_getConn();

        $stmt = $con->prepare('DELETE FROM jobs WHERE queue = :queue');
        $stmt->execute([':queue' => trim($queueName)]);

        $nbDeleted = $stmt->rowCount();
        $this->log->info("petitequeue:Queue::flush() - {$nbDeleted} jobs supprimés de la file '{$queueName}'");

        return $nbDeleted;
    }


    /**
     * Remove all jobs from Queue and Sqlite db
     *
     * @return int number of deleted jobs
     */
    public function clear(): int
    {
        $con = $this->_getConn();

        $stmt = $con->prepare('DELETE FROM jobs');
        $stmt->execute();

        $nbDeleted = $stmt->rowCount();
        $this->log->info("petitequeue:Queue::clear() - {$nbDeleted} jobs supprimés");

        return $nbDeleted;
    }


    /**
     * Increment 'attempts' field / counter on Job Sqlite Db
     * @param $jobId int|string
     *
     */
    public function addAttempts(int $jobId): int
    {
        $con = $this->_getConn();

        $stmt = $con->prepare('UPDATE jobs SET attempts = attempts + 1 WHERE id = :id');
        $stmt->execute([':id' => intval($jobId)]);

        return $stmt->rowCount();
    }

    /**
     * Return True if the current Queue have setup a new empty Sqlite db
     *
     * @return bool
     */
    public function isFreshInstall(): bool
    {
        return $this->freshInstall;
    }
}
