<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$pageTitle = 'Booking';
include './includes/header.html';
//on submit, validate form
$booking_errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //validate email
    if (isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email = $_POST['email'];
    }else $booking_errors['email'] = 'Please enter a valid email address!';
    //validate name
    if (isset($_POST['name']) && preg_match('/^[\w \']+$/', $_POST['name']) && strlen(trim($_POST['name']))>=2) {
        $name = $_POST['name'];
    }else $booking_errors['name'] = 'Please enter your name!';
    //filter message
    if (isset($_POST['msg']) && strlen(trim($_POST['msg'])) > 10) {
        $msg = strip_tags($_POST['msg']);
        if (strlen($_POST['msg'])>65535) {
            $msg = substr($msg, 0, 65535);
        }
    }else $booking_errors['msg'] = 'Please enter a message!';
    //if no errors, send email
    if (empty($booking_errors)) {
        $from = "admin@jazzinaustin.com";
        $headers = "From: JazzInAustin <$from>";
        $msg = <<<EOT
From: $name, $email

$msg
EOT;
        mail(CONTACT_EMAIL, 'Booking Request', $msg, $headers, "-f " . $from);
        include './views/booking_success.html';
        include './includes/footer.html';
        exit();
    }
}
//display the form
require './includes/form_functions.inc.php';
include './views/booking_form.html';
include './includes/footer.html';
?>