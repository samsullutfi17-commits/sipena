<?php
session_start();
session_destroy();
header('Location: peserta_login.php');
exit;
