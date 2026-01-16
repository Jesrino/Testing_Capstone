<?php
require_once '../includes/guards.php';
requireRole('admin');
include('../includes/header.php');
require_once __DIR__ . '/../includes/db.php';

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS SMTPSettings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  host VARCHAR(255) DEFAULT NULL,
  port INT DEFAULT 587,
  username VARCHAR(255) DEFAULT NULL,
  password TEXT DEFAULT NULL,
  encryption VARCHAR(10) DEFAULT NULL,
  from_email VARCHAR(255) DEFAULT NULL,
  from_name VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Load existing
$stmt = $pdo->query("SELECT * FROM SMTPSettings ORDER BY id DESC LIMIT 1");
$cfg = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    $host = $_POST['host'] ?: null;
    $port = $_POST['port'] ?: null;
    $username = $_POST['username'] ?: null;
    $password = $_POST['password'] ?: null;
    $encryption = $_POST['encryption'] ?: null;
    $from_email = $_POST['from_email'] ?: null;
    $from_name = $_POST['from_name'] ?: null;

    if ($cfg) {
        $up = $pdo->prepare("UPDATE SMTPSettings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ? WHERE id = ?");
        $up->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name, $cfg['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO SMTPSettings (host, port, username, password, encryption, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name]);
    }
    header('Location: smtp_settings.php?saved=1'); exit;
}
?>

<div class="container">
  <h1>SMTP Settings</h1>
  <?php if (isset($_GET['saved'])): ?>
    <div class="success">Saved SMTP settings.</div>
  <?php endif; ?>

  <form method="POST" class="smtp-form" style="max-width:800px;">
    <div class="form-group">
      <label>SMTP Host</label>
      <input type="text" name="host" value="<?php echo htmlspecialchars($cfg['host'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label>Port</label>
      <input type="number" name="port" value="<?php echo htmlspecialchars($cfg['port'] ?? 587) ?>" />
    </div>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?php echo htmlspecialchars($cfg['username'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" value="<?php echo htmlspecialchars($cfg['password'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label>Encryption</label>
      <select name="encryption">
        <option value="" <?php echo empty($cfg['encryption']) ? 'selected' : '' ?>>None</option>
        <option value="tls" <?php echo (isset($cfg['encryption']) && $cfg['encryption']==='tls') ? 'selected' : '' ?>>TLS</option>
        <option value="ssl" <?php echo (isset($cfg['encryption']) && $cfg['encryption']==='ssl') ? 'selected' : '' ?>>SSL</option>
      </select>
    </div>
    <div class="form-group">
      <label>From Email</label>
      <input type="email" name="from_email" value="<?php echo htmlspecialchars($cfg['from_email'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label>From Name</label>
      <input type="text" name="from_name" value="<?php echo htmlspecialchars($cfg['from_name'] ?? '') ?>" />
    </div>

    <button type="submit" name="save_smtp" class="btn-primary">Save</button>
  </form>
</div>

<style>
.smtp-form .form-group { margin-bottom:12px; display:flex; flex-direction:column; }
.smtp-form label { font-weight:600; margin-bottom:6px; }
.smtp-form input, .smtp-form select { padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; }
</style>
