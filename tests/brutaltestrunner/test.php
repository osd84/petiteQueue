<?php

require 'BrutalTestRunner.php';

$GLOBALS['debug'] = false; // default

btrHeader(__FILE__);
btrAssertEq(true, is_file(__FILE__), 'script is file');
btrAssertEq(true, true, 'true == true');
btrAssertEq(1, '1', "1 === '1'", false);
btrAssertEq(1, '1', "1 === '1' strict", true);
btrAssertEq(true, 1, "true === 1 strict", true);
btrAssertEq(true, false, 'true == false', true);
btrFooter();
