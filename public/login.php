<?php
session_start();
require_once "../includes/auth.php";

// Handle login logic before any output
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  // Determine the base path dynamically for redirects
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
  $host = $_SERVER['HTTP_HOST'];
  $base_path = dirname($_SERVER['PHP_SELF']);
  $base_url = $protocol . "://" . $host . str_replace('\\', '/', dirname($base_path));

  $loginResult = loginUser($email, $password);
  if ($loginResult === true) {
    $role = $_SESSION['role'] ?? null;
    if ($role === 'client') header("Location: {$base_url}/client/dashboard.php");
    elseif ($role === 'dentist' || $role === 'dentist_pending') header("Location: {$base_url}/dentist/dashboard.php");
    elseif ($role === 'admin') header("Location: {$base_url}/admin/dashboard.php");
    exit;
  } elseif (is_array($loginResult) && isset($loginResult['error'])) {
    $message = $loginResult['error'];
    $messageType = 'error';
  } else {
    $message = 'Invalid email or password. Please try again.';
    $messageType = 'error';
  }
}

include("../includes/header.php");
?>

<div class="auth-background">
  <div class="background-image"></div>
  <div class="overlay"></div>
  <div class="auth-container">
    <h2>Sign In</h2>

    <?php if ($message): ?>
      <div class="auth-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required />
      </div>

      <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <div class="auth-links">
      <p>Don't have an account? <a href="register.php">Sign Up</a></p>
    </div>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
