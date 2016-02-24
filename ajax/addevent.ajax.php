<?php
if($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_SESSION['id'])) { //post is the only way to access.
    exit();
}

require '../includes/config.inc.php';
require MYSQL;

$c = file_get_contents('php://input');
$c = json_decode($c);

//validate the event id
if (isset($c->id) && filter_var($c->id, FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //get the event info
    $q = 'SELECT title, start_time, end_time, band, venue, `desc` FROM events WHERE id='.$c->id;
    $r = $dbc->query($q);
    //build the response
    $row = $r->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $response = array(
            'title'=>$row['title'],
            'start'=>$row['start_time'],
            'end'=>$row['end_time'],
            'band'=>$row['band'],
            'venue'=>$row['venue'],
            'desc'=>$row['desc']
        );
    }
    $dbc = null;
    $response = json_encode($response);
    echo $response;
}
?>