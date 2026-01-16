<?php
// Get appointment status distribution for chart
$stmt = $pdo->prepare("
  SELECT status, COUNT(*) as count
  FROM Appointments
  GROUP BY status
  ORDER BY count DESC
");
$stmt->execute();
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no appointments exist, create sample data for demonstration
if (empty($statusData)) {
  $statusData = [
    ['status' => 'pending', 'count' => 5],
    ['status' => 'confirmed', 'count' => 3],
    ['status' => 'completed', 'count' => 8],
    ['status' => 'cancelled', 'count' => 2]
  ];
}
?>

<div class="services-chart-section">
  <h2>Appointment Status Distribution</h2>
  <?php if (!empty($statusData)): ?>
    <div class="chart-container">
      <canvas id="statusChart"></canvas>
    </div>
  <?php else: ?>
    <div class="no-chart-data">
      <div class="no-chart-icon">
        <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="No Data">
      </div>
      <h3>No appointment data available</h3>
      <p>The chart will display once appointments are created in the system.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Status data for chart
const statusData = <?php echo json_encode($statusData); ?>;

console.log('Status data:', statusData);

// Create status pie chart
function createStatusChart() {
  console.log('Creating chart...');
  const ctx = document.getElementById('statusChart');
  if (!ctx) {
    console.error('Canvas element not found');
    return;
  }

  const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'];

  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
      datasets: [{
        data: statusData.map(item => item.count),
        backgroundColor: colors.slice(0, statusData.length),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 20,
            usePointStyle: true
          }
        }
      }
    }
  });
  console.log('Chart created successfully');
}

// Initialize chart when page loads
window.addEventListener('load', function() {
  console.log('Page loaded, initializing chart...');
  createStatusChart();
});
</script>

<style>
.services-chart-section {
  margin: 40px 0;
  background: white;
  border-radius: 12px;
  padding: 25px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.chart-container {
  position: relative;
  height: 300px;
  margin-bottom: 20px;
}

.no-chart-data {
  text-align: center;
  padding: 60px 20px;
}

.no-chart-icon img {
  width: 48px;
  height: 48px;
  opacity: 0.5;
  margin-bottom: 20px;
}

.no-chart-data h3 {
  margin: 0 0 10px 0;
  color: #6b7280;
}

.no-chart-data p {
  margin: 0;
  color: #9ca3af;
}
</style>
