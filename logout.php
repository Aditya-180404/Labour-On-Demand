<?php
require_once 'includes/security.php';
session_unset();
session_destroy();
header("Location: index.php");
exit;
?>
