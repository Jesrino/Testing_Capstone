<?php
require_once __DIR__ . '/../includes/db.php';

function logPayment($appointmentId, $amount, $method, $status, $txId=null) {
  global $pdo;
  $stmt = $pdo->prepare("INSERT INTO Payments (appointmentId, amount, method, status, transactionId) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$appointmentId, $amount, $method, $status, $txId]);
  return $pdo->lastInsertId();
}

function getPaymentsByAppointment($appointmentId) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM Payments WHERE appointmentId = ? ORDER BY createdAt DESC");
  $stmt->execute([$appointmentId]);
  return $stmt->fetchAll();
}

function getBillForAppointment($appointmentId) {
  global $pdo;
  $stmt = $pdo->prepare(
    "SELECT t.id as treatmentId, t.name, t.price
     FROM AppointmentTreatments apt
     JOIN Treatments t ON apt.treatmentId = t.id
     WHERE apt.appointmentId = ?"
  );
  $stmt->execute([$appointmentId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $total = 0;
  foreach ($items as $it) {
    $total += floatval($it['price']);
  }

  return [
    'items' => $items,
    'total' => $total
  ];
}

function getPaidAmountForAppointment($appointmentId) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as paid FROM Payments WHERE appointmentId = ? AND status = 'confirmed'");
  $stmt->execute([$appointmentId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return floatval($row['paid'] ?? 0);
}

function getOutstandingAmount($appointmentId) {
  $bill = getBillForAppointment($appointmentId);
  $paid = getPaidAmountForAppointment($appointmentId);
  $outstanding = max(0, $bill['total'] - $paid);
  return $outstanding;
}

function isPaymentComplete($appointmentId) {
  $outstanding = getOutstandingAmount($appointmentId);
  return $outstanding <= 0;
}

function getPaymentStatus($appointmentId) {
  global $pdo;
  $bill = getBillForAppointment($appointmentId);
  $paid = getPaidAmountForAppointment($appointmentId);
  $outstanding = getOutstandingAmount($appointmentId);
  
  return [
    'total' => $bill['total'],
    'paid' => $paid,
    'outstanding' => $outstanding,
    'isComplete' => $outstanding <= 0,
    'percentagePaid' => $bill['total'] > 0 ? round(($paid / $bill['total']) * 100, 2) : 0
  ];
}
