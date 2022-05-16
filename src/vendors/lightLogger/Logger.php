<?php
/**
 * Super simple class for logging. Generates regular logs and csv files
 *
 * @package            lightLogger
 * @author             PetitCitron https://github.com/PetitCitron
 * @Orignalauthor      Kevin Chappell <kevin.b.chappell@gmail.com>
 * @license            http://opensource.org/licenses/MIT The MIT License (MIT)
 * @since              lightLogger .5
 */

namespace petitcitron\lightLogger;


/**
 * # Log Usage
 * $log = new Logger();
 * $log->error( 'Something went wrong' );
 * ## Output
 * Jun-19-2015 05:53:32 | Error: Something went wrong
 *
 * # CSV Usage
 * $csv = new Logger('my.csv');
 * $row = array( 'name', 'email', 'phone' );
 * $csv->add_row( $row );
 * ## Output
 * name,email,phone
 */
class Logger
{

    private $logFile;
    private $log = [];

    /**
     * define the log file to be used
     *
     * @param string $filename
     */
    public function __construct($logDir = null, $filename = null)
    {
        if (empty($logDir)) {
            $logDir = __DIR__ . '/logs/';
        }
        if($logDir[-1] != '/') {
            $logDir .= '/';
        }
        if (empty($filename)) {
            $filename = 'app.log';
        }
        if (!file_exists($logDir)) {
            mkdir($logDir, 0700, true);
        }
        $this->logFile = $logDir . $filename;
    }

    /**
     * Writes to log file before class is unloaded from memory
     */
    public function __destruct()
    {
        $log_file = fopen($this->logFile, 'a');
        if (!empty($this->log)) {
            $this->log[] = '';
            $log = implode(PHP_EOL, $this->log);
            if (!fwrite($log_file, $log)) {
                chmod($this->logFile, 0600);
                $log_file = fopen($this->logFile, 'a');
                if (!fwrite($log_file, $log)) {
                    // log a problem with the logger
                    error_log('Could not write to ' . $log_file, 0);
                }
            }
            fclose($log_file);
        }
    }

    /**
     * Use overloading to for dynamic log types
     *
     * @param string $method
     * @param array  $args
     *
     * @return bool       returns add_log()
     */
    public function __call($method, $args)
    {
        return $this->addLog(ucfirst($method), $args[0]);
    }

    /**
     * Add the timestamped log
     *
     * @param string $type log type
     * @param string $text text to be logged
     */
    public function addLog($type, $text)
    {
        $date = date('M-d-Y H:i:s');
        $this->log[] = "$date | $type: $text";
        return true;
    }

    /**
     * add a row to a csv file
     *
     * @param array $columns array of columns to be added to the CSV
     */
    public function addRow($columns)
    {
        $columns = array_map(function ($column) {
            return addslashes($column);
        }, $columns);
        $this->log[] = implode(',', $columns);
        return true;
    }

}
