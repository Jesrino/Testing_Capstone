<?php
include("../includes/header.php");
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') {
  header('Location: ' . $base_url . '/public/login.php'); exit;
}
require_once "../models/appointments.php";
require_once "../models/users.php";

$dentistId = $_SESSION['user_id'];
$user = getUserById($dentistId);

// Get date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of month
$endDate = $_GET['end_date'] ?? date('Y-m-d');

global $pdo;

// Get total appointments in date range
$stmt = $pdo->prepare("
  SELECT COUNT(*) as total, 
         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
         SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
         SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
  FROM Appointments
  WHERE dentistId = ? AND date BETWEEN ? AND ?
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get appointments by treatment
$stmt = $pdo->prepare("
  SELECT t.name, COUNT(a.id) as count, AVG(t.price) as avgPrice
  FROM Appointments a
  LEFT JOIN AppointmentTreatments at ON a.id = at.appointmentId
  LEFT JOIN Treatments t ON at.treatmentId = t.id
  WHERE a.dentistId = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
  GROUP BY t.id, t.name
  ORDER BY count DESC
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$treatmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient statistics
$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT a.clientId) as totalPatients,
         COUNT(a.id) as totalAppointments
  FROM Appointments a
  WHERE a.dentistId = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$patientStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get most frequent patients
$stmt = $pdo->prepare("
  SELECT u.id, u.name, COUNT(a.id) as appointmentCount
  FROM Appointments a
  JOIN Users u ON a.clientId = u.id
  WHERE a.dentistId = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
  GROUP BY u.id, u.name
  ORDER BY appointmentCount DESC
  LIMIT 10
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$frequentPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily appointment count for chart
$stmt = $pdo->prepare("
  SELECT DATE(date) as apptDate, COUNT(*) as count
  FROM Appointments
  WHERE dentistId = ? AND date BETWEEN ? AND ? AND status = 'completed'
  GROUP BY DATE(date)
  ORDER BY DATE(date) ASC
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartDates = json_encode(array_column($dailyStats, 'apptDate'));
$chartCounts = json_encode(array_column($dailyStats, 'count'));

// Get revenue statistics for the dentist
$stmt = $pdo->prepare("
  SELECT SUM(p.amount) as totalRevenue,
         COUNT(p.id) as totalPayments
  FROM Payments p
  JOIN Appointments a ON p.appointmentId = a.id
  WHERE a.dentistId = ? AND a.date BETWEEN ? AND ? AND p.status = 'confirmed'
");
$stmt->execute([$dentistId, $startDate, $endDate]);
$revenueStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Patient retention (returning patients)
$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT clientId) as returningPatients
  FROM Appointments
  WHERE dentistId = ? AND date BETWEEN ? AND ? AND clientId IN (
    SELECT clientId FROM Appointments
    WHERE dentistId = ? AND date < ?
    GROUP BY clientId HAVING COUNT(*) > 0
  )
");
$stmt->execute([$dentistId, $startDate, $endDate, $dentistId, $startDate]);
$returningPatients = $stmt->fetchColumn();

// Completion rate
$completionRate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;
?>

<div class="container">
  <header class="dashboard-hero">
    <div class="hero-inner">
      <div class="hero-text">
        <h1>Appointment Reports</h1>
        <p>Analyze your appointment trends and patient statistics</p>
      </div>
      <div class="hero-illustration">
        <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Reports" style="width:64px;height:64px;opacity:0.95;" />
      </div>
    </div>
  </header>

  <!-- Stats row (centered) -->
  <div class="stats-row">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
          <h3><?php echo $stats['total'] ?? 0; ?></h3>
          <p>Total Appointments</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">✓</div>
        <div class="stat-content">
          <h3><?php echo $stats['completed'] ?? 0; ?></h3>
          <p>Completed</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-content">
          <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
          <p>Confirmed</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-content">
          <h3><?php echo $patientStats['totalPatients'] ?? 0; ?></h3>
          <p>Unique Patients</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Date Range Filter -->
  <div class="filter-section">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
      <div class="filter-group">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
      </div>
      <div class="filter-group">
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
      </div>
      <button type="submit" class="btn-primary">Filter</button>
    </form>
  </div>

  <!-- Summary Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📊</div>
      <div class="stat-content">
        <h3><?php echo $stats['total'] ?? 0; ?></h3>
        <p>Total Appointments</p>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">✓</div>
      <div class="stat-content">
        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
        <p>Completed</p>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-content">
        <h3>₱<?php echo number_format($revenueStats['totalRevenue'] ?? 0, 2); ?></h3>
        <p>Total Revenue</p>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">📈</div>
      <div class="stat-content">
        <h3><?php echo $completionRate; ?>%</h3>
        <p>Completion Rate</p>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">🔄</div>
      <div class="stat-content">
        <h3><?php echo $patientStats['totalPatients'] > 0 ? round(($returningPatients / $patientStats['totalPatients']) * 100, 1) : 0; ?>%</h3>
        <p>Patient Retention</p>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-content">
        <h3><?php echo $patientStats['totalPatients'] ?? 0; ?></h3>
        <p>Unique Patients</p>
      </div>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="reports-content">
    <div class="chart-container">
      <h2>Daily Appointments Trend</h2>
      <canvas id="dailyChart" width="400" height="150"></canvas>
    </div>
  </div>

  <!-- Treatment Statistics -->
  <div class="reports-content">
    <h2>Most Performed Treatments</h2>
    <?php if (!empty($treatmentStats)): ?>
      <div class="treatment-grid">
        <?php foreach ($treatmentStats as $treatment): ?>
          <div class="treatment-card">
            <h3><?php echo htmlspecialchars($treatment['name'] ?? 'Unknown Treatment'); ?></h3>
            <p class="count"><?php echo $treatment['count']; ?> procedures</p>
            <p class="price">Avg: $<?php echo number_format($treatment['avgPrice'] ?? 0, 2); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color: #6b7280;">No completed appointments in this date range.</p>
    <?php endif; ?>
  </div>

  <!-- Frequent Patients -->
  <div class="reports-content">
    <h2>Most Frequent Patients</h2>
    <?php if (!empty($frequentPatients)): ?>
      <div class="patient-table">
        <table>
          <thead>
            <tr>
              <th>Patient Name</th>
              <th>Appointment Count</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($frequentPatients as $patient): ?>
              <tr>
                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                <td>
                  <span class="badge"><?php echo $patient['appointmentCount']; ?></span>
                </td>
                <td>
                  <a href="<?php echo $base_url; ?>/dentist/patient_details.php?id=<?php echo $patient['id']; ?>" class="btn-link">View Details</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="color: #6b7280;">No patient data available for this date range.</p>
    <?php endif; ?>
  </div>

  <!-- Export Data -->
  <div class="reports-content">
    <h2>Export Reports</h2>
    <div class="export-options">
      <button onclick="exportToCSV()" class="btn-primary">Export to CSV</button>
      <button onclick="exportToPDF()" class="btn-secondary">Export to PDF</button>
    </div>
  </div>
</div>

<style>
/* Hero header like admin dashboard */
.dashboard-hero {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
  padding: 3.25rem 1.25rem;
  border-radius: 0 0 1rem 1rem;
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
  margin-bottom: 24px;
}
.dashboard-hero .hero-inner { max-width: 1200px; margin: 0 auto; display:flex; align-items:center; justify-content:space-between; gap:20px; }
.dashboard-hero h1 { margin: 0; font-size: 2.75rem; font-weight: 700; }
.dashboard-hero p { margin: 0; opacity: 0.95; font-size: 1.05rem; }

/* Centered stats row */
.stats-row { max-width: 1200px; margin: 18px auto; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
.stat-card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 8px 20px rgba(10,10,10,0.06); display:flex; align-items:center; gap:16px; border: none; }
.stat-card .stat-icon { width:56px; height:56px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; background: #f3f4f6; border-radius:10px; }
.stat-content h3 { margin: 0 0 4px 0; font-size: 2rem; color: #0f172a; }
.stat-content p { margin: 0; color: #6b7280; font-size: 0.95rem; }

/* Filter card */
.filter-section { background: white; padding: 18px; border-radius: 12px; margin: 22px auto; box-shadow: 0 6px 18px rgba(0,0,0,0.04); max-width: 1200px; }
.filter-group { display:flex; flex-direction:column; gap:6px; }
.filter-group label { font-weight:600; color:#374151; font-size:0.9rem; }
.filter-group input { padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; }
.btn-primary { background:#556B2F; color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-weight:700; }
.btn-primary:hover { background:#445621; }

/* Reports content and tables */
.reports-content { background:white; padding:24px; border-radius:12px; margin-bottom:22px; box-shadow:0 6px 18px rgba(0,0,0,0.03); max-width:1200px; margin-left:auto; margin-right:auto; }
.reports-content h2 { margin:0 0 18px 0; font-size:1.25rem; color:#0f172a; border-bottom:2px solid #eef2f7; padding-bottom:12px; }
.chart-container { position:relative; height:280px; }
.treatment-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
.treatment-card { background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #eef2f7; text-align:center; }
.treatment-card h3 { margin:0 0 8px 0; font-size:1rem; }
.treatment-card .count { color:#556B2F; font-weight:700; font-size:1.25rem; }

.patient-table { overflow-x:auto; }
.patient-table table { width:100%; border-collapse:collapse; }
.patient-table th { background:#f8fafc; padding:12px; text-align:left; font-weight:700; color:#374151; border-bottom:1px solid #e6eef6; }
.patient-table td { padding:12px; border-bottom:1px solid #eef2f7; }
.badge { background:#dbeafe; color:#1e40af; padding:4px 12px; border-radius:20px; font-weight:700; }
.btn-link { color:#556B2F; text-decoration:none; font-weight:700; }
.btn-link:hover { text-decoration:underline; }

.export-options {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn-primary, .btn-secondary {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover {
  background: #e5e7eb;
  transform: translateY(-1px);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily appointments chart
const chartDates = <?php echo $chartDates; ?>;
const chartCounts = <?php echo $chartCounts; ?>;

if (chartDates.length > 0) {
  const ctx = document.getElementById('dailyChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartDates,
      datasets: [{
        label: 'Completed Appointments',
        data: chartCounts,
        borderColor: '#556B2F',
        backgroundColor: 'rgba(85, 107, 47, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 4,
        pointBackgroundColor: '#556B2F',
        pointBorderColor: '#fff',
        pointBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'bottom'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: Math.max(...chartCounts) + 2
        }
      }
    }
  });
}

// Export functions
function exportToCSV() {
  // Prepare CSV data
  const csvData = [
    ['Metric', 'Value'],
    ['Total Appointments', '<?php echo $stats['total'] ?? 0; ?>'],
    ['Completed Appointments', '<?php echo $stats['completed'] ?? 0; ?>'],
    ['Total Revenue', '₱<?php echo number_format($revenueStats['totalRevenue'] ?? 0, 2); ?>'],
    ['Completion Rate', '<?php echo $completionRate; ?>%'],
    ['Patient Retention', '<?php echo $patientStats['totalPatients'] > 0 ? round(($returningPatients / $patientStats['totalPatients']) * 100, 1) : 0; ?>%'],
    ['Unique Patients', '<?php echo $patientStats['totalPatients'] ?? 0; ?>']
  ];

  // Convert to CSV string
  const csvContent = csvData.map(row => row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')).join('\n');

  // Download CSV
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', 'dentist_reports_<?php echo date('Y-m-d'); ?>.csv');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function exportToPDF() {
  // Simple PDF export - in a real app, you'd use a library like jsPDF
  alert('PDF export functionality requires additional libraries. Please use CSV export for now.');
}
</script>

<?php include("../includes/footer.php"); ?>
