<?php
require './includes/config.inc.php';
$pageTitle = 'Remove Event';
include './includes/header.html';

//if not logged in, exit
if (!isset($_SESSION['id'])) {
    echo '<div class="centeredDiv"><h2>You\'re not authorized.</h2></div>';
    include './includes/footer.html';
    exit();
}
//validate event id and insure that user is the creator of event or is an admin
if ($_SERVER['REQUEST_METHOD'] === 'GET' && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //get user id
    require MYSQL;
    $r = $dbc->query("SELECT user_id, title FROM events WHERE id={$_GET['id']}");
    $r = $r->fetch(PDO::FETCH_NUM);
    if (!$_SESSION['isAdmin']) {
        if ($r[0] !== $_SESSION['id']) {
            //wrong user id
            echo '<h2>This page was accessed in error!</h2>';
            include './includes/footer.html';
            exit();
        }
    }
    echo "<div class=\"centeredDiv\"><h2>Are you sure you want to remove the event '$r[1]?'";
    ?>
<form action="./removeevent.php?id=<?php echo $_GET['id']; ?>" method="post">
    <input type="hidden" value="true" name="delete" />
    <input type="submit" value="Yes" />
    <button type="button" id="cancelButton">Cancel</button>
</form>
</div>
<script type="application/javascript" src="js/cancel.js"></script>
<?php
    //check for form submission
}else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['delete'] === 'true') {
    //check that user is still the author of the event, or is admin
    if (filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        require MYSQL;
        if (!$_SESSION['isAdmin']) {
            $r = $dbc->query("SELECT user_id FROM events WHERE id={$_GET['id']}");
            $r = $r->fetchColumn();
            if ($r !== $_SESSION['id']) {
                //wrong user
                echo '<h2>This page was accessed in error!</h2>';
                include './includes/footer.html';
                exit();
            }
        }
        //delete row from events
        $q = 'DELETE FROM events WHERE id=' . $_GET['id'];
        if ($dbc->exec($q)) {
            //success
            echo '<div class="centeredDiv"><h2>The event was successfully removed!</h2></div>';
        }else{
            trigger_error('The event was not deleted due to a system error. We apologize for the inconvenience.');
        }
    }
}
include './includes/footer.html';
?>