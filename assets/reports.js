// Reports & Analytics JavaScript
// Initialize all charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  initializeCharts();
});

function initializeCharts() {
  // Revenue Line Chart
  const revenueCtx = document.getElementById('revenueChart');
  if (revenueCtx && window.revenueChartData) {
    const revenueChart = new Chart(revenueCtx.getContext('2d'), {
      type: 'line',
      data: {
        labels: window.revenueChartData.labels,
        datasets: [{
          label: 'Revenue (₱)',
          data: window.revenueChartData.values,
          borderColor: 'rgb(75, 192, 192)',
          backgroundColor: 'rgba(75, 192, 192, 0.1)',
          tension: 0.1,
          fill: true
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top',
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Revenue: ₱' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
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

  // Appointment Status Pie Chart
  const statusCtx = document.getElementById('statusChart');
  if (statusCtx && window.statusChartData) {
    const statusLabels = window.statusChartData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
    const statusValues = window.statusChartData.map(item => item.count);
    const statusColors = {
      'pending': '#fbbf24',
      'confirmed': '#10b981',
      'completed': '#3b82f6',
      'cancelled': '#ef4444'
    };
    const backgroundColors = statusLabels.map(label =>
      statusColors[label.toLowerCase()] || '#6b7280'
    );

    const statusChart = new Chart(statusCtx.getContext('2d'), {
      type: 'pie',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusValues,
          backgroundColor: backgroundColors,
          borderColor: backgroundColors.map(color => color),
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
  }

  // Services Bar Chart
  const servicesCtx = document.getElementById('servicesChart');
  if (servicesCtx && window.servicesChartData) {
    const serviceNames = window.servicesChartData.map(item => item.name);
    const serviceCounts = window.servicesChartData.map(item => item.service_count);

    const servicesChart = new Chart(servicesCtx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: serviceNames,
        datasets: [{
          label: 'Appointments',
          data: serviceCounts,
          backgroundColor: 'rgba(59, 130, 246, 0.8)',
          borderColor: 'rgb(59, 130, 246)',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y', // Horizontal bar chart
        responsive: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.x;
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          },
          y: {
            ticks: {
              font: {
                size: 12
              }
            }
          }
        }
      }
    });
  }
}

// Utility functions for reports
function refreshCharts() {
  // This function can be called to refresh all charts
  // Useful if data is updated dynamically
  initializeCharts();
}

function exportChartData(chartId, format = 'png') {
  const canvas = document.getElementById(chartId);
  if (!canvas) return;

  const link = document.createElement('a');
  link.download = `${chartId}_export.${format}`;
  link.href = canvas.toDataURL(`image/${format}`);
  link.click();
}
