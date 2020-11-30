<?php

require_once __DIR__."/vendor/autoload.php";

function classLoader($class){
    $class = str_replace('Ixibei\Spider','',$class);
    $file = __DIR__.'/src/'.$class.'.php';
    if(file_exists($file)){
        require_once $file;
    }
}

spl_autoload_register('classLoader');