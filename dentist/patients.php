<?php
include("../includes/header.php");
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') { header('Location: /public/login.php'); exit; }
require_once "../models/Appointments.php";
require_once "../models/users.php";

$dentistId = $_SESSION['user_id'];

// Get all patients for this dentist
$stmt = $pdo->prepare("SELECT DISTINCT clientId FROM Appointments WHERE dentistId = ?");
$stmt->execute([$dentistId]);
$patientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$patients = [];
if (!empty($patientIds)) {
  $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
  $stmt = $pdo->prepare("SELECT * FROM Users WHERE id IN ($placeholders)");
  $stmt->execute($patientIds);
  $patients = $stmt->fetchAll();
}

// Get patient statistics
$totalPatients = count($patients);

// Get recent appointments count (last 30 days)
$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT clientId) as recentPatients
  FROM Appointments
  WHERE dentistId = ? AND createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$dentistId]);
$recentPatients = $stmt->fetch(PDO::FETCH_ASSOC)['recentPatients'];

// Get upcoming appointments
$stmt = $pdo->prepare("
  SELECT COUNT(*) as upcomingCount
  FROM Appointments
  WHERE dentistId = ? AND date >= CURDATE() AND status IN ('pending', 'confirmed')
");
$stmt->execute([$dentistId]);
$upcomingAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['upcomingCount'];

// Get today's appointments
$stmt = $pdo->prepare("
  SELECT COUNT(*) as todayCount
  FROM Appointments
  WHERE dentistId = ? AND date = CURDATE()
");
$stmt->execute([$dentistId]);
$todayAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['todayCount'];
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1>My Patients</h1>
    <p>Manage your patient information and appointments</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Patients Icon" style="width: 60px; height: 60px;">
  </div>
</div>

<div class="container">

<!-- Patient Statistics -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Total Patients">
    </div>
    <div class="stat-content">
      <h3><?php echo $totalPatients; ?></h3>
      <p>Total Patients</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Today's Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $todayAppointments; ?></h3>
      <p>Today</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="Upcoming">
    </div>
    <div class="stat-content">
      <h3><?php echo $upcomingAppointments; ?></h3>
      <p>Upcoming</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/doctor_icon.svg" alt="Recent">
    </div>
    <div class="stat-content">
      <h3><?php echo $recentPatients; ?></h3>
      <p>Recent (30 days)</p>
    </div>
  </div>
</div>

<!-- Search and Filter -->
<div class="filter-section">
  <div class="search-container">
    <input type="text" id="patientSearch" placeholder="Search patients by name or email..." onkeyup="filterPatients()">
    <img src="<?php echo $base_url; ?>/assets/images/search_icon.svg" alt="Search" style="width: 20px; height: 20px; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); opacity: 0.5;">
  </div>
</div>

<!-- Patients List -->
<div class="patients-section">
  <h2>All Patients</h2>
  <?php if (!empty($patients)): ?>
    <div class="patients-grid" id="patientsGrid">
      <?php foreach ($patients as $patient): ?>
        <div class="patient-card">
          <div class="patient-header">
            <div class="patient-avatar">
              <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Patient Avatar">
            </div>
            <div class="patient-info">
              <h3><?php echo htmlspecialchars($patient['name']); ?></h3>
              <p><?php echo htmlspecialchars($patient['email']); ?></p>
            </div>
          </div>
          <div class="patient-details">
            <?php if (!empty($patient['phone'])): ?>
              <div class="detail-item">
                <strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($patient['dateOfBirth'])): ?>
              <div class="detail-item">
                <strong>Age:</strong> <?php echo date_diff(date_create($patient['dateOfBirth']), date_create('today'))->y; ?> years old
              </div>
            <?php endif; ?>
            <?php if (!empty($patient['gender'])): ?>
              <div class="detail-item">
                <strong>Gender:</strong> <?php echo ucfirst(htmlspecialchars($patient['gender'])); ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($patient['lastVisit'])): ?>
              <div class="detail-item">
                <strong>Last Visit:</strong> <?php echo date('M d, Y', strtotime($patient['lastVisit'])); ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($patient['nextAppointment'])): ?>
              <div class="detail-item">
                <strong>Next Appointment:</strong> <?php echo date('M d, Y', strtotime($patient['nextAppointment'])); ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="patient-actions">
            <a href="<?php echo $base_url; ?>/dentist/patient_details.php?id=<?php echo $patient['id']; ?>" class="btn-view">View Details</a>
            <a href="<?php echo $base_url; ?>/dentist/schedule.php?patient=<?php echo $patient['id']; ?>" class="btn-schedule">Schedule</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="no-patients">
      <div class="no-patients-icon">
        <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="No Patients">
      </div>
      <h3>No patients yet</h3>
      <p>You haven't had any patients assigned to you yet.</p>
    </div>
  <?php endif; ?>
</div>

</div>

<script>
// Filter patients based on search input
function filterPatients() {
  const searchTerm = document.getElementById('patientSearch').value.toLowerCase();
  const patientCards = document.querySelectorAll('.patient-card');

  patientCards.forEach(card => {
    const name = card.querySelector('h3').textContent.toLowerCase();
    const email = card.querySelector('p').textContent.toLowerCase();

    if (name.includes(searchTerm) || email.includes(searchTerm)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
}
</script>

<style>
.search-container {
  position: relative;
  max-width: 400px;
  margin: 0 auto;
}

.search-container input {
  width: 100%;
  padding: 12px 45px 12px 15px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 1rem;
  box-sizing: border-box;
}

.patients-section {
  margin-top: 30px;
}

.patients-section h2 {
  margin-bottom: 20px;
  color: #0a0a0a;
}

.patients-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.patient-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
  transition: transform 0.2s, box-shadow 0.2s;
}

.patient-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.patient-header {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.patient-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  overflow: hidden;
  margin-right: 15px;
  border: 2px solid #e5e7eb;
}

.patient-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.patient-info h3 {
  margin: 0 0 5px 0;
  color: #0a0a0a;
  font-size: 1.2rem;
}

.patient-info p {
  margin: 0;
  color: #666;
  font-size: 0.9rem;
}

.patient-details {
  margin-bottom: 15px;
}

.detail-item {
  margin-bottom: 8px;
  font-size: 0.9rem;
  color: #555;
}

.detail-item strong {
  color: #0a0a0a;
}

.patient-actions {
  display: flex;
  gap: 10px;
}

.btn-view, .btn-schedule {
  flex: 1;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  text-decoration: none;
  text-align: center;
  font-size: 0.9rem;
  font-weight: 500;
  transition: background 0.2s;
}

.btn-view {
  background: #f3f4f6;
  color: #374151;
}

.btn-view:hover {
  background: #e5e7eb;
}

.btn-schedule {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
}

.btn-schedule:hover {
  background: linear-gradient(135deg, #4a5f26 0%, #0271b3 100%);
}

.no-patients {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.no-patients-icon {
  margin-bottom: 20px;
}

.no-patients-icon img {
  width: 80px;
  height: 80px;
  opacity: 0.5;
}

.no-patients h3 {
  margin: 0 0 10px 0;
  color: #0a0a0a;
}

.no-patients p {
  margin: 0;
  color: #666;
}

@media (max-width: 768px) {
  .patients-grid {
    grid-template-columns: 1fr;
  }

  .patient-header {
    flex-direction: column;
    text-align: center;
  }

  .patient-avatar {
    margin-right: 0;
    margin-bottom: 10px;
  }

  .patient-actions {
    flex-direction: column;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
