<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/Notifications.php';
require_once __DIR__ . '/BlockedDates.php';

function createAppointment($clientId, $dentistId, $date, $time, $treatmentId = null, $walkInName = null, $walkInPhone = null) {
  global $pdo;
  // Prevent creating appointments on blocked dates
  if (isDateBlocked($date)) {
    return false;
  }
  $stmt = $pdo->prepare("INSERT INTO Appointments (clientId, dentistId, treatmentId, date, time, status, walk_in_name, walk_in_phone) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
  $stmt->execute([$clientId, $dentistId, $treatmentId, $date, $time, $walkInName, $walkInPhone]);
  $appointmentId = $pdo->lastInsertId();

  // Notify admin about new appointment booking
  $adminStmt = $pdo->prepare("SELECT id FROM Users WHERE role = 'admin'");
  $adminStmt->execute();
  $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

  $message = $clientId ? "New appointment booked by client ID: $clientId for $date at $time" : "New walk-in appointment for $walkInName ($walkInPhone) on $date at $time";

  foreach ($admins as $admin) {
    createNotification($admin['id'], 'appointment_booked', $message);
  }

  return $appointmentId;
}

function listClientAppointments($clientId) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM Appointments WHERE clientId = ? ORDER BY date ASC, time ASC");
  $stmt->execute([$clientId]);
  return $stmt->fetchAll();
}

function notifyStatusChange($appointmentId, $newStatus) {
  global $pdo;

  // Get appointment details
  $stmt = $pdo->prepare("SELECT a.*, u.name as clientName FROM Appointments a JOIN Users u ON a.clientId = u.id WHERE a.id = ?");
  $stmt->execute([$appointmentId]);
  $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($appointment) {
    $clientId = $appointment['clientId'];
    $message = "Your appointment on {$appointment['date']} at {$appointment['time']} has been {$newStatus}.";

    if ($newStatus === 'cancelled') {
      createNotification($clientId, 'appointment_cancelled', $message);
    } else {
      createNotification($clientId, 'status_updated', $message);
    }
  }
}
function assignDentist($appointmentId, $dentistId) {
  global $pdo;

  // Check if appointment is already confirmed or completed
  $checkStmt = $pdo->prepare("SELECT status FROM Appointments WHERE id = ?");
  $checkStmt->execute([$appointmentId]);
  $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

  if (!$appointment || in_array($appointment['status'], ['confirmed', 'completed'])) {
    return false; // Cannot reassign if confirmed or completed
  }

  $stmt = $pdo->prepare("UPDATE Appointments SET dentistId = ? WHERE id = ?");
  $result = $stmt->execute([$dentistId, $appointmentId]);

  if ($result) {
    // Get dentist name and email and appointment details for notifications
    $dentistStmt = $pdo->prepare("SELECT name, email FROM Users WHERE id = ?");
    $dentistStmt->execute([$dentistId]);
    $dentist = $dentistStmt->fetch(PDO::FETCH_ASSOC);

    $apptStmt = $pdo->prepare("SELECT a.*, u.name as clientName, u.email as clientEmail FROM Appointments a JOIN Users u ON a.clientId = u.id WHERE a.id = ?");
    $apptStmt->execute([$appointmentId]);
    $appointment = $apptStmt->fetch(PDO::FETCH_ASSOC);

    if ($dentist && $appointment) {
      // Notify dentist about assignment (in-app)
      $dentistMessage = "You have been assigned to appointment ID {$appointmentId} for {$appointment['date']} at {$appointment['time']}. Patient: {$appointment['clientName']}.";
      createNotification($dentistId, 'dentist_assigned', $dentistMessage);

      // Notify client about assigned dentist (in-app)
      $clientMessage = "Dr. {$dentist['name']} has been assigned to your appointment on {$appointment['date']} at {$appointment['time']}.";
      createNotification($appointment['clientId'], 'dentist_assigned', $clientMessage);
      
      // Send email notifications (best-effort) if email addresses exist
      // Send emails using SMTP settings (if present) with retry
      try {
        require_once __DIR__ . '/../includes/SMTPMailer.php';
        // load SMTP settings from DB if available
        $cfgStmt = $pdo->query("SELECT * FROM SMTPSettings ORDER BY id DESC LIMIT 1");
        $cfg = $cfgStmt ? $cfgStmt->fetch(PDO::FETCH_ASSOC) : null;
        $mailer = new SMTPMailer($cfg ?: []);

        if (!empty($dentist['email'])) {
          $to = $dentist['email'];
          $subject = "Assigned: Appointment #{$appointmentId}";
          $body = "Hello Dr. {$dentist['name']},\n\nYou have been assigned to an appointment:\n\nAppointment ID: {$appointmentId}\nDate: {$appointment['date']}\nTime: {$appointment['time']}\nPatient: {$appointment['clientName']}\n\nPlease check your dashboard for details.\n\nRegards,\nDents-City";
          $mailer->send($to, $subject, $body, $cfg['from_email'] ?? null, $cfg['from_name'] ?? null, 3);
        }

        if (!empty($appointment['clientEmail'])) {
          $to = $appointment['clientEmail'];
          $subject = "Your appointment has a dentist assigned";
          $body = "Hello {$appointment['clientName']},\n\nDr. {$dentist['name']} has been assigned to your appointment on {$appointment['date']} at {$appointment['time']}.\n\nRegards,\nDents-City";
          $mailer->send($to, $subject, $body, $cfg['from_email'] ?? null, $cfg['from_name'] ?? null, 3);
        }
      } catch (Exception $e) {
        error_log('SMTPMailer error: ' . $e->getMessage());
      }
    }
  }

  return $result;
}

function updateAppointmentStatus($appointmentId, $newStatus, $dentistId = null) {
  global $pdo;

  // Check current status and validate allowed transitions
  $checkStmt = $pdo->prepare("SELECT status FROM Appointments WHERE id = ?");
  $checkStmt->execute([$appointmentId]);
  $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

  if (!$appointment) {
    return false; // Appointment not found
  }

  $currentStatus = $appointment['status'];

  // Define allowed status transitions
  $allowedTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed'], // Only allow confirmed -> completed
    'completed' => [], // No changes allowed from completed
    'cancelled' => ['pending', 'confirmed'] // Allow rescheduling cancelled appointments
  ];

  if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
    return false; // Invalid transition
  }

  $query = "UPDATE Appointments SET status = ? WHERE id = ?";
  $params = [$newStatus, $appointmentId];

  if ($dentistId) {
    $query .= " AND dentistId = ?";
    $params[] = $dentistId;
  }

  $stmt = $pdo->prepare($query);
  $result = $stmt->execute($params);

  if ($result) {
    notifyStatusChange($appointmentId, $newStatus);
  }

  return $result;
}
