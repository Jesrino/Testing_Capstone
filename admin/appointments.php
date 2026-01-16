<?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/Appointments.php";

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = $_POST['appointment_id'];
    $newStatus = $_POST['status'];

    if (updateAppointmentStatus($appointmentId, $newStatus)) {
        $success = "Appointment status updated successfully.";
    } else {
        $error = "Failed to update appointment status.";
    }
}

// Handle dentist assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_dentist'])) {
    $appointmentId = $_POST['appointment_id'];
    $dentistId = $_POST['dentist_id'];

    if (assignDentist($appointmentId, $dentistId)) {
        $success = "Dentist assigned successfully.";
    } else {
        $error = "Failed to assign dentist.";
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dentistFilter = $_GET['dentist'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Build WHERE clause for filters
$whereClause = "WHERE 1=1";
if ($statusFilter) {
    $whereClause .= " AND a.status = '$statusFilter'";
}
if ($dentistFilter) {
    $whereClause .= " AND a.dentistId = '$dentistFilter'";
}
if ($dateFilter) {
    $whereClause .= " AND a.date = '$dateFilter'";
}

// Get all appointments with client and dentist info
global $pdo;
$stmt = $pdo->prepare("
    SELECT a.*, c.name as clientName, c.email as clientEmail, c.phone as clientPhone, d.name as dentistName, a.walk_in_name, a.walk_in_phone
    FROM Appointments a
    LEFT JOIN Users c ON a.clientId = c.id
    LEFT JOIN Users d ON a.dentistId = d.id
    $whereClause
    ORDER BY a.date DESC, a.time DESC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by date for better display
$groupedAppointments = [];
foreach ($appointments as $appt) {
    $date = $appt['date'];
    if (!isset($groupedAppointments[$date])) {
        $groupedAppointments[$date] = [];
    }
    $groupedAppointments[$date][] = $appt;
}

// Get available dentists
$dentistsStmt = $pdo->query("SELECT id, name FROM Users WHERE role = 'dentist' ORDER BY name");
$dentists = $dentistsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by status for summary
$statusCounts = [];
foreach ($appointments as $appt) {
    $status = $appt['status'];
    if (!isset($statusCounts[$status])) {
        $statusCounts[$status] = 0;
    }
    $statusCounts[$status]++;
}

// Calculate summary stats
$today = date('Y-m-d');
$todayAppointments = $groupedAppointments[$today] ?? [];
$upcomingCount = 0;
foreach ($appointments as $appt) {
    if ($appt['date'] >= $today && $appt['status'] === 'confirmed') {
        $upcomingCount++;
    }
}
?>

<div class="container">
    <h1>Appointment Management</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Today's Summary -->
    <div class="schedule-summary">
        <div class="summary-card">
            <h3>Today's Appointments</h3>
            <p class="summary-number"><?php echo count($todayAppointments); ?></p>
        </div>
        <div class="summary-card">
            <h3>Upcoming Confirmed</h3>
            <p class="summary-number"><?php echo $upcomingCount; ?></p>
        </div>
        <div class="summary-card">
            <h3>Total Appointments</h3>
            <p class="summary-number"><?php echo count($appointments); ?></p>
        </div>
        <div class="summary-card">
            <h3>Pending Assignments</h3>
            <p class="summary-number"><?php echo $statusCounts['pending'] ?? 0; ?></p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <h2><i class="fas fa-filter"></i> Filters</h2>
        <form method="GET" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dentist">Dentist:</label>
                    <select id="dentist" name="dentist">
                        <option value="">All Dentists</option>
                        <?php foreach ($dentists as $dentist): ?>
                            <option value="<?php echo $dentist['id']; ?>" <?php echo $dentistFilter == $dentist['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dentist['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo $dateFilter; ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="appointments.php" class="btn-secondary">Clear Filters</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Appointments by Date -->
    <div class="appointments-section">
        <h2>All Appointments</h2>
        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <p>No appointments found matching your criteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedAppointments as $date => $dayAppointments): ?>
                <div class="date-group">
                    <h3 class="date-header">
                        <?php echo date('l, F j, Y', strtotime($date)); ?>
                        <?php if ($date === $today): ?>
                            <span class="today-badge">Today</span>
                        <?php endif; ?>
                        <span class="appointment-count">(<?php echo count($dayAppointments); ?> appointments)</span>
                    </h3>

                    <div class="appointments-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>Contact</th>
                                    <th>Dentist</th>
                                    <th>Assign Dentist</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dayAppointments as $appt): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($appt['time'])); ?></td>
                                        <td>
                                            <?php
                                            if ($appt['clientName']) {
                                                echo htmlspecialchars($appt['clientName']);
                                            } else {
                                                echo htmlspecialchars($appt['walk_in_name']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($appt['clientEmail']) {
                                                echo htmlspecialchars($appt['clientEmail']);
                                                if ($appt['clientPhone']) {
                                                    echo '<br><small>' . htmlspecialchars($appt['clientPhone']) . '</small>';
                                                }
                                            } else {
                                                echo htmlspecialchars($appt['walk_in_phone']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appt['dentistName'] ?: 'Unassigned'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <select name="dentist_id" onchange="this.form.submit()" style="padding: 4px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.875rem;" <?php echo in_array($appt['status'], ['confirmed', 'completed']) ? 'disabled' : ''; ?>>
                                                    <option value="">Choose...</option>
                                                    <?php foreach ($dentists as $dentist): ?>
                                                        <option value="<?php echo $dentist['id']; ?>" <?php echo $appt['dentistId'] == $dentist['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dentist['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="assign_dentist" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <span class="status <?php echo $appt['status']; ?>">
                                                <?php echo ucfirst($appt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 4px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.875rem;" <?php echo $appt['status'] === 'completed' ? 'disabled' : ''; ?>>
                                                    <?php if ($appt['status'] !== 'confirmed'): ?>
                                                        <option value="pending" <?php echo $appt['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <?php endif; ?>
                                                    <option value="confirmed" <?php echo $appt['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="completed" <?php echo $appt['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <?php if ($appt['status'] !== 'confirmed'): ?>
                                                        <option value="cancelled" <?php echo $appt['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.schedule-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    text-align: center;
}

.summary-card h3 {
    margin: 0 0 10px 0;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.summary-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.filters-section {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
}

.filters-section h2 {
    margin: 0 0 20px 0;
    font-size: 1.5rem;
    color: #0f172a;
    border-bottom: 2px solid #eef2f7;
    padding-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.filters-section h2 i {
    color: #556B2F;
}

.filters-form {
    margin-top: 15px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    align-items: end;
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
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
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

.date-group {
    margin-bottom: 30px;
}

.date-header {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 10px;
}

.today-badge {
    background: #3b82f6;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.appointment-count {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.appointments-table {
    overflow-x: auto;
    margin-bottom: 20px;
}

.appointments-table table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.appointments-table th {
    background: #f3f4f6;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.appointments-table td {
    padding: 15px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: top;
}

.appointments-table td small {
    color: #6b7280;
    font-size: 0.875rem;
}

.status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status.pending { background: #fef3c7; color: #d97706; }
.status.confirmed { background: #d1fae5; color: #065f46; }
.status.completed { background: #dbeafe; color: #1e40af; }
.status.cancelled { background: #fee2e2; color: #dc2626; }

.no-appointments {
    text-align: center;
    padding: 40px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.success {
    background: #d1fae5;
    color: #065f46;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #a7f3d0;
}

.error {
    background: #fee2e2;
    color: #dc2626;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
}

@media (max-width: 768px) {
    .schedule-summary {
        grid-template-columns: 1fr;
    }

    .filter-row {
        grid-template-columns: 1fr;
    }

    .date-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<?php include("../includes/footer.php"); ?>
