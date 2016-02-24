<?php
require './includes/config.inc.php';
include './includes/login.inc.php';
$pageTitle = 'My Account';
include './includes/header.html';
//make sure user is logged in
if (isset($_SESSION['id']) && filter_var($_SESSION['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //get associated events, profile
    require_once MYSQL;
    /*$q = 'SELECT * FROM profiles WHERE user_id='.$_SESSION['id'];
    if ($profile = $dbc->query($q)) {
        $profile = $profile->fetch();
    }*/
    $q = 'SELECT title, events.id, venue, DATE_FORMAT(`date`, \'%c/%e/%y\') AS edate FROM events WHERE user_id='.$_SESSION['id'].'
    ORDER BY `date` DESC';
    $events = $dbc->query($q);
    //display view
    include './views/myaccount.html';
}else{
    echo '<h2>You are not logged in!</h2>';
}
include './includes/footer.html';
?>