<?php
require_once 'db_connect.php';
session_start();

// This script is now only for the initial setup of the first owner account.

$res = $mysqli->query("SELECT COUNT(*) as c FROM users")->fetch_assoc();
$users_exist = intval($res['c'])>0;

// If users already exist, redirect away from this page.
if($users_exist){
  header('Location: login.php');
  exit;
}

$error=''; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $email = trim($_POST['email'] ?? '');
  $role = 'owner'; // First user must be an owner
  $branch_id = null; // No branches exist yet
  if($username==='' || $password==='' || $email === '') {
      $error='Username, password, and email are required.';
  }
  else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please provide a valid email address.';
  }
  else {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $stmt->bind_param('s',$username); $stmt->execute(); if($stmt->get_result()->fetch_assoc()) $error='Username exists';
    else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $mysqli->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, ?, NOW())");
      $stmt->bind_param('ssss', $username, $hash, $email, $role);
      if($stmt->execute()){
        // First owner created, redirect to login.
        header('Location: login.php');
        exit;
      }
      else $error='DB error';
    }
  }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container" style="max-width:680px;margin:60px auto">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Create Owner Account (Initial Setup)</h4>
    <?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if($msg):?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div class="mb-2"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary">Create</button>
        <a class="btn btn-outline-secondary" href="login.php">Back to login</a>
      </div>
    </form>
  </div></div>
</div>
</body></html>