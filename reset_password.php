<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$code = htmlspecialchars($_GET['code'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password - Motify</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container" style="max-width: 480px; margin-top: 100px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4">Reset Your Password</h4>
            
            <div id="responseMessage" class="mt-3"></div>

            <form id="resetPasswordForm" autocomplete="off">
                <!-- Hidden input for the reset code -->
                <input type="hidden" id="code" name="code" value="<?= $code ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required pattern=".{8,}" title="Password must be at least 8 characters long.">
                    <div id="password-strength-feedback" class="form-text small mt-1"></div>
                </div>
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
            <div class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script src="script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');
        const messageEl = document.getElementById('responseMessage');
        const strengthFeedback = document.getElementById('password-strength-feedback');

        const validatePasswords = () => {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity("Passwords do not match.");
                messageEl.innerHTML = '<div class="alert alert-warning small">Passwords do not match.</div>';
            } else {
                passwordConfirm.setCustomValidity("");
                if (messageEl.innerHTML.includes('Passwords do not match')) messageEl.innerHTML = '';
            }
        };

        const checkStrength = () => {
            const val = password.value;
            let strength = 0;
            if (val.length >= 8) strength++;
            if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strength++;
            if (val.match(/\d/)) strength++;
            if (val.match(/[^a-zA-Z\d]/)) strength++;

            let feedbackText = 'Strength: ';
            let feedbackColor = 'text-danger';

            if (strength <= 1) feedbackText += 'Weak';
            else if (strength === 2) { feedbackText += 'Medium'; feedbackColor = 'text-warning'; }
            else { feedbackText += 'Strong'; feedbackColor = 'text-success'; }
            
            strengthFeedback.innerHTML = `<span class="${feedbackColor}">${feedbackText}</span>`;
        };

        password.addEventListener('input', validatePasswords);
        passwordConfirm.addEventListener('input', validatePasswords);
        password.addEventListener('input', checkStrength);
    }
});
</script>

</body>
</html>