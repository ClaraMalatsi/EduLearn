<?php
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$surname || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Please complete all required fields correctly.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (role,name,surname,email,contact,address,password_hash) VALUES ('learner',?,?,?,?,?,?)");
            $stmt->execute([$name,$surname,$email,$contact,$address,password_hash($password, PASSWORD_DEFAULT)]);
            $success = 'Registration successful. You can now log in.';
        } catch (PDOException $e) {
            $error = 'Email address may already be registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Register - EduLearn</title><link rel="stylesheet" href="assets/css/Lms.css"></head>
<body>
<div class="login-page"><div class="login-container">
<h1>Create Learner Account</h1>
<?php if ($error): ?><p class="login-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="success-message"><?= e($success) ?></p><?php endif; ?>
<form method="POST">
<div class="form-grid">
<div class="input-group"><label>Name</label><input name="name" required></div>
<div class="input-group"><label>Surname</label><input name="surname" required></div>
</div>
<div class="input-group"><label>Email</label><input type="email" name="email" required></div>
<div class="input-group"><label>Contact</label><input name="contact"></div>
<div class="input-group"><label>Address</label><input name="address"></div>
<div class="input-group"><label>Password</label><input type="password" name="password" minlength="6" required></div>
<button class="login-button">Register</button>
</form>
<p class="login-info"><a href="index.php">Back to Login</a></p>
</div></div>
</body></html>
