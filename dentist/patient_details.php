<?php
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') { header('Location: /public/login.php'); exit; }
require_once "../models/Appointments.php";
require_once "../models/users.php";

$patientId = $_GET['patient_id'] ?? null;
if (!$patientId) {
  header('Location: patients.php');
  exit;
}

$dentistId = $_SESSION['user_id'];

// Get patient info
$stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
  header('Location: patients.php');
  exit;
}

include("../includes/header.php");

// Get appointments for this patient with this dentist
$stmt = $pdo->prepare("
  SELECT a.*, s.name as service_name, s.price
  FROM Appointments a
  LEFT JOIN Services s ON a.serviceId = s.id
  WHERE a.clientId = ? AND a.dentistId = ?
  ORDER BY a.appointmentDate DESC, a.appointmentTime DESC
");
$stmt->execute([$patientId, $dentistId]);
$appointments = $stmt->fetchAll();

// Calculate statistics
$totalAppointments = count($appointments);
$completedAppointments = 0;
$totalRevenue = 0;
$lastVisit = null;

foreach ($appointments as $appointment) {
  if ($appointment['status'] === 'completed') {
    $completedAppointments++;
    $totalRevenue += $appointment['price'] ?? 0;
  }
  if (!$lastVisit || strtotime($appointment['appointmentDate']) > strtotime($lastVisit)) {
    $lastVisit = $appointment['appointmentDate'];
  }
}
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1>Patient Details</h1>
    <p><?php echo htmlspecialchars($patient['name']); ?> - Treatment History</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Patient" style="width: 60px; height: 60px; border-radius: 50%;">
  </div>
</div>

<div class="container">

<!-- Patient Info -->
<div class="activity-section" style="margin-bottom: 30px;">
  <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px;">
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
      <h4 style="margin: 0 0 10px 0; color: #0a0a0a;">Patient Name</h4>
      <p style="margin: 0; font-size: 1.1rem; font-weight: 500;"><?php echo htmlspecialchars($patient['name']); ?></p>
    </div>
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
      <h4 style="margin: 0 0 10px 0; color: #0a0a0a;">Email</h4>
      <p style="margin: 0; font-size: 1.1rem;"><?php echo htmlspecialchars($patient['email']); ?></p>
    </div>
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
      <h4 style="margin: 0 0 10px 0; color: #0a0a0a;">Total Appointments</h4>
      <p style="margin: 0; font-size: 1.1rem; font-weight: 500;"><?php echo $totalAppointments; ?></p>
    </div>
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
      <h4 style="margin: 0 0 10px 0; color: #0a0a0a;">Last Visit</h4>
      <p style="margin: 0; font-size: 1.1rem;"><?php echo $lastVisit ? date('M d, Y', strtotime($lastVisit)) : 'Never'; ?></p>
    </div>
  </div>
</div>

<!-- Treatment History -->
<div class="activity-section">
  <h2>Treatment History</h2>
  <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #e5e7eb;">
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Date & Time</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Service</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Status</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Price</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appointments as $appointment): ?>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 12px;">
              <div>
                <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($appointment['appointmentDate'])); ?></div>
                <div style="color: #666; font-size: 0.9rem;"><?php echo date('h:i A', strtotime($appointment['appointmentTime'])); ?></div>
              </div>
            </td>
            <td style="padding: 12px;">
              <?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?>
            </td>
            <td style="padding: 12px;">
              <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;
                <?php
                if ($appointment['status'] === 'completed') echo 'background: #d1fae5; color: #065f46;';
                elseif ($appointment['status'] === 'confirmed') echo 'background: #dbeafe; color: #1e40af;';
                elseif ($appointment['status'] === 'pending') echo 'background: #fef3c7; color: #92400e;';
                elseif ($appointment['status'] === 'cancelled') echo 'background: #fee2e2; color: #dc2626;';
                else echo 'background: #f3f4f6; color: #374151;';
                ?>">
                <?php echo htmlspecialchars($appointment['status']); ?>
              </span>
            </td>
            <td style="padding: 12px;">
              ₱<?php echo number_format($appointment['price'] ?? 0, 2); ?>
            </td>
            <td style="padding: 12px;">
              <?php echo htmlspecialchars($appointment['notes'] ?? 'No notes'); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (empty($appointments)): ?>
    <div style="text-align: center; padding: 40px; color: #666;">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="No appointments" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;">
      <h3>No Treatment History</h3>
      <p>This patient hasn't had any appointments with you yet.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Back Button -->
<div style="margin-top: 30px; text-align: center;">
  <a href="patients.php" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">← Back to Patients</a>
</div>

</div>


