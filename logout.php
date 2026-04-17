<?php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?></content>
<parameter name="filePath">c:/xampp/htdocs/CanastaMX/logout.php