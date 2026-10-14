<?php
/**
 * AS ESPORT Admin - logout. Destroys the admin session and returns to login.
 */

require_once __DIR__ . '/../config.php';

unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_name']);
session_regenerate_id(true);
redirect('login.php');
