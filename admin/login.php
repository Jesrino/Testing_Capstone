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
    if ($role === 'admin') {
      header("Location: {$base_url}/admin/dashboard.php");
      exit;
    } else {
      $message = 'Access denied. This login is for administrators only.';
      $messageType = 'error';
      // Clear session if wrong role
      session_destroy();
      session_start();
    }
  } elseif (is_array($loginResult) && isset($loginResult['error'])) {
    $message = $loginResult['error'];
    $messageType = 'error';
  } else {
    $message = 'Invalid email or password. Please try again.';
    $messageType = 'error';
  }
}

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
  $host = $_SERVER['HTTP_HOST'];
  $base_path = dirname($_SERVER['PHP_SELF']);
  $base_url = $protocol . "://" . $host . str_replace('\\', '/', dirname($base_path));
  header("Location: {$base_url}/admin/dashboard.php");
  exit;
}

include("../includes/header.php");
?>

<div class="auth-background">
  <div class="background-image"></div>
  <div class="overlay"></div>
  <div class="auth-container">
    <div class="auth-header">
      <img src="<?php echo $base_url; ?>/assets/images/logo.svg" alt="Dents-City Logo" class="auth-logo" />
      <h2>Admin Sign In</h2>
      <p>Access the administrative panel</p>
    </div>

    <?php if ($message): ?>
      <div class="auth-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="form-group">
        <label for="email">Admin Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter admin email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required />
      </div>

      <button type="submit" class="btn-primary">Sign In as Admin</button>
    </form>

    <div class="auth-links">
      <p><a href="<?php echo $base_url; ?>/public/login.php">← Back to Client Login</a></p>
    </div>
  </div>
</div>

<style>
.auth-background {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: relative;
  overflow: hidden;
}

.background-image {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: url('<?php echo $base_url; ?>/assets/images/background.png');
  background-size: cover;
  background-position: center;
  opacity: 0.1;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.3);
}

.auth-container {
  position: relative;
  z-index: 10;
  background: white;
  padding: 2.5rem;
  border-radius: 1rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 420px;
  margin: 1rem;
}

.auth-header {
  text-align: center;
  margin-bottom: 2rem;
}

.auth-logo {
  width: 60px;
  height: 60px;
  margin-bottom: 1rem;
}

.auth-header h2 {
  margin: 0 0 0.5rem 0;
  color: #1f2937;
  font-size: 1.875rem;
  font-weight: 700;
}

.auth-header p {
  margin: 0;
  color: #6b7280;
  font-size: 0.875rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.form-group input {
  padding: 0.75rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: white;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 0.875rem 1.5rem;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  text-align: center;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.auth-error {
  background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
  color: #dc2626;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid #fecaca;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

.auth-links {
  text-align: center;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.auth-links p {
  margin: 0;
  color: #6b7280;
  font-size: 0.875rem;
}

.auth-links a {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.auth-links a:hover {
  color: #5a67d8;
}

@media (max-width: 480px) {
  .auth-container {
    padding: 2rem 1.5rem;
    margin: 0.5rem;
  }

  .auth-header h2 {
    font-size: 1.5rem;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
