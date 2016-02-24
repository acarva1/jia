<?php
	require './includes/config.inc.php';
	include './includes/login.inc.php';
	$pageTitle = 'Calendar';
	include './includes/header.html';

	include './views/calendar.html';

	include './includes/footer.html';
?>