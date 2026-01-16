<?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/Appointments.php";
require_once "../models/payments.php";

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? '';
$dentistFilter = $_GET['dentist'] ?? '';

// Build WHERE clause for filters
$whereClause = "WHERE a.createdAt BETWEEN '$startDate' AND '$endDate 23:59:59'";
if ($statusFilter) {
    $whereClause .= " AND a.status = '$statusFilter'";
}
if ($dentistFilter) {
    $whereClause .= " AND a.dentistId = '$dentistFilter'";
}

// Filtered Appointments Data
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
           SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
           SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM Appointments a
    $whereClause
");
$stmt->execute();
$appointmentStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Filtered Revenue Data
$stmt = $pdo->prepare("
    SELECT SUM(p.amount) as total_revenue,
           COUNT(p.id) as total_payments
    FROM Payments p
    JOIN Appointments a ON p.appointmentId = a.id
    $whereClause AND p.status = 'confirmed'
");
$stmt->execute();
$revenueStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Appointments by Date (for line chart)
$stmt = $pdo->prepare("
    SELECT DATE(a.createdAt) as date, COUNT(*) as count
    FROM Appointments a
    $whereClause
    GROUP BY DATE(a.createdAt)
    ORDER BY date
");
$stmt->execute();
$appointmentsByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue by Date
$stmt = $pdo->prepare("
    SELECT DATE(a.createdAt) as date, SUM(p.amount) as revenue
    FROM Payments p
    JOIN Appointments a ON p.appointmentId = a.id
    $whereClause AND p.status = 'confirmed'
    GROUP BY DATE(a.createdAt)
    ORDER BY date
");
$stmt->execute();
$revenueByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Appointments by Dentist
$stmt = $pdo->prepare("
    SELECT u.name, COUNT(a.id) as appointment_count,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM Appointments a
    JOIN Users u ON a.dentistId = u.id
    $whereClause
    GROUP BY a.dentistId, u.name
    ORDER BY appointment_count DESC
");
$stmt->execute();
$appointmentsByDentist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Appointments by Status (for filtered period)
$stmt = $pdo->prepare("
    SELECT a.status, COUNT(*) as count
    FROM Appointments a
    $whereClause
    GROUP BY a.status
");
$stmt->execute();
$statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Patient Demographics (age groups)
$stmt = $pdo->prepare("
    SELECT
        CASE
            WHEN TIMESTAMPDIFF(YEAR, u.dateOfBirth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
            WHEN TIMESTAMPDIFF(YEAR, u.dateOfBirth, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
            WHEN TIMESTAMPDIFF(YEAR, u.dateOfBirth, CURDATE()) BETWEEN 36 AND 50 THEN '36-50'
            WHEN TIMESTAMPDIFF(YEAR, u.dateOfBirth, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
            ELSE '65+'
        END as age_group,
        COUNT(*) as count
    FROM Appointments a
    JOIN Users u ON a.clientId = u.id
    $whereClause AND u.dateOfBirth IS NOT NULL
    GROUP BY age_group
    ORDER BY age_group
");
$stmt->execute();
$patientDemographics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Services Popularity
$stmt = $pdo->prepare("
    SELECT t.name, COUNT(a.id) as service_count
    FROM Appointments a
    JOIN Treatments t ON a.treatmentId = t.id
    $whereClause
    GROUP BY t.id, t.name
    ORDER BY service_count DESC
    LIMIT 10
");
$stmt->execute();
$servicesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all dentists for filter dropdown
$stmt = $pdo->prepare("SELECT id, name FROM Users WHERE role = 'dentist' OR role = 'dentist_pending' ORDER BY name");
$stmt->execute();
$dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all statuses for filter dropdown
$statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

// Function for simple linear regression prediction
function linearRegression($data, $futureDays = 30) {
    $n = count($data);
    if ($n < 2) return [];

    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumXX = 0;

    foreach ($data as $i => $point) {
        $x = $i + 1; // day index
        $y = $point['count'] ?? $point['revenue'] ?? 0;
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumXX += $x * $x;
    }

    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;

    $predictions = [];
    for ($i = 1; $i <= $futureDays; $i++) {
        $x = $n + $i;
        $predicted = $slope * $x + $intercept;
        $predictions[] = max(0, round($predicted)); // ensure non-negative
    }

    return $predictions;
}

// Predict future appointments
$predictedAppointments = linearRegression($appointmentsByDate, 30);
$nextMonthAppointments = array_sum($predictedAppointments);

// Predict future revenue
$predictedRevenue = linearRegression($revenueByDate, 30);
$nextMonthRevenue = array_sum($predictedRevenue);
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <a href="reports.php" class="back-btn">← Back to Reports</a>
    <h1>Detailed Analysis</h1>
    <p>In-depth reports with advanced filtering and predictive insights</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/admin_logo.svg" alt="Admin Icon" style="width: 60px; height: 60px;">
  </div>
</div>

<div class="container">

<!-- Filters Section -->
<div class="filters-section">
  <h2>Filters</h2>

  <!-- Date Presets -->
  <div class="date-presets">
    <button type="button" onclick="setDateRange('7')" class="preset-btn">Last 7 Days</button>
    <button type="button" onclick="setDateRange('30')" class="preset-btn">Last 30 Days</button>
    <button type="button" onclick="setDateRange('90')" class="preset-btn">Last 3 Months</button>
    <button type="button" onclick="setDateRange('365')" class="preset-btn">Last Year</button>
    <button type="button" onclick="setDateRange('month')" class="preset-btn">This Month</button>
  </div>

  <form method="GET" class="filters-form">
    <div class="filter-row">
      <div class="filter-group">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
      </div>
      <div class="filter-group">
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
      </div>
      <div class="filter-group">
        <label for="status">Status:</label>
        <select id="status" name="status">
          <option value="">All Statuses</option>
          <?php foreach ($statuses as $status): ?>
            <option value="<?php echo $status; ?>" <?php echo $statusFilter == $status ? 'selected' : ''; ?>>
              <?php echo ucfirst($status); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label for="dentist">Dentist:</label>
        <select id="dentist" name="dentist">
          <option value="">All Dentists</option>
          <?php foreach ($dentists as $dentist): ?>
            <option value="<?php echo $dentist['id']; ?>" <?php echo $dentistFilter == $dentist['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($dentist['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <button type="submit" class="btn-primary">Apply Filters</button>
        <a href="analysis.php" class="btn-secondary">Reset</a>
      </div>
    </div>
  </form>
</div>

<!-- Key Metrics -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Total Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $appointmentStats['total'] ?? 0; ?></h3>
      <p>Total Appointments</p>
      <small><?php echo $startDate; ?> to <?php echo $endDate; ?></small>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/payments.svg" alt="Total Revenue">
    </div>
    <div class="stat-content">
      <h3>₱<?php echo number_format($revenueStats['total_revenue'] ?? 0, 2); ?></h3>
      <p>Total Revenue</p>
      <small><?php echo $revenueStats['total_payments'] ?? 0; ?> payments</small>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/tick_icon.svg" alt="Completed Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $appointmentStats['completed'] ?? 0; ?></h3>
      <p>Completed</p>
      <small><?php echo $appointmentStats['total'] ? round(($appointmentStats['completed'] / $appointmentStats['total']) * 100, 1) : 0; ?>% completion rate</small>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/cross_icon.png" alt="Cancelled Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $appointmentStats['cancelled'] ?? 0; ?></h3>
      <p>Cancelled</p>
      <small><?php echo $appointmentStats['total'] ? round(($appointmentStats['cancelled'] / $appointmentStats['total']) * 100, 1) : 0; ?>% cancellation rate</small>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Patient Retention">
    </div>
    <div class="stat-content">
      <h3><?php echo $appointmentStats['total'] ? round(($appointmentStats['completed'] / $appointmentStats['total']) * 100, 1) : 0; ?>%</h3>
      <p>Completion Rate</p>
      <small>Successful appointments</small>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Avg Treatment Duration">
    </div>
    <div class="stat-content">
      <h3><?php echo $appointmentStats['completed'] > 0 ? round($appointmentStats['total'] / $appointmentStats['completed'], 1) : 0; ?> days</h3>
      <p>Avg Duration</p>
      <small>From booking to completion</small>
    </div>
  </div>
</div>

<!-- Predictive Metrics -->
<div class="stats-grid">
  <div class="stat-card predictive">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Predicted Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $nextMonthAppointments; ?></h3>
      <p>Predicted Appointments</p>
      <small>Next 30 days</small>
    </div>
  </div>

  <div class="stat-card predictive">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/payments.svg" alt="Predicted Revenue">
    </div>
    <div class="stat-content">
      <h3>₱<?php echo number_format($nextMonthRevenue, 2); ?></h3>
      <p>Predicted Revenue</p>
      <small>Next 30 days</small>
    </div>
  </div>
</div>

<!-- Appointments Trend Chart -->
<div class="activity-section">
  <h2>Appointments Trend</h2>
  <div class="chart-container">
    <canvas id="appointmentsTrendChart"></canvas>
  </div>
</div>

<!-- Revenue Trend Chart -->
<div class="activity-section">
  <h2>Revenue Trend</h2>
  <div class="chart-container">
    <canvas id="revenueTrendChart"></canvas>
  </div>
</div>

<!-- Status Distribution -->
<div class="activity-section">
  <h2>Appointment Status Distribution</h2>
  <div class="chart-container">
    <canvas id="statusDistributionChart"></canvas>
  </div>
</div>

<!-- Patient Demographics -->
<div class="activity-section">
  <h2>Patient Demographics</h2>
  <div class="chart-container">
    <canvas id="demographicsChart"></canvas>
  </div>
</div>

<!-- Services Popularity -->
<div class="activity-section">
  <h2>Most Popular Services</h2>
  <div class="chart-container">
    <canvas id="servicesChart"></canvas>
  </div>
</div>

<!-- Dentist Performance -->
<div class="activity-section">
  <h2>Dentist Performance</h2>
  <div class="performance-table">
    <table>
      <thead>
        <tr>
          <th>Dentist</th>
          <th>Total Appointments</th>
          <th>Completed</th>
          <th>Completion Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appointmentsByDentist as $dentist): ?>
          <tr>
            <td><?php echo htmlspecialchars($dentist['name']); ?></td>
            <td><?php echo $dentist['appointment_count']; ?></td>
            <td><?php echo $dentist['completed_count']; ?></td>
            <td><?php echo $dentist['appointment_count'] ? round(($dentist['completed_count'] / $dentist['appointment_count']) * 100, 1) : 0; ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Export Options -->
<div class="activity-section">
  <h2>Export Data</h2>
  <div class="export-options">
    <button onclick="exportToCSV()" class="btn-primary">Export to CSV</button>
    <button onclick="exportToPDF()" class="btn-secondary">Export to PDF</button>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Appointments Trend Chart
const appointmentsTrendCtx = document.getElementById('appointmentsTrendChart');
if (appointmentsTrendCtx) {
  const appointmentsData = <?php echo json_encode($appointmentsByDate); ?>;
  const predictedAppointments = <?php echo json_encode($predictedAppointments); ?>;
  const lastDate = appointmentsData.length > 0 ? new Date(appointmentsData[appointmentsData.length - 1].date) : new Date();
  const futureLabels = [];
  for (let i = 1; i <= predictedAppointments.length; i++) {
    const futureDate = new Date(lastDate);
    futureDate.setDate(lastDate.getDate() + i);
    futureLabels.push(futureDate.toISOString().split('T')[0]);
  }
  const allLabels = appointmentsData.map(item => item.date).concat(futureLabels);
  const allData = appointmentsData.map(item => item.count).concat(predictedAppointments);

  new Chart(appointmentsTrendCtx, {
    type: 'line',
    data: {
      labels: allLabels,
      datasets: [{
        label: 'Historical Appointments',
        data: appointmentsData.map(item => item.count),
        borderColor: 'rgb(59, 130, 246)',
        backgroundColor: 'rgba(59, 130, 246, 0.3)',
        fill: true,
        tension: 0.4
      }, {
        label: 'Predicted Appointments',
        data: allData,
        borderColor: 'rgb(255, 99, 132)',
        backgroundColor: 'rgba(255, 99, 132, 0.2)',
        borderDash: [5, 5],
        fill: false,
        tension: 0.4,
        pointStyle: 'circle',
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });
}

// Revenue Trend Chart
const revenueTrendCtx = document.getElementById('revenueTrendChart');
if (revenueTrendCtx) {
  const revenueData = <?php echo json_encode($revenueByDate); ?>;
  const predictedRevenue = <?php echo json_encode($predictedRevenue); ?>;
  const lastDate = revenueData.length > 0 ? new Date(revenueData[revenueData.length - 1].date) : new Date();
  const futureLabels = [];
  for (let i = 1; i <= predictedRevenue.length; i++) {
    const futureDate = new Date(lastDate);
    futureDate.setDate(lastDate.getDate() + i);
    futureLabels.push(futureDate.toISOString().split('T')[0]);
  }
  const allLabels = revenueData.map(item => item.date).concat(futureLabels);
  const allData = revenueData.map(item => item.revenue).concat(predictedRevenue);

  new Chart(revenueTrendCtx, {
    type: 'line',
    data: {
      labels: allLabels,
      datasets: [{
        label: 'Historical Revenue (₱)',
        data: revenueData.map(item => item.revenue),
        borderColor: 'rgb(34, 197, 94)',
        backgroundColor: 'rgba(34, 197, 94, 0.1)',
        tension: 0.1
      }, {
        label: 'Predicted Revenue (₱)',
        data: allData,
        borderColor: 'rgb(255, 159, 64)',
        backgroundColor: 'rgba(255, 159, 64, 0.1)',
        borderDash: [5, 5],
        tension: 0.1,
        pointStyle: 'circle',
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return '₱' + value.toLocaleString();
            }
          }
        }
      }
    }
  });
}

// Status Distribution Chart
const statusDistributionCtx = document.getElementById('statusDistributionChart');
if (statusDistributionCtx) {
  const statusData = <?php echo json_encode($statusDistribution); ?>;
  new Chart(statusDistributionCtx, {
    type: 'doughnut',
    data: {
      labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
      datasets: [{
        data: statusData.map(item => item.count),
        backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#ef4444'],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
}

// Patient Demographics Chart
const demographicsCtx = document.getElementById('demographicsChart');
if (demographicsCtx) {
  const demographicsData = <?php echo json_encode($patientDemographics); ?>;
  new Chart(demographicsCtx, {
    type: 'doughnut',
    data: {
      labels: demographicsData.map(item => item.age_group),
      datasets: [{
        data: demographicsData.map(item => item.count),
        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
}

// Services Popularity Chart
const servicesCtx = document.getElementById('servicesChart');
if (servicesCtx) {
  const servicesData = <?php echo json_encode($servicesData); ?>;
  new Chart(servicesCtx, {
    type: 'bar',
    data: {
      labels: servicesData.map(item => item.name),
      datasets: [{
        label: 'Appointments',
        data: servicesData.map(item => item.service_count),
        backgroundColor: 'rgba(59, 130, 246, 0.8)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });
}

// Date preset functions
function setDateRange(preset) {
  const today = new Date();
  let startDate, endDate;

  switch(preset) {
    case '7':
      startDate = new Date(today);
      startDate.setDate(today.getDate() - 7);
      endDate = new Date(today);
      break;
    case '30':
      startDate = new Date(today);
      startDate.setDate(today.getDate() - 30);
      endDate = new Date(today);
      break;
    case '90':
      startDate = new Date(today);
      startDate.setDate(today.getDate() - 90);
      endDate = new Date(today);
      break;
    case '365':
      startDate = new Date(today);
      startDate.setDate(today.getDate() - 365);
      endDate = new Date(today);
      break;
    case 'month':
      startDate = new Date(today.getFullYear(), today.getMonth(), 1);
      endDate = new Date(today);
      break;
    default:
      return;
  }

  document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
  document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
}

// Export functions
function exportToCSV() {
  // Simple CSV export - in a real app, you'd generate proper CSV data
  alert('CSV export functionality would be implemented here');
}

function exportToPDF() {
  // Simple PDF export - in a real app, you'd use a library like jsPDF
  alert('PDF export functionality would be implemented here');
}
</script>

<style>
.dashboard-header {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
  padding: 3rem 1.25rem;
  border-radius: 0 0 1rem 1rem;
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.welcome-section h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
  font-weight: 700;
}

.welcome-section p {
  margin: 0 0 1rem 0;
  opacity: 0.95;
  font-size: 1.1rem;
}

.back-btn {
  display: inline-block;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.2);
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
  transition: background-color 0.2s;
  border: 1px solid rgba(255, 255, 255, 0.3);
  margin-bottom: 1rem;
}

.back-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 15px;
  border: 1px solid #e5e7eb;
}

.stat-icon {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 10px;
}

.stat-icon img {
  width: 30px;
  height: 30px;
}

.stat-content h3 {
  margin: 0 0 5px 0;
  font-size: 2rem;
  color: #0f172a;
  font-weight: 700;
}

.stat-content p {
  margin: 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.activity-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
}

.activity-section h2 {
  margin: 0 0 20px 0;
  font-size: 1.5rem;
  color: #0f172a;
  border-bottom: 2px solid #eef2f7;
  padding-bottom: 10px;
}

.filters-section {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
}

.filters-form {
  margin-top: 15px;
}

.filter-row {
  display: flex;
  gap: 20px;
  align-items: end;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filter-group label {
  font-weight: 500;
  color: #374151;
}

.filter-group input,
.filter-group select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.filter-group button {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
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

.performance-table {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.performance-table table {
  width: 100%;
  border-collapse: collapse;
}

.performance-table th,
.performance-table td {
  padding: 15px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.performance-table th {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
}

.performance-table tbody tr:hover {
  background: #f9fafb;
}

.export-options {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.chart-container {
  position: relative;
  height: 300px;
  width: 100%;
}

.stat-card.predictive {
  border: 2px solid #10b981;
  background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
}

.date-presets {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.preset-btn {
  padding: 8px 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #f9fafb;
  color: #374151;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.preset-btn:hover {
  background: #e5e7eb;
  border-color: #9ca3af;
}

.preset-btn:active {
  background: #d1d5db;
}

@media (max-width: 768px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .growth-metrics {
    grid-template-columns: 1fr;
  }

  .dashboard-header {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }

  .welcome-section h1 {
    font-size: 2rem;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
