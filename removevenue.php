<?php
require './includes/config.inc.php';
$pageTitle = 'Remove Venue';
include './includes/header.html';
//require user to be admin
if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true) {
    require MYSQL;
    //if delete is confirmed.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        //remove database row
        if ($dbc->exec('DELETE FROM venues WHERE id='.$_GET['id'])) {
            //success
            echo '<div class="centeredDiv"><h2>The event was successfully removed!</h2></div>';
        }else{
            trigger_error('The event was not deleted due to a system error. We apologize for the inconvenience.');
        }
    }else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
        //if initial get request, show confirmation form
        //get venue name
        $name = $dbc->query('SELECT name FROM venues WHERE id='.$_GET['id']);
        $name = $name->fetchColumn();
        echo "<div class=\"centeredDiv\"><h2>Are you sure you want to remove the venue '$name?'";
        ?>
    <form action="./removevenue.php?id=<?php echo $_GET['id']; ?>" method="post">
        <input type="hidden" value="true" name="delete" />
        <input type="submit" value="Yes" />
        <button type="button" id="cancelButton">Cancel</button>
    </form>
    </div>
    <script type="application/javascript" src="js/cancel.js"></script>
    <?php
    }
    
}else{
    echo '<div class="centeredDiv"><h2>Access Denied.</h2></div>';
}
include './includes/footer.html';
?>