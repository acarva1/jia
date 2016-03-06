<?php
require './includes/config.inc.php';
$pageTitle = 'Embed Calendar';
include './includes/header.html';
//check that user is logged in and has a profile
if (isset($_SESSION['id']) && isset($_SESSION['hasProfile'])) {
    include './views/service.html';
}else{
    echo '<div class="centeredDiv"><h2>Access Denied</h2></div>';
}
include './includes/footer.html';
?>