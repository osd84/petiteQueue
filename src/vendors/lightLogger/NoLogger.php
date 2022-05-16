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
 * null
 */
class NoLogger
{


    public function __construct()
    {

    }

    /**
     * Writes to log file before class is unloaded from memory
     */
    public function __destruct()
    {

    }

    /**
     * Use overloading to for dynamic log types
     *
     * @param string $method
     * @param array  $args
     *
     * @return null
     */
    public function __call($method, $args)
    {
        return null;
    }


}
