<?php

namespace osd84\PetiteQueue;

use DateTime;
use PetitCitron\LightLogger\Logger;
use PetitCitron\LightLogger\NoLogger;
use PDO;
use PDOException;

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
    private $conf = [
        'database' => ROOT . '/datas/queue.db',
        'logging' => false,
        'logfile' => 'petitequeue.log'
    ];

    public function __construct($conf = null)
    {
        if (!empty($conf)) {
            $this->conf = array_merge($this->conf, $conf);
        }
        $this->_install();
        $this->_setupLogger();
    }

    public function _getConn()
    {
        if (empty($this->connexion)) {
            try {
                $this->connexion = new PDO('sqlite:' . $this->conf['database']);
                $this->connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new \RuntimeException("Connexion à la base de données impossible : " . $e->getMessage());
            }
        }
        return $this->connexion;
    }

    public function _install()
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

    public function _setupLogger()
    {
        if ($this->conf['logging']) {
            $this->log = new Logger(ROOT . '/logs/', $this->conf['logfile']);
            $this->log->info('petitequeue:Queue - Log enabled');
        } else {
            $this->log = new NoLogger();
        }
    }

    public function push($callable, $args = [], $options = [])
    {
        if (!is_array($options)) {
            $options = ['queue' => $options];
        }

        $queue = $this->setting($options, 'queue', 'default');
        $data = json_encode([
            'class' => $callable,
            'args' => $args,
            'queue_time' => microtime(true),
        ]);

        $sql = "INSERT INTO jobs (queue, data, locked, attempts, created_at) 
                VALUES (:queue, :data, :locked, :attempts, :created_at)";

        $stmt = $this->_getConn()->prepare($sql);
        $result = $stmt->execute([
            ':queue' => $queue,
            ':data' => $data,
            ':locked' => 0,
            ':attempts' => 0,
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        if ($result && $stmt->rowCount() === 1) {
            $this->lastJobId = $this->_getConn()->lastInsertId();
            $this->log->info('petitequeue:Queue::push() - add Job ' . $data);
            return $this->lastJobId;
        }

        return null;
    }

    public function jobs($queue = null)
    {
        $sql = 'SELECT * FROM jobs';
        $params = [];

        if ($queue !== null) {
            $sql .= ' WHERE queue = :queue';
            $params[':queue'] = $queue;
        }

        $stmt = $this->_getConn()->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();

        if (empty($jobs)) {
            $this->log->info("petitequeue:Queue::jobs() - No Jobs Found");
            return [];
        }

        $this->log->info('petitequeue:Queue::jobs() - ' . count($jobs) .' Found');
        return $jobs;
    }

    public function markLocked($jobId)
    {
        $stmt = $this->_getConn()->prepare('UPDATE jobs SET locked = 1 WHERE id = :id');
        return $stmt->execute([':id' => intval($jobId)]);
    }

    public function drop($jobId)
    {
        $stmt = $this->_getConn()->prepare('DELETE FROM jobs WHERE id = :id');
        $this->log->info("petitequeue:Queue::drop() $jobId is drop");
        $stmt->execute([':id' => intval($jobId)]);
        return $stmt->rowCount();
    }

    public function flush($queueName)
    {
        $stmt = $this->_getConn()->prepare('DELETE FROM jobs WHERE queue = :queue');
        $this->log->info("petitequeue:Queue::flush() $queueName Jobs are drop");
        $stmt->execute([':queue' => trim($queueName)]);
        return $stmt->rowCount();
    }

    public function clear()
    {
        $this->log->info("petitequeue:Queue::clear() All Jobs are drop");
        return $this->_getConn()->exec('DELETE FROM jobs WHERE 1');
    }

    public function addAttempts($jobId)
    {
        $stmt = $this->_getConn()->prepare('UPDATE jobs SET attempts = attempts + 1 WHERE id = :id');
        return $stmt->execute([':id' => intval($jobId)]);
    }

    public function job($jobId)
    {
        $stmt = $this->_getConn()->prepare('SELECT * FROM jobs WHERE id = :id');
        $stmt->execute([':id' => intval($jobId)]);
        $job = $stmt->fetch();

        if (empty($job)) {
            $this->log->info("petitequeue:Queue::job() - Job $jobId NotFound");
            return null;
        }
        return $job;
    }

    public function setting($settings, $key, $default = null)
    {
        if (!is_array($settings)) {
            $settings = ['queue' => $settings];
        }
        $settings = array_merge($this->settings, $settings);
        return $settings[$key] ?? $default;
    }

    // Les autres méthodes restent identiques car elles n'utilisent pas directement la base de données
    public function isFreshInstall()
    {
        return $this->freshInstall;
    }
}