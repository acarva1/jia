<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
require_once MYSQL;
$pageTitle = 'Register';
include './includes/header.html';
require './includes/form_functions.inc.php';

//exit if user is logged in
if (isset($_SESSION['id'])) {
    echo '<div class="centeredDiv"><h2>You already have an account!</h2></div>';
    include './includes/footer.html';
    exit();
}
//check for account validation code
if (isset($_GET['vc']) && (strlen($_GET['vc']) === 12)) {
    $vc = $_GET['vc'];
    $q = 'UPDATE users SET type=\'u\' WHERE validation_code=?';
    $stmt = $dbc->prepare($q);
    if ($stmt->execute(array($vc))) {
        //show success message
        include './views/register_success.html';
        include './includes/footer.html';
        exit();
    }else{
        trigger_error('A system error has occured!');
    }
}
//if form was submitted
$reg_errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //validate first name
    if (isset($_POST['first']) && preg_match('/^[a-zA-Z\'\-]{2,45}$/', $_POST['first'])) {
        $first = $_POST['first'];
    }else{
        $reg_errors['first'] = 'Please enter your first name!';
    }
    //validate last name
    if (isset($_POST['last']) && preg_match('/^[a-zA-Z\'\-]{2,45}$/', $_POST['last'])) {
        $last = $_POST['last'];
    }else{
        $reg_errors['last'] = 'Please enter your last name!';
    }
    //validate email
    if (isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email = $_POST['email'];
    }else{
        $reg_errors['email'] = 'Please enter your email address!';
    }
    //validate password
    if (isset($_POST['pass1']) && preg_match('/^\w*(?=\w*[A-Z])(?=\w*\d)(?=\w*[a-z])\w*$/', $_POST['pass1']) && strlen($_POST['pass1'])>=6) {
        if ($_POST['pass1'] === $_POST['pass2']) {
            $pass = $_POST['pass1'];
        }else{
            $reg_errors['pass2'] = 'Passwords don\'t match!';
        }
    }else{
        $reg_errors['pass1'] = 'Please enter a valid password!';
    }
    //if no errors, create the DB row.
    if (empty($reg_errors)) {
        //check if email already exists
        $q = 'SELECT id FROM users WHERE email=?';
        $stmt = $dbc->prepare($q);
        $stmt->execute(array($email));
        if (empty($stmt->fetch())) {
            $vc = bin2hex(openssl_random_pseudo_bytes(6));
            $q = 'INSERT INTO users (first_name, last_name, email, pass, validation_code) VALUES (?,?,?,?,?)';
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($first,$last,$email, password_hash($pass, PASSWORD_BCRYPT), $vc));
            if ($stmt->rowCount() === 1) {
                //send confirmation email
                $from = "admin@jazzinaustin.com";
                $headers = "From: JazzInAustin <$from>";
                $msg = <<<EOT
Hello $first,

Thank you for registering with Jazz in Austin. To activate your account, please follow the link below:

http://www.jazzinaustin.com/register.php?vc=$vc

Have a nice day!
EOT;
                mail($email, 'Jazz in Austin Account Activation', $msg, $headers, "-f " . $from);
                //display the 'sent an email' message.
                include './views/register_email.html';
                include './includes/footer.html';
                exit();
            }
        }else{
            $reg_errors['email'] = 'An account already exists for this email adress.';
        }
    }
}
//display the form
?>
<script type="application/javascript" src="js/calendar.js"></script>
<?php
include './views/register_form.html';

include './includes/footer.html';
?>