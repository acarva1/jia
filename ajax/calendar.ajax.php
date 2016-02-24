<?php
if($_SERVER['REQUEST_METHOD'] != 'POST') { //post is the only way to access.
    exit();
}

require '../includes/config.inc.php';
require MYSQL;


$c = file_get_contents('php://input');
$c = json_decode($c);

//valide data
if (isset($c->start) && isset($c->end)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $c->start) && //dates are in format YYYY-MM-DD
       preg_match('/^\d{4}-\d{2}-\d{2}$/', $c->end)) {
        $startDate = $c->start;
        $endDate = $c->end;
    }else{
        echo 'false';
        exit();
    }
    $result = array();
    //make the query
    $q = "CALL get_events('$startDate', '$endDate')";
    $r = $dbc->query($q);
    
    while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
        $title = $row['title'];
        $id = $row['id'];
        $start = $row['start_time'];
        $end = $row['end_time'];
        $venue = $row['venue'];
        $date = $row['fdate'];
        $result[$date][] = array( //each element of result is a date key with an array of an array of event values.
            'title'=>$title, 'id'=>$id, 'start'=>$start, 'end'=>$end, 'venue'=>$venue
        );
    }
    
    $dbc = null;
    //encode and send
    $result = json_encode($result);
    echo $result;
}else{ //no valid data
    $dbc = null;
    exit('false');
}
?>