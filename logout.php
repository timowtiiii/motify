

<?php
session_start();
require_once 'db_connect.php';
$user = $_SESSION['user_id'] ?? null;
if($user){
  $log_stmt = $mysqli->prepare("INSERT INTO action_logs (user_id, action, meta) VALUES (?, 'logout', ?)");
  $meta_message = 'User logged out';
  $log_stmt->bind_param('is', $user, $meta_message);
  $log_stmt->execute();
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
