<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$pageTitle = 'Add Event';
include './includes/header.html';

//check that user is logged in
if (isset($_SESSION['id']) && filter_var($_SESSION['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //check for form submission
    $event_errors = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        //fields: Title, Venue, Date, Start time, end time, description
        //validate title
        if (isset($_POST['title']) && strlen($_POST['title']) <= 80 && strlen($_POST['title']) >= 3) {
            $title = $_POST['title'];
            $title = strip_tags($title); //strip any html tags
        }else{
            $event_errors['title'] = 'Please enter a valid title for your event!';
        }
        //validate venue
        if (isset($_POST['venue']) && strlen($_POST['venue']) <= 80 && strlen($_POST['venue']) >= 2) {
            $venue = $_POST['venue'];
            $venue = strip_tags($venue);
        }else{
            $event_errors['venue'] = 'Please enter the name of the venue for your event!';
        }
        //validate date
        if (isset($_POST['date']) && preg_match('/^\d{1,2}\D\d{1,2}\D(\d{2}||\d{4})$/', $_POST['date'])) {
            $nums = preg_split('/\D/', $_POST['date']);
            $month = substr('0' . $nums[0], -2);
            $day = substr('0' . $nums[1], -2);
            //validate year
            $year = substr('20' . $nums[2], -4);
            if ((int)$year > date('Y')+4)
                $event_errors['date'] = 'Date is too far in the future!';
            if (empty($event_errors['date']) && checkdate($month, $day, $year)) { //make sure date is valid
                $date = $year.'-'.$month.'-'.$day;
                if (strtotime($date) < strtotime(date('Y-m-d'))) $event_errors['date'] = 'The date can\'t be in the past!';
            }else $event_errors['date'] = 'Please enter a valid date!';
        }else{
            $event_errors['date'] = 'Please enter a valid date!';
        }
        //validate start time
        if (isset($_POST['startHour'], $_POST['startMin'], $_POST['startPeriod'])) {
            //time values are validated with JS
            $hour = (int) $_POST['startHour'];
            if ($_POST['startPeriod'] === 'pm' && ($hour < 12)) {
                $hour += 12;
            }else if ($_POST['startPeriod'] === 'am' && $hour === 12) $hour = 0; //midnight
            $hour = substr('0' . $hour, -2);
            $min = substr('0' . $_POST['startMin'], -2);
            $start = $hour . $min;
        }else{
            $event_errors['start'] = 'Please enter a valid start time!';
        }
        //validate end time
        if (isset($_POST['endHour'], $_POST['endMin'], $_POST['endPeriod'])) {
            //time values are validated with JS
            $hour = (int) $_POST['endHour'];
            if ($_POST['endPeriod'] === 'pm' && ($_POST['endHour'] < 12)) {
                $hour += 12;
            }else if ($_POST['endPeriod'] === 'am' && $hour === 12) $hour = 0; //midnight
            $hour = substr('0' . $hour, -2);
            $min = substr('0' . $_POST['endMin'], -2);
            $end = $hour . $min;
        }else{
            $event_errors['end'] = 'Please enter a valid ending time!';
        }
        //filter description
        $desc = null;
        if (isset($_POST['desc'])) {
            $desc = strip_tags($_POST['desc']);
            if (strlen($_POST['desc'])>65535) {
                $desc = substr($desc, 0, 65535);
            }
        }
        //validate band members
        $band = '';
        if (!empty($_POST['band'])) {
            $i = 0;
            foreach ($_POST['band'] as &$bn) {
                //if invalid, create error
                if (!preg_match('/^[a-zA-Z\- \'.]{3,}$/', $bn)) {
                    $event_errors['band['.$i.']'] = 'Invalid name.';
                }
                if ($band !== '') $band .= '|';
                $bn = htmlspecialchars(strip_tags($bn));
                $bn = str_replace(array(',','|'), '', $bn);
                //validate instrument
                if(!empty($_POST['instr'][$i])) {
                    $_POST['instr'][$i] = strip_tags($_POST['instr'][$i]);
                    $_POST['instr'][$i] = str_replace(array(',','|'), '', $_POST['instr'][$i]);
                    if (!preg_match('/^[a-zA-Z\- \']{3,}$/', $_POST['instr'][$i])) {
                        $event_errors['instr['.$i.']'] = 'Invalid instrument.';
                    }
                }
                //check for duplicate names
                if (array_count_values($_POST['band'])[$bn] === 1) {
                    //compose band string
                    $band .= $bn . ',' . ($_POST['instr'][$i]?$_POST['instr'][$i]:'');
                }
                $i++;
            }
        }
        //if no errors, add event to DB
        if (empty($event_errors)) {
            require_once MYSQL;
            //check if this event already exists
            $q = 'SELECT id FROM events WHERE venue=? AND date=? AND start_time=?';
            $stmt = $dbc->prepare($q);
            $stmt->execute(array($venue, $date, $start));
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if (!empty($row)) {
                $event_errors['date'] = 'An event has already been created with this date, time, and venue!';
            }else{
                //add event to DB
                $q = 'INSERT INTO events (title, venue, start_time, end_time, `date`, `desc`, user_id, band) VALUES (?,?,?,?,?,?,?,?)';
                $stmt = $dbc->prepare($q);
                if ($stmt->execute(array($title, $venue, $start, $end, $date, $desc, $_SESSION['id'], $band))) {
                    $eid = $dbc->lastInsertId();
                    //show success message
                    include './views/addevent_success.html';
                    include './includes/footer.html';
                    //if band member names match with a profile, add to the event_profile table
                    if (!empty($_POST['band'])) {
                        $q = 'SELECT u.id AS id FROM users AS u INNER JOIN profiles AS p ON p.user_id=u.id WHERE CONCAT_WS(\' \', LOWER(u.first_name), LOWER(u.last_name))=LOWER(?)';
                        $stmt = $dbc->prepare($q);
                        for ($i = 0; $i < count($_POST['band']); $i++) {
                            $stmt->execute(array($_POST['band'][$i]));
                            $uid = $stmt->fetchColumn();
                            if ($uid) {
                                $dbc->exec('INSERT INTO events_profiles (profile_id, event_id) VALUES ('.$uid.', '.$eid.')');
                            }
                        }
                    }
                    //if the venue has a page, create an events_venues listing
                    $q = "SELECT id FROM venues WHERE name LIKE '%$venue%'";
                    if ($stmt = $dbc->query($q)) {
                        //$stmt->execute(array($venue));
                        $vid = $stmt->fetchColumn();
                        if ($vid) {
                            //create row
                            $dbc->exec("INSERT INTO events_venues (venue_id, event_id) VALUES ($vid, $eid)");
                        }
                    }

                    exit();
                }else{
                    trigger_error('A system error has occured, your event was not added. We apologize for the inconvenience.');
                }
            }
        }
    }
    require_once MYSQL;
    //Query all the profile user names
    $names = $dbc->query('SELECT CONCAT_WS(" ", first_name, last_name) AS name FROM users JOIN profiles ON id=user_id ORDER BY first_name, last_name ASC');
    if ($names) {
        $nameList = array();
        while ($name = $names->fetchColumn()) {
            $nameList[] = $name;
        }
        $nameList = json_encode($nameList); //this will be in a hidden form for the JS to access
    }
    //check if user has a profile to get their instrument
    $r = $dbc->query("SELECT i.name AS instrument FROM users AS u
    INNER JOIN profiles AS p ON p.user_id=u.id
    INNER JOIN instr AS i ON p.instr_id=i.id
    WHERE u.id={$_SESSION['id']}");
    if ($r) {
        //get the user's instrument
        $instr = ucwords($r->fetchColumn());
    }
    //get the names of all this user's events
    $r = $dbc->query("SELECT title, id FROM events WHERE user_id={$_SESSION['id']}");
    include './includes/form_functions.inc.php';
    //show the form
    include './views/addevent_form.html';
    include './includes/footer.html';
    
}else{
    echo '<div class="centeredDiv"><h2>You must be logged in to create an event.</h2></div>';
    include './includes/footer.html';
}

?>