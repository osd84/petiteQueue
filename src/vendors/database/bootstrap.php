<?php

/**
 * database from Cake
 */

// Loading Deps without Composer - Freeze deps

$path =  __DIR__ . '/vendor/autoload.php';

if (file_exists($path)) {
    require_once $path;
    return;
}

throw new \Exception('Composer autoloader could not be found. Install dependencies with `composer install` on /vendors/database and try again.');
