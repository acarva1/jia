<?php
require './includes/config.inc.php';
$pageTitle = 'Reset Password';
include './includes/header.html';
//check that user is not logged in
if (!isset($_SESSION['id'])) {
    //validate email from form
    $reset_errors = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $email = $_POST['email'];
        }else $reset_errors['email'] = 'Please enter your email address!';
        //if no errors, get user id and create auth_token
        if (empty($reset_errors)) {
            require MYSQL;
            $q = "SELECT id FROM users WHERE email=?";
            $stmt = $dbc->prepare($q);
            if ($stmt->execute(array($email)) && $uid = $stmt->fetchColumn()) {
                //generate random token string
                $token = openssl_random_pseudo_bytes(32);
                $token = bin2hex($token);
                //store token in DB
                $q = 'REPLACE INTO auth_tokens (user_id, token, expires)
                VALUES (?,?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))';
                $stmt = $dbc->prepare($q);
                if ($stmt->execute(array($uid, $token)) && $stmt->rowCount() > 0) {
                    //Send email with instructions and token link
                    $from = "admin@jazzinaustin.com";
                    $headers = "From: JazzInAustin <$from>";
                    $url = 'http://' . BASE_URL . 'password.php?t=' . $token;
                    $body = <<<EOT
This email is in response to a forgotten password reset request at 'Jazz in Austin'. If you did make this request, click the following link to be able to access your account:
$url
If you do not use this link to reset your password within 15 minutes, you'll need to request a password reset again.
If you have _not_ forgotten your password, you can safely ignore this message and you will still be able to login with your existing password.
EOT;
                    mail($email, 'Password Reset Request', $body, $headers, '-f '.$from);
                    //display the instruction message
                    echo '<h2>Reset Your Password</h2><p>You will receive an access code via email. Click the link in that email to gain access to the site. You can then change your password.</p>';
                    include './includes/footer.html';
                    exit();
                }else trigger_error('An internal error has occured. We apologize for the inconvenience.');
            }else{
                $reset_errors['email'] = 'Please enter your email address!';
            }
        }
    }
    //show the form
    require './includes/form_functions.inc.php';
    include './views/forgotpw_form.html';
}else{
    echo '<div class="centeredDiv"><h2>You are already logged in!</h2></div>';
}
include './includes/footer.html';
?>