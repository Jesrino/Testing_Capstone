<?php
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'booking_system';

    $conn = new mysqli($host, $user, $pass, $db);


    if($conn->connect_error){
        die('Database Connection Failed:' . $conn->connect_error );
    }

    if (session_status()===PHP_SESSION_NONE){
        session_start();
    }

?>