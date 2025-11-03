<?php
require_once 'db_connect.php';
session_start();
// if logged-in and not owner, disallow access. If no user exists, allow first-time owner creation.
$allow = false;
$res = $mysqli->query("SELECT COUNT(*) as c FROM users")->fetch_assoc();
$users_exist = intval($res['c'])>0;
if(!$users_exist){
  // allow initial registration (first owner)
  $allow = true;
} else {
  if(isset($_SESSION['role']) && $_SESSION['role']==='owner') $allow = true;
}
if(!$allow){ header('Location: login.php'); exit; }

$error=''; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $role = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';
  $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
  if($username==='' || $password==='') $error='Missing';
  else {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $stmt->bind_param('s',$username); $stmt->execute(); if($stmt->get_result()->fetch_assoc()) $error='Username exists';
    else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $mysqli->prepare("INSERT INTO users (username,password,role,assigned_branch_id,created_at) VALUES (?,?,?,?,NOW())");
      $stmt->bind_param('sssi',$username,$hash,$role,$branch_id);
      if($stmt->execute()){ $msg='User created'; if(!$users_exist){ header('Location: login.php'); exit; } }
      else $error='DB error';
    }
  }
}
$branches = $mysqli->query("SELECT id,name FROM branches ORDER BY id ASC");
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container" style="max-width:680px;margin:60px auto">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3"><?= $users_exist ? 'Create new user' : 'Create owner account (initial setup)' ?></h4>
    <?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if($msg):?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div class="mb-2"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="staff">Staff</option>
          <option value="owner">Owner</option>
        </select>
      </div>
      <div class="mb-3"><label class="form-label">Assign Branch (optional for staff)</label>
        <select name="branch_id" class="form-select">
          <option value="">-- none --</option>
          <?php while($b=$branches->fetch_assoc()): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary">Create</button>
        <a class="btn btn-outline-secondary" href="login.php">Back to login</a>
      </div>
    </form>
  </div></div>
</div>
</body></html>