<?php
session_start();
spl_autoload_register(function ($class_name) {
    $class_name = __DIR__.'/'.$class_name . '.php';
    $class_name = str_replace('\\','/',$class_name);
    require_once $class_name;
});
