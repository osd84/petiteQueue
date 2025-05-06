
# petiteQueue

A stupid and brutal **job/worker system** library only using Sqlite.<br>
Lightly inpired by <a href="https://github.com/josegonzalez/php-queuesadilla">php-queuesadilla</a><br><br>
<br>
**Security** : Never expose this lib on /webroot directory, it was no coded for that. Keep it in no browsable dir.

## Install

```sh
composer require osd84/petitequeue
```



## Requirements

- PHP 7.4+
- php_pdo_sqlite

## Stable


```sh
wget https://github.com/PetitCitron/petiteQueue/archive/refs/tags/v1.0.0.zip
unzip v1.0.0.zip
cd petiteQueue-1.0.0/
composer install --no-dev
```

As a Lib, Add in composer.json

```json
"require": {
    "petitcitron/petitequeue": "^v1"
  },
"repositories": [
{
  "type": "vcs",
  "url": "https://github.com/PetitCitron/petiteQueue"
}
   ],
```
```sh
composer update
```

## Usage


```php
<?php
use osd84\PetiteQueue\Queue;

require dirname(__DIR__) . '/vendor/autoload.php';

$myQueue = new Queue(['database' => $testBdd, 'logging' => true]);
$myQueue->push('myFunction', ['some','args'])
$myQueue->push('MyClass::staticMethod', ['some','args'])
$myQueue->push(['MyClass','method'], ['some','args'])

$myQueue->jobs() // list waiting jobs
$myQueue->run() // run All jobs
$myQueue->force($jobId) // run 1 jobs (even locked job)
$myQueue->drop($jobId) // drop 1 job
$myQueue->flush('critic') // clear All flagged queue jobs
$myQueue->clear() // clear All
```

## Usage & Tests


Simple Tests by running <br>
Read this file for Know how use this lib.

```sh
php7.4 tests/tests.php
```

Out of test must be :

```log
/usr/bin/php7.4 ./tests/tests.php

-----------
Brutal test Runner for [tests.php]
test 1 :: OK ✔ :: Detect as a Fresh DB sqlite install
test 2 :: OK ✔ :: Log system work
test 3 :: OK ✔ :: push as jobs.id 1
test 4 :: OK ✔ :: push as jobs.id 2
test 5 :: OK ✔ :: push as jobs.id 3
test 6 :: OK ✔ :: We have 3 jobs in queue
test 7 :: OK ✔ :: We have 1 job in queue 'critic'
test 8 :: OK ✔ :: We have 2 job in queue 'default'
test 9 :: OK ✔ :: All jobs are successfully run
test 10 :: OK ✔ :: Queue jobs list is empty
test 11 :: OK ✔ :: All jobs are run with 2 fails
test 12 :: OK ✔ :: Failed jobs are lock
test 13 :: OK ✔ :: Failed jobs are attempts flag
test 14 :: OK ✔ :: All locked jobs are skip
test 15 :: OK ✔ :: Try to force Failed jobs, also fail
test 16 :: OK ✔ :: Force a jobs success
test 17 :: OK ✔ :: Failed job still here
test 18 :: OK ✔ :: Force drop a job
test 19 :: OK ✔ :: Job successful deleted
test 20 :: OK ✔ :: Clear all jobs
test 21 :: OK ✔ :: Jobs successful deleted
test 22 :: OK ✔ :: Flush all queue 'critic'
test 23 :: OK ✔ :: Jobs successful deleted

-----------
✔ [SUCCESS] 0 fails, 23 success, 23 total 
Process finished with exit code 0
```