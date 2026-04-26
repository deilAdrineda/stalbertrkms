<?php
    $dbname = 'samadc';
    $host = '127.0.0.1';
    $root =  'root';
    $password = '';

    $con = mysqli_connect($host, $root, $password, $dbname);

    if(!$con){
        die("Could not CONNECT properly");
    }
?>