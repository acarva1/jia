<?php

$host = substr($_SERVER['HTTP_HOST'], 0, 5);
if (in_array($host, array('local', '127.0', '192.1'))) { //determine if host is local or on the server.
    $local = true;
}else{
    $local = false;
}

//errors are emailed here:
define('CONTACT_EMAIL', 'aaronallen8455@gmail.com');

if($local) {
    define('BASE_URI', 'file:///C:/xampp/htdocs/jia-master');
    define('BASE_URL', 'localhost/jia-master/');
    define('MYSQL', BASE_URI . '/includes/mysql.inc.php');
}else{//live
    define('BASE_URI', 'home/pronzneu/www/jazzinaustin.com');
    define('BASE_URL', 'jazzinaustin.com/');
    define('MYSQL', '/home/pronzneu/mysqljia.inc.php'); //SQL config is outside of webdir on live    
}

//start the session
session_start();
//create error handler
function my_error_handler($e_number, $e_message, $e_file, $e_line, $e_vars) {
    global $local;
    //build the error message
    $message = "An error occured in script '$e_file' on line $e_line:\n$e_message\n";
    //add the backtrace
    $message .= "<pre>" . print_r(debug_backtrace(), 1) . "</pre>\n";
    //show message if not live
    if ($local) {
        echo '<div class="error">' . nl2br($message) . '</div>';
    }else{
        //send the error in an email
        error_log($message, 1, CONTACT_EMAIL, 'From:admin@jazzinaustin.com');
        //only print message in browser if error isn't a notice
        if ($e_number != E_NOTICE) {
            echo '<div class="error">A system error occured. We apologize for the inconvenience.</div>';
        }
    }
    return true; //so that php doesn't try to handle the error too.
}

//use the error handler
set_error_handler('my_error_handler');

//utility function for parsing time values from the DB
function parseTime($str) {
    $hour = (int)substr($str,0,2);
    if ($hour >= 12) {
        if ($hour !== 12)
            $hour -=12;
        $period = 'pm';
    }else{
        $period = 'am';
        if ($hour === 0)
            $hour = 12; //midnight
    } 
    $min = substr($str,-2);
    return $hour.':'.$min.$period;
}