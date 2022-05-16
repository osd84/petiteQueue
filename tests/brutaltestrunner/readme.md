# BrutalTestRunner

Minimalist PHP test runner.\
No composer, No deps, only simple file to include.

Is a BRUTAL DEV DESIGNED APP -> idea from [@4uto3!o6r4mm](http://autobiogramm.tuxfamily.org/brutalisme.html)


## Simple - only 3 methods :

```php
btrHeader(<test_name>) // print header in out
btrAssertEq(<expected_val>, <tested_val>, <info_msg>, <strict_mode_test_bool>) // Assert Equals
btrFooter() // print result in out and correct exit() code
```

## How To use ?

Exemple of tests :

```php 
<?php

require 'BrutalTestRunner.php'; // include lib file

btrHeader(__FILE__); // if you want pretty header in terminal

btrAssertEq(true, true, 'true == true'); // Only assertEqual test, it's minimalist
btrAssertEq(true, is_file(__FILE__), 'script is file');
btrAssertEq(true, false, 'true == false', true);
btrAssertEq(1, '1', "1 === '1'", false); // assertEqual no strict mode (default)
btrAssertEq(1, '1', "1 === '1' strict", true); // assertEqual with strict mode
btrAssertEq(true, 1, "true === 1 strict", true); // assertEqual with strict mode

btrFooter(); // if you want pretty footer n terminal AND good exit code success/fail
```

Result :
```shell
-----------
Brutal test Runner for [readme.php]
test 1 :: OK ✔ :: true == true
test 2 :: OK ✔ :: script is file
test 3 :: FAIL ✖ :: true == false
test 4 :: OK ✔ :: 1 === '1'
test 5 :: FAIL ✖ :: 1 === '1' strict
test 6 :: FAIL ✖ :: true === 1 strict

-----------
✖ [FAILED] 3 fails, 3 success, 6 total 
```

See code example in test.php :

```shell
php7.4  test.php
```

## Debug Mode

For more debug info you can "on" debug mode

```php
<?php

$GLOBALS['debug'] = true;
```

will stop in first failed test :

```shell
-----------
Brutal test Runner for [test.php]
test 1 :: OK ✔ :: script is file
test 2 :: OK ✔ :: true == true
test 3 :: OK ✔ :: 1 === '1'
test 4 :: FAIL ✖ :: 1 === '1' strict
    1 != 1 
---------------
EXPECT :
1
FOUND :
'1'
---------------
Tests FAILED
```

## Licence

```
 * Copyright (C) 2021  PetitCitron osd.ovh - https://github.com/PetitCitron
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
```