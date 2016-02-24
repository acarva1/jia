<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$row = null;
//make sure request method is get with a valid event id
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //get event details
    require_once MYSQL;
    $q='SELECT e.title, e.venue, DATE_FORMAT(e.date, \'%M %D, %Y\') AS date , e.start_time, e.end_time, e.desc, DATE_FORMAT(e.date_created, \'%M %D, %Y\') AS date_created, ev.venue_id, p.user_id AS profile, e.band, e.user_id, CONCAT_WS(\' \', u.first_name, u.last_name) AS name
    FROM events AS e INNER JOIN users AS u ON e.user_id=u.id LEFT OUTER JOIN events_venues AS ev ON e.id=ev.event_id
    LEFT OUTER JOIN profiles AS p ON e.user_id=p.user_id WHERE e.id=?';
    $stmt = $dbc->prepare($q);
    $stmt->execute(array($_GET['id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    //get profile id(s) from events_profiles table if exists
    $profiles = array();
    $q='SELECT profile_id, CONCAT_WS(\' \', first_name, last_name) AS name FROM events_profiles
    INNER JOIN profiles ON profile_id=profiles.user_id INNER JOIN users ON profiles.user_id=users.id WHERE event_id=?';
    $stmt = $dbc->prepare($q);
    $stmt->execute(array($_GET['id']));
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profiles[$r['name']] = $r['profile_id'];
    }
}
if (empty($row)) {
    //fails if not get
    $pageTitle = 'Invalid Event ID';
    include './includes/header.html';
    echo '<h2>This event doesn\'t exist!</h2>';
    include './includes/footer.html';
    exit();
}
$pageTitle = $row['title'];
$pageDesc = $row['desc'];
include './includes/header.html';
include './views/event_view.html';
include './includes/footer.html';
?>