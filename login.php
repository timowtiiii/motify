<?php
// login.php
require_once 'db_connect.php';
session_start();
$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if($username==='' || $password==='') $error = 'Missing fields';
  else {
    $stmt = $mysqli->prepare("SELECT id, username, password, role, assigned_branch_id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param('s',$username); $stmt->execute(); $res = $stmt->get_result();
    if($row = $res->fetch_assoc()){
      if(password_verify($password, $row['password'])){
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['assigned_branch_id'] = $row['assigned_branch_id'];
        header('Location: index.php'); exit;
      } else $error = 'Invalid credentials';
    } else $error = 'Invalid credentials';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Login - Motify</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f4f7fb}.login-box{max-width:420px;margin:80px auto}</style>
</head>
<body>
<div class="container login-box">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">Motify — Login</h4>
      <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="mb-2">
          <label for="username" class="form-label">Username</label>
          <input id="username" name="username" class="form-control" required autocomplete="username">
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="d-flex justify-content-between">
          <button class="btn btn-primary">Login</button>
          <a href="register.php" class="btn btn-outline-secondary">Register (owner only)</a>
        </div>
        <div class="text-center mt-3">
          <a href="forgot_password.php">Forgot Password?</a>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>