<?php
require './includes/config.inc.php';
$pageTitle = 'Edit Venue';
include './includes/header.html';
//require user to be admin
if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
    require MYSQL;
    //if form submitted, validate it
    $venue_errors = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
        //name desc links pic
        //validate name
        if (!empty($_POST['name']) && preg_match('/^[\w \-\']{2,}$/', $_POST['name'])) {
            $name = $_POST['name'];
        }else{
            $venue_errors['name'] = 'Please enter a valid name!';
        }
        //validate desc
        if (!empty($_POST['desc'])) {
            $desc = strip_tags($_POST['desc']);
            if (strlen($_POST['desc'])>65535) {
                $venue_errors['desc'] = "Description must be less than 65535 characters, currently ".strlen($_POST['desc']);
            }
        }else $desc = null;
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
                            $venue_errors['title['.$i.']'] = 'Please enter a valid link title!';
                        }else{
                            $links .= '\\'.$_POST['title'][$i];
                        }
                    }
                }else{
                    $venue_errors['links['.$i.']'] = 'Please enter a valid URL!';
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
                $venue_errors['pic'] = 'The uploaded file was too large.';
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
                    $venue_errors['pic'] = 'The uploaded file was not of the proper type.';
                    unlink($file['tmp_name']);
                }
            }
            //if no errors, we process the image
            if (empty($venue_errors['pic'])) {
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
                $iname = './images/venues/' . sha1($file['name'] . uniqid('', true)) . '.jpg';
                imagejpeg($resized, $iname);
                unlink($file['tmp_name']);
                $_SESSION['profpic'] = $iname;
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
                        $venue_errors['pic'] = 'The uploaded file was too large.';
                        break;
                    case 3:
                        $venue_errors['pic'] = 'The file was only partially uploaded.';
                        break;
                    case 6:
                    case 7:
                    case 8:
                        $venue_errors['pic'] = 'The file could not be uploaded due to a system error.';
                        break;
                    case 4:
                    default:
                        $venue_errors['pic'] = 'No file was uploaded.';
                        break;
                }
            }
        }
        //if no errors, update the DB entry
        if (empty($venue_errors)) {
            
            if (isset($pic) && $pic=== false && !isset($_POST['noPic'])) {
                $q = 'UPDATE venues SET `desc`=?, links=?, name=? WHERE id='.$_GET['id'];
                $stmt = $dbc->prepare($q);
                $stmt->bindParam(1, $desc);
                $stmt->bindParam(2, $links);
                $stmt->bindParam(3, $name);
            }else{
                $pic = isset($_POST['noPic'])?null:$_SESSION['profpic'];
                $q = 'UPDATE venues SET `desc`=?, links=?, name=?, pic=? WHERE id='.$_GET['id'];
                $stmt = $dbc->prepare($q);
                $stmt->bindParam(1, $desc);
                $stmt->bindParam(2, $links);
                $stmt->bindParam(3, $name);
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
                //if name was changed, update the events_venues table
                if ($name !== $_SESSION['currentname']) {
                    //delete old entries
                    $dbc->exec('DELETE FROM events_venues WHERE venue_id='.$_GET['id']);
                    //find events associated with the new name and create rows for them in the table
                    $q = "SELECT id FROM events WHERE venue LIKE '%$name%'";
                    if ($r = $dbc->query($q)) {
                        $eids = array();
                        while ($eid = $r->fetchColumn()) {
                            $eids[] = $eid;
                        }
                        $q = 'INSERT INTO events_venues (event_id, venue_id) VALUES (?, ?)';
                        $stmt = $dbc->prepare($q);
                        foreach ($eids as $eid) {
                            $stmt->execute(array($eid, $_GET['id']));
                        }
                    }
                }
                //cleanup
                unset($_SESSION['profpic'], $_SESSION['currentpic'], $_SESSION['profpicname'], $_SESSION['currentname']);
                //success message
                include './views/editvenue_success.html';
                //the form will be displayed under this
            }else{
                trigger_error('The venue profile could not be updated due to a system error. We apologize for the inconvenience.');
                include './includes/footer.html';
                exit();
            }
        }
    }
    //display the form
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        //get venue info from DB
        $r = $dbc->query('SELECT name, `desc`, pic, links FROM venues WHERE id='.$_GET['id']);
        if ($event = $r->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['currentname'] = $event['name'];
            $_SESSION['currentpic'] = $event['pic'];
            //parse links
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
            require './includes/form_functions.inc.php';
            include './views/editvenue_form.html';
        }else{
            echo '<h2>No venue associated with this ID</h2>';
        }
    }
}else{
    echo '<div class="centeredDiv"><h2>Access Denied</h2></div>'; //must be admin
}
include './includes/footer.html';
?>