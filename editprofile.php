<?php
require './includes/config.inc.php';
$pagetitle = 'Edit Profile';
include './includes/header.html';
//validate form submission
$profile_errors = array();
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'],$_SESSION['id']) && $_GET['id'] === $_SESSION['id']) || $_SESSION['isAdmin']) {
    require_once MYSQL;
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
        $pic = false;
        //if pic failed to upload, find out why
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
    
    //if no errors, update the profile
    if (empty($profile_errors)) {
        
        if (isset($pic) && $pic=== false && !isset($_POST['noPic'])) {
            $q = 'UPDATE profiles SET bio=?, links=?, instr_id=? WHERE user_id='.$_GET['id'];
            $stmt = $dbc->prepare($q);
            $stmt->bindParam(1, $bio);
            $stmt->bindParam(2, $links);
            $stmt->bindParam(3, $instr);
        }else{
            $pic = isset($_POST['noPic'])?null:$_SESSION['profpic'];
            $q = 'UPDATE profiles SET bio=?, links=?, instr_id=?, pic=? WHERE user_id='.$_GET['id'];
            $stmt = $dbc->prepare($q);
            $stmt->bindParam(1, $bio);
            $stmt->bindParam(2, $links);
            $stmt->bindParam(3, $instr);
            $stmt->bindParam(4, $pic); //if no pic, insert null
        }
        if ($stmt->execute()) {
            //success
            if (isset($_SESSION['currentpic']) && !file_exists($_SESSION['currentpic'])) {
                unset($_SESSION['currentpic']);
            }
            if (!empty($_SESSION['profpic']) && isset($_SESSION['currentpic'])) {
                //delete the old pic if a new one was selected
                unlink($_SESSION['currentpic']);
            }
            if (isset($_POST['noPic']) && isset($_SESSION['currentpic'])) {
                //delete the current picture if no pic was checked
                unlink($_SESSION['currentpic']);
            }
            //cleanup
            unset($_SESSION['profpic'], $_SESSION['currentpic'], $_SESSION['profpicname']);
            //success message
            include './views/editprofile_success.html';
            //the form will be displayed under this
        }else{
            trigger_error('The profile could not be updated due to a system error. We apologize for the inconvenience.');
            include './includes/footer.html';
            exit();
        }
    }
}
//check id and make sure user is logged in or is admin
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    if (!isset($_SESSION['id']) || $_SESSION['id'] !== $_GET['id'] && !$_SESSION['isAdmin']) {
        echo '<div class="centeredDiv"><h2>Access denied.</h2></div>';
        include './includes/footer.html';
        exit();
    }
    //get profile info from DB
    require_once MYSQL;
    $q = 'SELECT name AS instr, bio, pic, links FROM profiles JOIN instr ON id=instr_id WHERE user_id='.$_GET['id'];
    $event = $dbc->query($q);
    if ($event = $event->fetch(PDO::FETCH_ASSOC)) {
        //save path of current pic
        $_SESSION['currentpic'] = $event['pic'];
        //proccess link list
        $linkURL = array();
        $linkName = array();
        if (!empty($event['links'])) {
            $a = explode('|', $event['links']);
            foreach ($a as $p) {
                $p2 = explode('\\', $p);
                $linkURL[] = $p2[0];
                $linkName[] = $p2[1];
            }
        }
        //show the form
        require './includes/form_functions.inc.php';
        include './views/editprofile_form.html';
        include './includes/footer.html';
        exit();
    }else{
        //no profile found
        echo '<div class="centeredDiv"><h2>No profile exists for this user.</h2></div>';
        include './includes/footer.html';
        exit();
    }
}else{
    //must be a get or post request
    echo '<div class="centeredDiv"><h2>Access denied.</h2></div>';
    include './includes/footer.html';
}
?>