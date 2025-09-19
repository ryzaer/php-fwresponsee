<?php
spl_autoload_register(function($class) {  
    $root = preg_replace('~[\\\]~','/',__DIR__);
    $file = $root."/classes";
    $find = preg_split('~[\\\]~',$class);     
    $make = $find[count($find)-1];      
    unset($find[count($find)-1]);   
    $newcc = implode('/',$find); 
    $space = $file.($newcc ? '/'.$newcc : null);  
    require_once "$space/$make.php";   
});