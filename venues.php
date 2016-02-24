<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$row = null;
require_once MYSQL;
//if getting a specific venue
if (!empty($_GET)) {
    //get venue details from DB based on whether we are getting name or id value
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        $r = $dbc->query('SELECT * FROM venues WHERE id='.$_GET['id']);
    }else if (isset($_GET['name']) && preg_match('/^[\w \-\']{2,}$/', $_GET['name'])) {
        $r = $dbc->prepare('SELECT * FROM venues WHERE name=?');
        $r->execute(array($_GET['name']));
    }else{
        $pageTitle = 'Error';
        include './includes/header.html';
        echo '<div class="centeredDiv"><h2>This page has been accessed in error</h2></div>';
        include './includes/footer.html';
        exit();
    }
    $row = $r->fetch(PDO::FETCH_ASSOC);
    $r->closeCursor();
    //get associated event info
    if ($r = $dbc->query("SELECT title, events.id, DATE_FORMAT(`date`, '%M %D, %Y') AS edate, start_time, end_time
    FROM venues JOIN events_venues ON venues.id=venue_id JOIN events ON events.id=event_id
    WHERE venues.id={$row['id']} AND `date` >= CURDATE()
    ORDER BY `date` ASC")) {
        $events = array();
        while ($row2 = $r->fetch(PDO::FETCH_ASSOC)) {
            $row2['time'] = parseTime($row2['start_time']) . ' - ' . parseTime($row2['end_time']);
            $events[$row2['edate']][] = $row2; //array of an array of events indexed by date
        }
    }
    //parse the links
    $linkURL = array();
    if (!empty($row['links'])) {
        $p = explode('|', $row['links']);
        foreach ($p as $pair) {
            $l = explode('\\', $pair);
            $linkURL[$l[0]] = $l[1];
        }
    }
    //display the view
    $pageTitle = $row['name'];
    include './includes/header.html';
    include './views/venue_view.html';
    include './includes/footer.html';
    exit();
}


$pageTitle = 'Venues';
include './includes/header.html';
//get venue info from DB
$r = $dbc->query('CALL get_venues ()');
//display venues
include './views/venues.html';
include './includes/footer.html';
?>