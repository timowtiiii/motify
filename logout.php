<?php
session_start();
require_once 'db_connect.php';
$user = $_SESSION['user_id'] ?? null;
if($user){
  $mysqli->query("INSERT INTO action_logs (user_id, action, meta) VALUES (".intval($user).", 'logout', 'user logged out')");
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
