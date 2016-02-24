<?php
require './includes/config.inc.php';
$pageTitle = 'Add Venue';
include './includes/header.html';
//require user to be admin
if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
    require MYSQL;
    //if form submission, begin validating form
    $venue_errors = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //name, desc, pic
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
                $profile_errors['desc'] = "Description must be less than 65535 characters, currently ".strlen($_POST['desc']);
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
        //validate pic
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
                $maxHeight = 400;
                $maxWidth = 400;
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
        }else{
            $pic = null;
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
        //insert into DB
        if (empty($venue_errors)) {
            $q = "INSERT INTO venues (name, pic, `desc`, links) VALUES (?, ?, ?, ?)";
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($name, isset($_SESSION['profpic'])?$_SESSION['profpic']:null, $desc, $links));
            if ($stmt->rowCount() === 1) {
                //success
                $vid = $dbc->lastInsertId(); //get id for new entry
                include './views/addvenue_success.html';
                //find any events that reference this venue and add to events_venues table.
                $name = str_ireplace('the ', '', $name); //remove 'The' prefix
                $q = "SELECT id FROM events WHERE venue LIKE '%$name%'";
                if ($r = $dbc->query($q)) {
                    $eids = array();
                    while ($eid = $r->fetchColumn()) {
                        $eids[] = $eid;
                    }
                    $q = 'INSERT INTO events_venues (event_id, venue_id) VALUES (?, ?)';
                    $stmt = $dbc->prepare($q);
                    foreach ($eids as $eid) {
                        $stmt->execute(array($eid, $vid));
                    }
                }
                //cleanup
                unset ($_SESSION['profpic'], $_SESSION['profpicname']);
                $_POST = $_FILES = array();
            }
        }
    }
    //display the form
    require './includes/form_functions.inc.php';
    include './views/addvenue_form.html';
}else{
    echo '<div class="centeredDiv"><h2>Access Denied</h2></div>'; //must be admin
}
include './includes/footer.html';
?>