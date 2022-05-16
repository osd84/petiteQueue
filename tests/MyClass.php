<?php

class MyClass
{

    public static function simpleMethod($arg1, $args2) {
        return $arg1 . ' ' . $args2;
    }

    public static function staticMethod($arg1, $args2) {
        return $arg1 . ' ' . $args2;
    }
}

function myFunction($arg1, $args2) {
    return $arg1 . ' ' . $args2;
}

function myFunctionWhoFail() {
    throw new Exception('I fail ...');
}

function myFunctionWhoFailBecauseNoReturn() {
    return null;
}