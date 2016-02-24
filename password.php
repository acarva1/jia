<?php
require './includes/config.inc.php';
$pageTitle = 'Change Password';
include './includes/header.html';
//on form submit, validate
$pw_errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id'])) {
    require MYSQL;
    //see if the form was submitted with an auth_token and validate that
    if (isset($_GET['t']) && strlen($_GET['t']) === 64 && !isset($_POST['curpass'])) {
        $q = 'SELECT user_id FROM auth_tokens WHERE token=? AND expires > NOW()';
        $stmt = $dbc->prepare($q);
        if ($stmt->execute(array($_GET['t']))) {
            $uid = $stmt->fetchColumn();
            if ($uid === $_SESSION['id']) {
                $pwCheck = true;
            }else{
                echo '<div class="centeredDiv"><h2>This page was accessed in error.</h2></div>';
                include './includes/footer.html';
                exit();
            }
        }else trigger_error('An internal error occured.');
    }else{
        //no token, check current password
        if (!empty($_POST['curpass'])) {
            if ($r = $dbc->query('SELECT pass FROM users where id='.$_SESSION['id'])) {
                if ($curPass = $r->fetchColumn()) {
                    if (password_verify($_POST['curpass'], $curPass)) {
                        //password matches
                        $pwCheck = true;
                    }else{
                        $pw_errors['curpass'] = 'Incorrect password!';
                        $pwCheck = false;
                    }
                }else{
                    echo '<div class="centeredDiv"><h2>This page was accessed in error.</h2></div>';
                }
            }else trigger_error('An internal error occured.');
        }
    }
    //validate new password
    if (!empty($_POST['pass1']) && preg_match('/^\w*(?=\w*[A-Z])(?=\w*\d)(?=\w*[a-z])\w*$/', $_POST['pass1']) && strlen($_POST['pass1'])>=6) {
        //confirm pass
        if ($_POST['pass1'] === $_POST['pass2']) {
            $pass = password_hash($_POST['pass1'], PASSWORD_BCRYPT);
        }else $pw_errors['pass2'] = 'Passwords did not match!';
    }else $pw_errors['pass1'] = 'Please enter a valid password!';
    //if no errors and password check or token cleared, update password
    if (empty($pw_errors) && isset($pwCheck) && $pwCheck === true) {
        if ($dbc->exec("UPDATE users SET pass='$pass' WHERE id={$_SESSION['id']}")) {
            //success
            include './views/password_success.html';
            include './includes/footer.html';
            //remove auth_token if exists
            if (isset($uid)) {
                $dbc->exec('DELETE FROM auth_tokens WHERE user_id='.$uid);
            }
            //remove rm_token if exists
            if ($dbc->exec('DELETE FROM rm_tokens WHERE user_id='.$_SESSION['id'])) {
                setcookie('rm', '', time()-3600); //remove cookie
            }
            exit();
        }else{
            trigger_error('The password was not changed due to a system error. We apologize for the inconvenience.');
        }
    }
}
//determin which version of the PW change form to display.
if (isset($_SESSION['id']) && !isset($_GET['t'])) {//logged in
    //show the change password form.
    require './includes/form_functions.inc.php';
    include './views/changepw_form.html';
}else if ((!isset($_SESSION['id']) || isset($uid)) && isset($_GET['t']) && strlen($_GET['t']) === 64) {//forgot password
    //get user info if token matches
    if (isset($uid)) { //if the entered password failed to validate, $uid will be set and we don't need to run a query.
        require './includes/form_functions.inc.php';
        include './views/resetpw_form.html';
    }else{
        require MYSQL;
        $q = "SELECT user_id, type, CONCAT_WS(' ', first_name, last_name) AS name
        FROM auth_tokens JOIN users ON user_id=id
        WHERE token=? AND expires > NOW()";
        $stmt = $dbc->prepare($q);
        if ($stmt->execute(array($_GET['t'])) && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['type'])) { //user acct is active
                //regen session id
                session_regenerate_id(true);
                //set session atts
                $_SESSION['id'] = $row['user_id'];
                $_SESSION['isAdmin'] = ($row['type'] === 'a')?true:false;
                $_SESSION['name'] = $row['name'];
                //show pw reset form
                require './includes/form_functions.inc.php';
                include './views/resetpw_form.html';
            }else{
                echo '<div class="centeredDiv"><h2>The supplied token has expired.</h2>
                You\'ll need to start the process over before you can reset your password.</div>';
            }
        }
    }
}else{
    echo '<div class="centeredDiv"><h2>Access Denied</h2></div>';
}
include './includes/footer.html';
?>