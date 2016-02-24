<?php

//check if login form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') { //otherwise check if form was submitted
    if (isset($_POST['login'])) {
        $loginEmail = $_POST['login']['email']; //save value for sticky form use
        if (filter_var($_POST['login']['email'], FILTER_VALIDATE_EMAIL)) {
            $e = $_POST['login']['email'];
        }else{
            $loginError = true;
        }
        if(!empty($_POST['login']['pass'])) {
            $p = $_POST['login']['pass'];
        }
        //process login info
        if (isset($p, $e)) {
            require_once MYSQL;
            $loginError = true; //turns false if login succeeds
            //get password from database
            $q = 'SELECT id, pass, type, CONCAT_WS(\' \', first_name, last_name) AS name, user_id FROM users LEFT OUTER JOIN profiles ON user_id=id WHERE email=?';
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($e));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row) && empty($stmt->fetch())) { //if theres one row returned.
                //$row = $stmt->fetch(PDO::FETCH_ASSOC);
                $passHash = $row['pass'];
                //verify the password password
                if (isset($passHash) && password_verify($p, $passHash)) {
                    if (!empty($row['type'])) { //login succeeded
                        //if user is admin, set the prop
                        if ($row['type'] === 'a') {
                            session_regenerate_id(true); //prevent session fixation
                            $_SESSION['isAdmin'] = true;
                        }else $_SESSION['isAdmin'] = false;
                        //set other props
                        $_SESSION['id'] = $row['id'];
                        $_SESSION['name'] = $row['name'];
                        //check if has profile
                        if (!empty($row['user_id'])) {
                            $_SESSION['hasProfile'] = true;
                        }
                        //log their ip address
                        $dbc->exec("UPDATE users SET ip='{$_SERVER['REMOTE_ADDR']}' WHERE id={$row['id']}");
                        //set the remember me cookie
                        if (isset($_POST['login']['remember']) && $_POST['login']['remember'] === 'on') {
                            //create row in rm_token table
                            $s = bin2hex(openssl_random_pseudo_bytes(6));
                            $v = bin2hex(openssl_random_pseudo_bytes(6));
                            $q = 'REPLACE INTO rm_tokens (user_id, token, selector, expires) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 31 DAY))';
                            $stmt = $dbc->prepare($q);
                            $stmt->execute(array($_SESSION['id'], hash('sha256', $v), $s));
                            //create the cookie
                            if ($stmt->rowCount() === 1) {
                                setcookie('rm', $s.'-'.$v, time()+60*60*60*24*31*3); //expires in 3 months
                            }
                        }else if (isset($_COOKIE['email'], $_COOKIE['pass']) && isset($_POST['login'])) {
                            //if the box was not checked, we delete existing cookie.
                            setcookie('rm', '', time()-3600);
                            //and remove row from rm_token
                            $q = 'DELETE FROM rm_tokens WHERE user_id=' . $_SESSION['id'];
                            $dbc->query($q);
                        }
                        $loginError = false;
                    }
                }
            }
        }
    }
}else if (isset($_COOKIE['rm']) && strlen($_COOKIE['rm']) === 25) { //otherwise, check for login cookies.
    require_once MYSQL;
    $s = strtok($_COOKIE['rm'], '-'); //selector
    $v = strtok('-'); //validator
    //query the rm token
    $q = 'SELECT user_id, token FROM rm_tokens WHERE selector=? AND expires > NOW()';
    $stmt = $dbc->prepare($q);
    $stmt->execute(array($s));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($row) && empty($stmt->fetch())) {
        //$row = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = $row['token'];
        //compare database with cookie
        if (hash_equals($hash, hash('sha256', $v))) {
            //get account info
            $uid = $row['user_id'];
            $q = 'SELECT type, CONCAT_WS(\' \', first_name, last_name) AS name, pic FROM users LEFT OUTER JOIN profiles ON user_id=id WHERE id=?';
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($uid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row) && empty($stmt->fetch()) && $row['type'] != null) {
                //$row = $stmt->fetch(PDO::FETCH_ASSOC);
                //check if profile exists
                if (!empty($row['pic'])) {
                    $_SESSION['hasProfile'] = true;
                }
                //check if admin
                if ($row['type'] === 'a') {
                    session_regenerate_id(true);
                    $_SESSION['isAdmin'] = true;
                }else $_SESSION['isAdmin'] = false;
                //log them in
                $_SESSION['id'] = $uid;
                $_SESSION['name'] = $row['name'];
                //log their ip address
                $dbc->exec("UPDATE users SET ip='{$_SERVER['REMOTE_ADDR']}' WHERE id=$uid");
                //renew cookie and expiration in DB
                setcookie('rm', $s . '-' . $v, time()+60*60*60*24*31*3); //expire in 3 months
                $q = 'UPDATE rm_tokens SET expires = DATE_ADD(NOW(), INTERVAL 31 DAY) WHERE user_id='.$uid;
                $dbc->query($q);
            }
        }
    }
}