  <?php
session_start();
include("../includes/header.php");
require_once "../includes/auth.php";

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';
  $role = 'client';

  // Validation
  if (empty($name) || empty($email) || empty($password)) {
    $message = 'All fields are required.';
    $messageType = 'error';
  } elseif ($password !== $confirmPassword) {
    $message = 'Passwords do not match.';
    $messageType = 'error';
  } elseif (strlen($password) < 6) {
    $message = 'Password must be at least 6 characters long.';
    $messageType = 'error';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please enter a valid email address.';
    $messageType = 'error';
  } else {
    $res = registerUser($name, $email, $password, $role);
    if ($res['ok']) {
      $message = 'Registration successful! Please sign in with your credentials.';
      $messageType = 'success';
    } else {
      $message = $res['msg'];
      $messageType = 'error';
    }
  }
}
?>

<div class="auth-background">
  <div class="background-image"></div>
  <div class="overlay"></div>
  <div class="auth-container">
    <h2>Create Account</h2>

    <?php if ($message): ?>
      <div class="auth-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
    <div class="form-group">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" />
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
    </div>

    <div class="form-group">
      <label for="phone">Phone Number</label>
      <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" />
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Create a password" required minlength="6" />
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required />
    </div>

    <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="auth-links">
      <p>Already have an account? <a href="login.php">Sign In</a></p>
    </div>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
