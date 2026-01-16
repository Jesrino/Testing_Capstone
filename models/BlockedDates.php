<?php
require_once __DIR__ . '/../includes/db.php';

function ensureBlockedDatesTable() {
  global $pdo;
  $pdo->exec("CREATE TABLE IF NOT EXISTS BlockedDates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function addBlockedDate($date, $reason = null, $createdBy = null) {
  global $pdo;
  ensureBlockedDatesTable();
  $stmt = $pdo->prepare("INSERT INTO BlockedDates (date, reason, created_by) VALUES (?, ?, ?)");
  try {
    return $stmt->execute([$date, $reason, $createdBy]);
  } catch (PDOException $e) {
    return false;
  }
}

function removeBlockedDate($id) {
  global $pdo;
  ensureBlockedDatesTable();
  $stmt = $pdo->prepare("DELETE FROM BlockedDates WHERE id = ?");
  return $stmt->execute([$id]);
}

function isDateBlocked($date) {
  global $pdo;
  ensureBlockedDatesTable();
  $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM BlockedDates WHERE date = ?");
  $stmt->execute([$date]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row && $row['cnt'] > 0;
}

function listBlockedDates() {
  global $pdo;
  ensureBlockedDatesTable();
  $stmt = $pdo->query("SELECT * FROM BlockedDates ORDER BY date DESC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
