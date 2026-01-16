<?php
include("../includes/header.php");
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') { header('Location: /public/login.php'); exit; }
require_once "../models/Appointments.php";

$dentistId = $_SESSION['user_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = $_POST['appointment_id'];
    $newStatus = $_POST['status'];

    if (updateAppointmentStatus($appointmentId, $newStatus, $dentistId)) {
        $success = "Appointment status updated successfully.";
    } else {
        $error = "Failed to update appointment status.";
    }
}

// Get all appointments for this dentist
global $pdo;
$stmt = $pdo->prepare("
    SELECT a.*, u.name as clientName, u.email as clientEmail, u.phone as clientPhone
    FROM Appointments a
    JOIN Users u ON a.clientId = u.id
    WHERE a.dentistId = ?
    ORDER BY a.date DESC, a.time DESC
");
$stmt->execute([$dentistId]);
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
?>

<div class="container">
    <h1>My Schedule</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Today's Summary -->
    <?php
    $today = date('Y-m-d');
    $todayAppointments = $groupedAppointments[$today] ?? [];
    $upcomingCount = 0;
    foreach ($appointments as $appt) {
        if ($appt['date'] >= $today && $appt['status'] === 'confirmed') {
            $upcomingCount++;
        }
    }
    ?>

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
    </div>

    <!-- Appointments by Date -->
    <div class="appointments-section">
        <h2>All Appointments</h2>
        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <p>You have no appointments scheduled yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedAppointments as $date => $dayAppointments): ?>
                <div class="date-group">
                    <h3 class="date-header">
                        <?php echo date('l, F j, Y', strtotime($date)); ?>
                        <?php if ($date === $today): ?>
                            <span class="today-badge">Today</span>
                        <?php endif; ?>
                    </h3>

                    <div class="appointments-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dayAppointments as $appt): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($appt['time'])); ?></td>
                                        <td><?php echo htmlspecialchars($appt['clientName']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($appt['clientEmail']); ?><br>
                                            <small><?php echo htmlspecialchars($appt['clientPhone']); ?></small>
                                        </td>
                                        <td>
                                            <span class="status <?php echo $appt['status']; ?>">
                                                <?php echo ucfirst($appt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 4px; border-radius: 4px; border: 1px solid #d1d5db;">
                                                    <option value="pending" <?php echo $appt['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $appt['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="completed" <?php echo $appt['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $appt['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
</style>


