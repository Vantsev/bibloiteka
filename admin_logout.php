<?php
require_once "includes/db.php";
unset($_SESSION['admin_auth']);
session_destroy();
header("Location: admin_login.php?token=bkhvn_s3cr3t_panel_2024");
exit;
