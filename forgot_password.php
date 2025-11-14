<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password - Motify</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container" style="max-width: 480px; margin-top: 100px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4">Forgot Password</h4>
            <p class="text-muted text-center">Enter the email address associated with the owner account. A verification code will be sent to you.</p>
            
            <div id="responseMessage" class="mt-3"></div>

            <form id="forgotPasswordForm" autocomplete="off">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Send Reset Code</button>
                </div>
            </form>
            <div class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script src="script.js"></script>

</body>
</html>