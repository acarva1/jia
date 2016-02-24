<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$row = null;
//check if we are viewing a specific profile
if (isset($_GET['id']) || isset($_GET['name'])) {
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        $pid = $_GET['id'];
        //query profiles database
        require_once MYSQL;
        $r = $dbc->query("CALL get_profile ('id', $pid, NULL)");
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if (empty($row)) { //if no results
            $pageTitle = 'Profile Not Found';
            include './includes/header.html';
            echo '<div class="centeredDiv"><h2>No profile found!</h2></div>';
            include './includes/footer.html';
            exit();
        }
        $r->closeCursor();
    }else if (isset($_GET['name']) && preg_match('/^[a-zA-Z\'\-]+ [a-zA-Z\'\-]+$/', $_GET['name'])) {
        $name = $_GET['name'];
        //query profiles thru user name
        require_once MYSQL;
        $q = "CALL get_profile ('name', NULL, '$name')";
        $stmt = $dbc->prepare($q);
        $stmt->execute(array($name));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($row)) {
            $pageTitle = 'Profile Not Found';
            include './includes/header.html';
            echo '<div class="centeredDiv"><h2>No profile found!</h2></div>';
            include './includes/footer.html';
            exit();
        }
        $stmt->closeCursor();
    }
    //if query succeeded, show profile view
    if (!empty($row)) {
        //parse the links
        if (!empty($row['links'])) {
            $links = explode('|', $row['links']);
            $linkURL = array();
            $linkDesc = array();
            foreach ($links as $i=>$pair) {
                $a = explode('\\', $pair);
                $linkURL[$i] = $a[0];
                $linkDesc[$i] = $a[1];
            }
        }
        //get relevant events info for this profile
        $events = array();
        $q = $dbc->query("SELECT id, DATE_FORMAT(`date`, '%M %D, %Y') AS edate, start_time, end_time, title, venue
        FROM events_profiles JOIN events ON event_id=id
        WHERE profile_id={$row['user_id']} AND `date` >= CURDATE()
        ORDER BY `date` ASC");
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $r['time'] = parseTime($r['start_time']).'-'.parseTime($r['end_time']);
            $events[$r['edate']][] = $r;
        }
        //display the profile view
        $pageTitle = $row['name'];
        include './includes/header.html';
        include './views/profile_view.html';
        include './includes/footer.html';
        exit();
    }
}
//show the profile selection page.
require_once MYSQL;
$pageTitle = 'Musician Profiles';
include './includes/header.html';
//get all profiles sorted by instr
$baseInstr = array ('bass','drums','guitar','piano','saxophone','trombone','trumpet','vocal');
$r = $dbc->query('CALL get_profiles ()');
$rows = array();
while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
    $rows[$row['instr_name']][] = $row; //fetch all rows into this array of arrays indexed by instr name
}
if (empty($rows)) {
    echo '<div class="centeredDiv"><h2>No profiles exist.</h2></div>';
}else{
    include './views/profiles.html';
}
include './includes/footer.html';
?>