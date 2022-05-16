
# petiteQueue

A stupid and brutal **job/worker system** library only using Sqlite.<br>
Small manually freezed deps. <br>
Lightly inpired by <a href="https://github.com/josegonzalez/php-queuesadilla">php-queuesadilla</a><br><br>
<br>
**Security** : Never expose this lib on /webroot directory, it was no coded for that. Keep it in no browsable dir.

## No Composer / No PSR firendly

**No composer**, because i don't like it.<br>
I don't care about PSR too.  🤷‍♂️ 


## Requirements

- PHP 7.4+
- php_pdo_sqlite

## Installation


1  . Unzip the Lib <br>
2  . Import And Play

Si more in /tests/tests.php

```php
<?php
use petitcitron\PetiteQueue\Queue;

define('ROOT', dirname(__FILE__, 2));
require_once(ROOT . '/src/petitcitron/petitequeue/Queue.php');

$myQueue = new Queue(['database' => $testBdd, 'logging' => true]);
$myQueue->push('MyClass::method', ['some','args'])

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