<?php
require './includes/config.inc.php';
$pageTitle = 'Create Profile';
include './includes/header.html';
//exit of user not logged in
if (!isset($_SESSION['id'])) {
    echo '<div class="centeredDiv"><h2>You must be logged in.</h2></div>';
    include './includes/footer.html';
    exit();
}
//check if user already has a profile
require MYSQL;
$r  = $dbc->query('SELECT user_id FROM profiles WHERE user_id='.$_SESSION['id']);
if (!empty($r->fetch())) {
    echo '<div class="centeredDiv"><h2>You already have a profile!</h2></div>';
    include './includes/footer.html';
    exit();
}
//validate form submissions
$profile_errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //instr_id, bio, pic, links
    //validate instrument and get id
    if (isset($_POST['instr']) && preg_match('/^[ \w\-.]{2,}$/', $_POST['instr']) && $_POST['instr'] !== 'none') {
        //if 'other' was selected get the value of that entry
        if ($_POST['instr'] === 'other' && isset($_POST['instrSelOther'])) {
            if (preg_match('/^[ \w\-.]{2,}$/',$_POST['instrSelOther'])) {
                //get id for entered instrument or create a new one
                $q = 'SELECT id FROM instr WHERE name=LOWER(?)';
                $stmt = $dbc->prepare($q);
                $stmt->execute(array($_POST['instrSelOther']));
                $r = $stmt->fetchColumn();
                if (empty($r)) {
                    //create new instrument listing
                    $dbc->exec("INSERT INTO instr (name) VALUES (LOWER('{$_POST['instrSelOther']}'))");
                    $instr = $dbc->lastInsertId();
                }else $instr = $r;
            }else{
                $profile_errors['instr'] = 'Please enter a valid instrument name!';
            }
        }else{
            //get ID of instr from select menu
            $q = 'SELECT id FROM instr WHERE name=LOWER(?)';
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($_POST['instr']));
            $instr = $stmt->fetchColumn();
        }
    }else{
        $profile_errors['instr'] = 'Please choose your primary instrument!';
    }
    //validate bio
    if (isset($_POST['bio'])) {
        $bio = strip_tags($_POST['bio']);
        if (strlen($_POST['bio'])>65535) {
            $profile_errors['bio'] = "Your bio must be less than 65535 characters, currently ".strlen($_POST['bio']);
        }
    }else $bio = null;
    //validate links
    if (!empty($_POST['links'][0])) {
        $links = '';
        foreach ($_POST['links'] as $i=>$l) {
            if (!preg_match('/^(http|https):\/\//i', $l)) {
                $l = 'http://' . $l;
            }
            $l = filter_var($l, FILTER_SANITIZE_URL);
            //validate url
            if (filter_var($l, FILTER_VALIDATE_URL) && preg_match('/\.[a-zA-Z]{2,7}\/?/', $l)) {
                $links .= ((strlen($links) !== 0)?'|':'') . $l;
                //validate link title
                if (isset($_POST['title'][$i])) {
                    $_POST['title'][$i] = strip_tags($_POST['title'][$i]);
                    if (!preg_match('/^[!?,.\w\-() ]*$/', $_POST['title'][$i])) {
                        $profile_errors['title['.$i.']'] = 'Please enter a valid link title!';
                    }else{
                        $links .= '\\'.$_POST['title'][$i];
                    }
                }
            }else{
                $profile_errors['links['.$i.']'] = 'Please enter a valid URL!';
            }
        }
    }else $links = null;
    //validate picture
    if (isset($_FILES['pic']) && is_uploaded_file($_FILES['pic']['tmp_name']) && ($_FILES['pic']['error'] === UPLOAD_ERR_OK)) {
        if (!empty($_FILES['pic']['name']) && isset($_SESSION['profpic'])) {
            unlink($_SESSION['profpic']);
        }
        
        $file = $_FILES['pic'];
        //check file size
        $size = round($file['size']/1024);
        if ($size > 3000) {
            $profile_errors['pic'] = 'The uploaded file was too large.';
            unlink($file['tmp_name']);
        }else{
            //validate file type
            $allowed_mime = array('image/gif', 'image/pjep', 'image/jpeg', 'image/JPG', 'image/X-PNG', 'image/PNG', 'image/png', 'image/x-png');
            $allowed_extensions = array('.jpg', '.gif', '.png', 'jpeg');
            $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($fileinfo, $file['tmp_name']);
            finfo_close($fileinfo);
            $file_ext = substr($file['name'], -4);
            if (!in_array($file_type, $allowed_mime) || !in_array($file_ext, $allowed_extensions)) {
                $profile_errors['pic'] = 'The uploaded file was not of the proper type.';
                unlink($file['tmp_name']);
            }
        }
        //if no errors, we process the image
        if (empty($profile_errors['pic'])) {
            //if file dimensions are too large, resize them.
            $maxHeight = 500;
            $maxWidth = 500;
            //user proper image create function for each file type
            switch ($file_ext) {
                case '.jpg' :
                case 'jpeg' :
                    $src = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case '.gif' :
                    $src = imagecreatefromgif($file['tmp_name']);
                    break;
                case '.png' :
                    $src = imagecreatefrompng($file['tmp_name']);
            }
            $newHeight = $height = imagesy($src);
            $newWidth = $width = imagesx($src);
            //check size
            if ($height > $maxHeight) {
                $newHeight = $maxHeight;
                $newWidth = $width * ($newHeight/$height);
            }
            if ($newWidth > $maxWidth) {
                $newHeight = $newHeight * ($maxWidth/$newWidth);
                $newWidth = $maxWidth;
            }
            //make resized image
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $src, 0,0,0,0, $newWidth, $newHeight, $width, $height);
            //create path
            $name = './images/profiles/' . sha1($file['name'] . uniqid('', true)) . '.jpg';
            imagejpeg($resized, $name);
            unlink($file['tmp_name']);
            $_SESSION['profpic'] = $name;
            $_SESSION['profpicname'] = $file['name'];
            //path to pic file:
            //$pic = $name;
        }
    }else {
        $pic = null;
        if (!empty($_FILES['pic']['name'])) {
            switch ($_FILES['pic']['error']) {
                case 1:
                case 2:
                    $profile_errors['pic'] = 'The uploaded file was too large.';
                    break;
                case 3:
                    $profile_errors['pic'] = 'The file was only partially uploaded.';
                    break;
                case 6:
                case 7:
                case 8:
                    $profile_errors['pic'] = 'The file could not be uploaded due to a system error.';
                    break;
                case 4:
                default:
                    $profile_errors['pic'] = 'No file was uploaded.';
                    break;
            }
        }
    }
    //if no errors, insert into DB
    if (empty($profile_errors)) {
        $q = 'INSERT INTO profiles (user_id, instr_id, bio, pic, links) VALUES (?,?,?,?,?)';
        $stmt = $dbc->prepare($q);
        if ($stmt->execute(array($_SESSION['id'], $instr, $bio, isset($_SESSION['profpic'])?$_SESSION['profpic']:null, $links))) {
            //cleanup
            unset($_SESSION['profpic'], $_SESSION['profpicname'], $file);
            $_POST = array();
            $_FILES = array();
            //create an easy check for seeing if user has a profile
            $_SESSION['hasProfile'] = true;
            include './views/addprofile_success.html';
            include './includes/footer.html';
            //find any events that include this player and add to events_profiles table.
            $q = 'SELECT events.id FROM events, users WHERE users.id='.$_SESSION['id'].' AND band LIKE CONCAT(CONCAT("%",CONCAT_WS(\' \', first_name, last_name)),"%")';
            if ($r = $dbc->query($q)) {
                $eids = array();
                while ($eid = $r->fetchColumn()) {
                    $eids[] = $eid; //get event ids
                }
                $q = 'INSERT INTO events_profiles (event_id, profile_id) VALUES (?,?)';
                $stmt = $dbc->prepare($q);
                foreach ($eids as $eid) {
                    $stmt->execute(array($eid, $_SESSION['id'])); //insert into table
                }
            }
            
            exit();
        }else{
            trigger_error('Your profile was not created because a system error occured. We apologize for the inconvenience.');
        }
    }
}
//show the form
require './includes/form_functions.inc.php';
include './views/addprofile_form.html';
include './includes/footer.html';
?>