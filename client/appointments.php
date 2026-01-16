<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/users.php";
require_once "../models/Appointments.php";
require_once "../models/Notifications.php";
require_once "../models/BlockedDates.php";

$clientId = $_SESSION['user_id'];
$user = getUserById($clientId);

// Get treatments for the dropdown with prices
global $pdo;
$stmt = $pdo->query("SELECT id, name, price FROM treatments ORDER BY name");
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle reschedule form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    $appointmentId = $_POST['appointment_id'];
    $newDate = $_POST['date'];
    $newTime = $_POST['time'];
    $clientId = $_SESSION['user_id'];

    if (empty($newDate) || empty($newTime)) {
        $error = "Please select both date and time.";
    } else {
        // Validate date is not in the past
        if (strtotime($newDate) < strtotime('today')) {
            $error = "Cannot reschedule to a past date.";
        } else {
            // Check if the appointment belongs to the client and is reschedulable
            $stmt = $pdo->prepare("SELECT id, status FROM Appointments WHERE id = ? AND clientId = ? AND status IN ('pending', 'confirmed')");
            $stmt->execute([$appointmentId, $clientId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                $error = "Appointment not found or cannot be rescheduled.";
            } else {
                // Check if the new slot is available
                $conflictStmt = $pdo->prepare("SELECT COUNT(*) as conflicts FROM Appointments WHERE date = ? AND time = ? AND dentistId IS NOT NULL AND status IN ('pending', 'confirmed') AND id != ?");
                $conflictStmt->execute([$newDate, $newTime, $appointmentId]);
                $conflicts = $conflictStmt->fetch(PDO::FETCH_ASSOC)['conflicts'];

                if ($conflicts > 0) {
                    $error = "The selected time slot is not available.";
                } else {
                    // Update the appointment
                    $updateStmt = $pdo->prepare("UPDATE Appointments SET date = ?, time = ? WHERE id = ?");
                    $result = $updateStmt->execute([$newDate, $newTime, $appointmentId]);

                    if ($result) {
                        // Notify admin about reschedule
                        $adminStmt = $pdo->prepare("SELECT id FROM Users WHERE role = 'admin'");
                        $adminStmt->execute();
                        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($admins as $admin) {
                            createNotification($admin['id'], 'appointment_rescheduled', "Client rescheduled appointment to {$newDate} at {$newTime}");
                        }

                        $success = "Appointment rescheduled successfully!";
                    } else {
                        $error = "Failed to reschedule appointment.";
                    }
                }
            }
        }
    }
}

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $selectedTreatments = $_POST['treatments'] ?? [];

    if (empty($date) || empty($time) || empty($selectedTreatments)) {
        $error = "All fields are required.";
    } else {
        // Validate date is not in the past
        if (strtotime($date) < strtotime('today')) {
            $error = "Cannot book appointments in the past.";
        } else {
                // Prevent booking on blocked dates
                if (isDateBlocked($date)) {
                    $error = "The selected date is not available for booking.";
                } else {
                    // Create appointment
                    $appointmentId = createAppointment($clientId, null, $date, $time);
                }

            if ($appointmentId) {
                // Add treatments to junction table and calculate total
                require_once "../models/payments.php";
                $totalAmount = 0;
                foreach ($selectedTreatments as $treatmentId) {
                    $stmt = $pdo->prepare("INSERT INTO AppointmentTreatments (appointmentId, treatmentId) VALUES (?, ?)");
                    $stmt->execute([$appointmentId, $treatmentId]);
                    
                    // Get treatment price
                    $priceStmt = $pdo->prepare("SELECT price FROM Treatments WHERE id = ?");
                    $priceStmt->execute([$treatmentId]);
                    $treatment = $priceStmt->fetch(PDO::FETCH_ASSOC);
                    if ($treatment) {
                        $totalAmount += $treatment['price'];
                    }
                }
                
                // Auto-create payment for the appointment
                if ($totalAmount > 0) {
                    logPayment($appointmentId, $totalAmount, 'bank', 'pending');
                }
                
                $success = "Appointment booked successfully! A bill has been created. It will be assigned to a dentist soon.";
            } else {
                $error = "Failed to book appointment. Please try again.";
            }
        }
    }
}

// Get client's appointments with treatment details
$appointments = listClientAppointments($clientId);

// Get treatment details for each appointment
foreach ($appointments as &$appointment) {
    $stmt = $pdo->prepare("
        SELECT t.name, t.price
        FROM AppointmentTreatments at
        JOIN Treatments t ON at.treatmentId = t.id
        WHERE at.appointmentId = ?
    ");
    $stmt->execute([$appointment['id']]);
    $appointment['treatments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate min/max prices and total
    if (!empty($appointment['treatments'])) {
        $prices = array_column($appointment['treatments'], 'price');
        $appointment['min_price'] = min($prices);
        $appointment['max_price'] = max($prices);
        $appointment['total_price'] = array_sum($prices);
    }
}
unset($appointment);

// Load blocked dates for client-side validation
require_once "../models/BlockedDates.php";
$blockedDatesRaw = listBlockedDates();
$blockedDates = array_map(function($b){ return $b['date']; }, $blockedDatesRaw);

// Get all booked slots (pending/confirmed) for client-facing availability checks
global $pdo;
$bsStmt = $pdo->prepare("SELECT date, time, dentistId FROM Appointments WHERE date >= CURDATE() AND status IN ('pending','confirmed')");
$bsStmt->execute();
$bookedSlotsRaw = $bsStmt->fetchAll(PDO::FETCH_ASSOC);
$bookedSlots = array_map(function($r){ return ['date'=>$r['date'],'time'=>$r['time'],'dentistId'=>($r['dentistId'] ?? null)]; }, $bookedSlotsRaw);

// Get dentists list for selection
$dentistsStmt = $pdo->prepare("SELECT id, name FROM Users WHERE role = 'dentist'");
$dentistsStmt->execute();
$dentists = $dentistsStmt->fetchAll(PDO::FETCH_ASSOC);

// Check for missed appointment notifications
$missedNotification = null;
$notifications = getUserNotifications($clientId);
foreach ($notifications as $notification) {
    if ($notification['type'] === 'appointment_missed' && !$notification['isRead']) {
        $missedNotification = $notification;
        break;
    }
}
?>

<script>
// Set global variables for JavaScript
window.missedNotificationId = <?php echo $missedNotification ? $missedNotification['id'] : 'null'; ?>;
</script>
        <!-- FullCalendar -->
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<div class="container">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>My Appointments</h1>
            <p>Book and manage your dental appointments</p>
        </div>
        <div class="user-avatar">
            <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Profile" style="width: 60px; height: 60px;">
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <div class="alert-icon">⚠️</div>
            <div class="alert-content">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <div class="alert-icon">✅</div>
            <div class="alert-content">
                <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Missed Appointment Modal -->
    <?php if ($missedNotification): ?>
    <div id="missed-appointment-modal" class="modal" style="display: block;">
        <div class="modal-content">
            <h2>Missed Appointment</h2>
            <p><?php echo htmlspecialchars($missedNotification['message']); ?></p>
            <p>Would you like to reschedule this appointment?</p>
            <div class="modal-actions">
                <button id="reschedule-yes" class="btn-primary">Yes, reschedule</button>
                <button id="reschedule-no" class="btn-secondary">No, thank you</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Book New Appointment -->
    <div class="booking-section">
        <div class="booking-header">
            <div class="booking-title">
                <i class="fas fa-calendar-plus"></i>
                <h2>Book New Appointment</h2>
            </div>
            <p class="booking-subtitle">Schedule your dental care with ease</p>
        </div>

        <!-- Calendar and Filters -->
        <div class="calendar-section">
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3><i class="fas fa-calendar-alt"></i> Select Date</h3>
                    <div class="calendar-filters">
                        <div class="filter-group">
                            <label for="dentist_select">
                                <i class="fas fa-user-md"></i> Filter by Dentist:
                            </label>
                            <select id="dentist_select">
                                <option value="">Any Dentist</option>
                                <?php foreach($dentists as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="calendar" class="calendar-widget"></div>
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color booked"></div>
                        <span>Booked</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color blocked"></div>
                        <span>Unavailable</span>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="booking-form-container">
                <div class="form-header">
                    <h3><i class="fas fa-clipboard-list"></i> Appointment Details</h3>
                    <p>Fill in your appointment preferences</p>
                </div>

                <form method="POST" class="booking-form" id="appointment-form">
                    <div class="form-steps">
                        <!-- Step 1: Date & Time -->
                        <div class="form-step active" data-step="1">
                            <div class="step-header">
                                <div class="step-number">1</div>
                                <h4>Select Date & Time</h4>
                            </div>]

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date">
                                        <i class="fas fa-calendar-day"></i> Preferred Date
                                    </label>
                                    <input type="text" name="date" id="date" placeholder="Click on calendar or select date" required readonly>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Select an available date from the calendar
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dentist">
                                        <i class="fas fa-user-md"></i> Preferred Dentist (Optional)
                                    </label>
                                    <select name="dentist" id="dentist">
                                        <option value="">Any Available Dentist</option>
                                        <?php foreach($dentists as $d): ?>
                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Leave empty to be assigned automatically
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="time">
                                        <i class="fas fa-clock"></i> Preferred Time
                                    </label>
                                    <select name="time" id="time" required>
                                        <option value="">Select time slot</option>
                                        <option value="08:00">8:00 AM</option>
                                        <option value="08:30">8:30 AM</option>
                                        <option value="09:00">9:00 AM</option>
                                        <option value="09:30">9:30 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="10:30">10:30 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="11:30">11:30 AM</option>
                                        <option value="12:00">12:00 NN</option>
                                        <option value="13:00">1:00 PM</option>
                                        <option value="13:30">1:30 PM</option>
                                        <option value="14:00">2:00 PM</option>
                                        <option value="14:30">2:30 PM</option>
                                        <option value="15:00">3:00 PM</option>
                                        <option value="15:30">3:30 PM</option>
                                        <option value="16:00">4:00 PM</option>
                                        <option value="16:30">4:30 PM</option>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Available slots will be shown based on selected date
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <button type="button" class="btn-next" onclick="nextStep(2)">
                                    Next: Select Treatments
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Treatments -->
                        <div class="form-step" data-step="2">
                            <div class="step-header">
                                <div class="step-number">2</div>
                                <h4>Select Treatments</h4>
                            </div>

                            <div class="form-group treatments-group">
                                <label for="treatments">
                                    <i class="fas fa-tooth"></i> Dental Treatments
                                </label>
                                <div class="treatments-grid">
                                    <?php foreach ($treatments as $treatment): ?>
                                        <div class="treatment-option" data-id="<?php echo $treatment['id']; ?>" data-price="<?php echo $treatment['price']; ?>">
                                            <div class="treatment-checkbox">
                                                <input type="checkbox" name="treatments[]" value="<?php echo $treatment['id']; ?>" id="treatment-<?php echo $treatment['id']; ?>">
                                                <label for="treatment-<?php echo $treatment['id']; ?>"></label>
                                            </div>
                                            <div class="treatment-info">
                                                <h5><?php echo htmlspecialchars($treatment['name']); ?></h5>
                                                <div class="treatment-price">₱<?php echo number_format($treatment['price'], 2); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="field-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Select one or more treatments for your appointment
                                </div>
                            </div>

                            <!-- Cost Summary -->
                            <div class="cost-summary">
                                <div class="summary-header">
                                    <h4><i class="fas fa-calculator"></i> Cost Summary</h4>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-row">
                                        <span class="summary-label">Selected Treatments:</span>
                                        <span class="summary-value" id="selected-count">0</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span class="summary-label">Total Estimated Cost:</span>
                                        <span class="summary-value total-amount">₱<span id="total-cost">0.00</span></span>
                                    </div>
                                    <div class="summary-note">
                                        <i class="fas fa-info-circle"></i>
                                        Final cost may vary based on dentist assessment
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <button type="button" class="btn-prev" onclick="prevStep(1)">
                                    <i class="fas fa-arrow-left"></i>
                                    Back
                                </button>
                                <button type="submit" name="book_appointment" class="btn-book">
                                    <i class="fas fa-calendar-check"></i>
                                    Book Appointment
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dentist Availability Calendar -->
    <div class="availability-section">
        <div class="section-header">
            <h2><i class="fas fa-calendar-check"></i> Dentist Availability</h2>
            <p>Check which dentists are available on specific dates and times</p>
        </div>

        <div class="availability-controls">
            <div class="control-group">
                <label for="availability-date">
                    <i class="fas fa-calendar-day"></i> Select Date:
                </label>
                <input type="text" id="availability-date" placeholder="Choose a date" readonly>
            </div>
            <div class="control-group">
                <label for="availability-dentist">
                    <i class="fas fa-user-md"></i> Filter by Dentist:
                </label>
                <select id="availability-dentist">
                    <option value="">All Dentists</option>
                    <?php foreach($dentists as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="availability-calendar-container">
            <div id="availability-calendar"></div>
        </div>

        <div class="availability-details">
            <div class="availability-legend">
                <h4>Legend:</h4>
                <div class="legend-items">
                    <div class="legend-item">
                        <div class="legend-color available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color booked"></div>
                        <span>Booked</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color blocked"></div>
                        <span>Unavailable</span>
                    </div>
                </div>
            </div>

            <div class="selected-date-info" id="selected-date-info" style="display: none;">
                <h4><i class="fas fa-info-circle"></i> Availability for <span id="selected-date-text"></span></h4>
                <div id="time-slots" class="time-slots">
                    <!-- Time slots will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="reschedule-modal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <h2>Reschedule Appointment</h2>
            <form method="POST" id="reschedule-form">
                <input type="hidden" name="appointment_id" id="reschedule-appointment-id">
                <div class="form-group">
                    <label for="reschedule-date">New Date:</label>
                    <input type="text" name="date" id="reschedule-date" required readonly>
                </div>
                <div class="form-group">
                    <label for="reschedule-time">New Time:</label>
                    <select name="time" id="reschedule-time" required>
                        <option value="">Select time slot</option>
                        <option value="08:00">8:00 AM</option>
                        <option value="08:30">8:30 AM</option>
                        <option value="09:00">9:00 AM</option>
                        <option value="09:30">9:30 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="10:30">10:30 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="11:30">11:30 AM</option>
                        <option value="12:00">12:00 NN</option>
                        <option value="13:00">1:00 PM</option>
                        <option value="13:30">1:30 PM</option>
                        <option value="14:00">2:00 PM</option>
                        <option value="14:30">2:30 PM</option>
                        <option value="15:00">3:00 PM</option>
                        <option value="15:30">3:30 PM</option>
                        <option value="16:00">4:00 PM</option>
                        <option value="16:30">4:30 PM</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeRescheduleModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" name="reschedule_appointment" class="btn-primary">Reschedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Appointments -->
    <!-- Existing Appointments -->
    <div class="appointments-section">
        <div class="section-header">
            <h2>My Appointments</h2>
            <div class="appointment-tabs">
                <button class="tab-btn active" onclick="showTab('upcoming')">Upcoming</button>
                <button class="tab-btn" onclick="showTab('past')">Past</button>
                <button class="tab-btn" onclick="showTab('all')">All</button>
            </div>
        </div>

        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h3>No appointments yet</h3>
                <p>Book your first dental appointment above to get started!</p>
            </div>
        <?php else: ?>
            <!-- Upcoming Appointments -->
            <div id="upcoming-appointments" class="appointment-tab-content active">
                <h3>Upcoming Appointments</h3>
                <?php
                $upcomingAppointments = array_filter($appointments, function($apt) {
                    return strtotime($apt['date']) >= strtotime('today');
                });
                ?>
                <?php if (empty($upcomingAppointments)): ?>
                    <p class="no-appointments">No upcoming appointments.</p>
                <?php else: ?>
                    <div class="appointments-grid">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <div class="appointment-date">
                                        <strong><?php echo date('M j, Y', strtotime($appointment['date'])); ?></strong>
                                        <span><?php echo date('g:i A', strtotime($appointment['time'])); ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($appointment['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                    </span>
                                </div>
                                <div class="appointment-details">
                                    <p><strong>Dentist:</strong> <?php echo htmlspecialchars($appointment['dentistName'] ?? 'Not assigned'); ?></p>
                                    <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                    <?php if (!empty($appointment['treatments'])): ?>
                                        <div class="treatment-summary">
                                            <p><strong>Treatments:</strong>
                                                <?php
                                                $treatmentNames = array_map(function($t) { return $t['name']; }, $appointment['treatments']);
                                                echo htmlspecialchars(implode(', ', $treatmentNames));
                                                ?>
                                            </p>
                                            <div class="price-info">
                                                <span class="price-range">
                                                    <i class="fas fa-tag"></i>
                                                    ₱<?php echo number_format($appointment['min_price'], 2); ?> -
                                                    ₱<?php echo number_format($appointment['max_price'], 2); ?>
                                                </span>
                                                <span class="total-price">
                                                    <i class="fas fa-calculator"></i>
                                                    Total: ₱<?php echo number_format($appointment['total_price'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="appointment-actions">
                                    <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                        <button class="btn-reschedule" onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)">Reschedule</button>
                                        <form method="POST" action="cancel_appointment.php" style="display: inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Appointments -->
            <div id="past-appointments" class="appointment-tab-content">
                <h3>Past Appointments</h3>
                <?php
                $pastAppointments = array_filter($appointments, function($apt) {
                    return strtotime($apt['date']) < strtotime('today');
                });
                ?>
                <?php if (empty($pastAppointments)): ?>
                    <p class="no-appointments">No past appointments.</p>
                <?php else: ?>
                    <div class="appointments-grid">
                        <?php foreach ($pastAppointments as $appointment): ?>
                            <div class="appointment-card past">
                                <div class="appointment-header">
                                    <div class="appointment-date">
                                        <strong><?php echo date('M j, Y', strtotime($appointment['date'])); ?></strong>
                                        <span><?php echo date('g:i A', strtotime($appointment['time'])); ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($appointment['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                    </span>
                                </div>
                                <div class="appointment-details">
                                    <p><strong>Dentist:</strong> <?php echo htmlspecialchars($appointment['dentistName'] ?? 'Not assigned'); ?></p>
                                    <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                </div>
                                <div class="appointment-actions">
                                    <button class="btn-view-details" onclick="viewAppointmentDetails(<?php echo $appointment['id']; ?>)">View Details</button>
                                    <button class="btn-book-again" onclick="bookAgain()">Book Again</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Appointments Table View -->
            <div id="all-appointments" class="appointment-tab-content">
                <h3>All Appointments</h3>
                <div class="appointments-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Dentist</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appointment['date']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                                    <td>
                                        <span class="status <?php echo htmlspecialchars($appointment['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['dentistName'] ?? 'Not assigned'); ?></td>
                                    <td>
                                        <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                            <form method="POST" action="cancel_appointment.php" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick book modal -->
<div id="quick-book-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:600px;">
        <h3>Select a time to book</h3>
        <div id="quick-times" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;">
        </div>
        <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end;">
            <button id="quick-close" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<style>
/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
    color: white;
    padding: 3.125rem 1.25rem;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    border-radius: 0 0 1.25rem 1.25rem;
    box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    gap: 1.25rem;
}

.welcome-section {
    text-align: center;
    width: 100%;
}

.welcome-section h1 {
    margin: 0 0 0.625rem 0;
    font-size: 2.5rem;
    font-weight: 700;
    text-align: center;
}

.welcome-section p {
    margin: 0;
    opacity: 0.95;
    font-size: 1.1rem;
    text-align: center;
}

.user-avatar {
    text-align: center;
}

.user-avatar img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

/* Alert Messages */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #f0fdf4 100%);
    color: #065f46;
    border-color: #a7f3d0;
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
    color: #dc2626;
    border-color: #fecaca;
}

.alert-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.alert-content strong {
    font-weight: 600;
}

/* Booking Section */
.booking-section {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-radius: 20px;
    margin-bottom: 40px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.booking-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    text-align: center;
}

.booking-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 8px;
}

.booking-title i {
    font-size: 1.5rem;
    opacity: 0.9;
}

.booking-title h2 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.booking-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

/* Calendar Section */
.calendar-section {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
    padding: 30px;
}

.calendar-container {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.calendar-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.calendar-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.calendar-header h3 i {
    color: #667eea;
}

.calendar-filters {
    display: flex;
    gap: 16px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.filter-group select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    background: white;
}

.calendar-widget {
    height: 400px;
    padding: 20px;
}

/* Calendar Legend */
.calendar-legend {
    display: flex;
    gap: 20px;
    padding: 16px 20px;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #6b7280;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.legend-color.available {
    background: #10b981;
}

.legend-color.booked {
    background: #ef4444;
}

.legend-color.blocked {
    background: #f59e0b;
}

/* Booking Form Container */
.booking-form-container {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.form-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    text-align: center;
}

.form-header h3 {
    margin: 0 0 4px 0;
    color: #1f2937;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.form-header h3 i {
    color: #667eea;
}

.form-header p {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
}

/* Multi-Step Form */
.form-steps {
    position: relative;
}

.form-step {
    display: none;
    padding: 30px;
}

.form-step.active {
    display: block;
}

.step-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.step-number {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.step-header h4 {
    margin: 0;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 600;
}

.form-row {
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-group label i {
    color: #667eea;
    width: 16px;
}

.form-group input,
.form-group select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.field-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 0.85rem;
    color: #6b7280;
}

.field-hint i {
    color: #667eea;
    font-size: 0.8rem;
}

/* Treatments Grid */
.treatments-group {
    margin-bottom: 24px;
}

.treatments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.treatment-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.treatment-option:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.treatment-option.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
}

.treatment-checkbox {
    position: relative;
}

.treatment-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.treatment-checkbox label {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.treatment-checkbox input[type="checkbox"]:checked + label {
    background: #667eea;
    border-color: #667eea;
}

.treatment-checkbox input[type="checkbox"]:checked + label::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.treatment-info {
    flex: 1;
}

.treatment-info h5 {
    margin: 0 0 4px 0;
    color: #1f2937;
    font-size: 1rem;
    font-weight: 600;
}

.treatment-price {
    color: #059669;
    font-weight: 700;
    font-size: 0.9rem;
}

/* Cost Summary */
.cost-summary {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.summary-header {
    margin-bottom: 16px;
}

.summary-header h4 {
    margin: 0;
    color: #166534;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-header h4 i {
    color: #16a34a;
}

.summary-content {
    display: grid;
    gap: 12px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-row.total-row {
    padding-top: 12px;
    border-top: 1px solid #bbf7d0;
    font-size: 1.1rem;
    font-weight: 700;
}

.summary-label {
    color: #166534;
}

.summary-value {
    color: #166534;
    font-weight: 600;
}

.summary-value.total-amount {
    color: #059669;
    font-size: 1.2rem;
}

.summary-note {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #bbf7d0;
    font-size: 0.85rem;
    color: #166534;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.summary-note i {
    margin-top: 1px;
    flex-shrink: 0;
}

/* Step Actions */
.step-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-next,
.btn-prev,
.btn-book {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-next {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-next:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-prev {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-prev:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

.btn-book {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-book:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-cancel {
    background: #dc2626;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.btn-cancel:hover {
    background: #b91c1c;
}

.appointments-table {
    overflow-x: auto;
    margin-top: 20px;
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

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    text-align: center;
}

.modal-content h2 {
    margin-top: 0;
    color: #1f2937;
}

.modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 15px;
    justify-content: center;
}

/* Appointments Section */
.appointments-section {
    background: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.section-header h2 {
    margin: 0;
    color: #1f2937;
    font-size: 1.5rem;
    font-weight: 700;
}

.appointment-tabs {
    display: flex;
    gap: 8px;
    background: #f3f4f6;
    padding: 4px;
    border-radius: 8px;
}

.tab-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    color: #6b7280;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
}

.tab-btn.active {
    background: white;
    color: #1f2937;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-btn:hover {
    color: #1f2937;
}

.appointment-tab-content {
    display: none;
}

.appointment-tab-content.active {
    display: block;
}

.appointment-tab-content h3 {
    margin: 0 0 20px 0;
    color: #374151;
    font-size: 1.25rem;
    font-weight: 600;
}

.appointments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.appointment-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.2s;
}

.appointment-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.appointment-card.past {
    opacity: 0.8;
    border-color: #d1d5db;
}

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.appointment-date strong {
    display: block;
    font-size: 1.125rem;
    color: #1f2937;
    font-weight: 600;
}

.appointment-date span {
    color: #6b7280;
    font-size: 0.875rem;
}

.appointment-details p {
    margin: 4px 0;
    color: #374151;
    font-size: 0.875rem;
}

.appointment-details strong {
    color: #1f2937;
}

.treatment-summary {
    margin-top: 12px;
    padding: 12px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.treatment-summary p {
    margin: 0 0 8px 0;
    font-size: 0.85rem;
    color: #374151;
}

.price-info {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.price-range,
.total-price {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #059669;
}

.price-range i,
.total-price i {
    color: #10b981;
    font-size: 0.75rem;
}

.appointment-actions {
    margin-top: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-reschedule {
    background: #f59e0b;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn-reschedule:hover {
    background: #d97706;
}

.btn-view-details {
    background: #6b7280;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn-view-details:hover {
    background: #4b5563;
}

.btn-book-again {
    background: #10b981;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn-book-again:hover {
    background: #059669;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
}

.empty-state h3 {
    margin: 0 0 8px 0;
    color: #374151;
    font-size: 1.25rem;
}

.empty-state p {
    margin: 0;
    font-size: 0.875rem;
}

.no-appointments {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
    font-style: italic;
}

/* Dentist Availability Section */
.availability-section {
    background: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 40px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.availability-section .section-header {
    margin-bottom: 30px;
}

.availability-section .section-header h2 {
    margin: 0 0 8px 0;
    color: #1f2937;
    font-size: 1.75rem;
    font-weight: 700;
}

.availability-section .section-header p {
    margin: 0;
    color: #6b7280;
    font-size: 1rem;
}

.availability-controls {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 200px;
}

.control-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}

.control-group label i {
    color: #667eea;
}

.control-group input,
.control-group select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    background: white;
    transition: border-color 0.2s;
}

.control-group input:focus,
.control-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.availability-calendar-container {
    margin-bottom: 30px;
}

.availability-details {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.availability-legend {
    min-width: 200px;
}

.availability-legend h4 {
    margin: 0 0 16px 0;
    color: #1f2937;
    font-size: 1.1rem;
    font-weight: 600;
}

.legend-items {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.selected-date-info {
    flex: 1;
    min-width: 300px;
}

.selected-date-info h4 {
    margin: 0 0 20px 0;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 600;
}

.time-slots {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.dentist-availability h5 {
    margin: 0 0 12px 0;
    color: #374151;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dentist-availability h5 i {
    color: #10b981;
}

.time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}

.time-slot {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.time-slot:hover {
    border-color: #667eea;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
}

.time-slot.available {
    border-color: #10b981;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
}

.time-slot.available:hover {
    border-color: #059669;
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
}

.time-slot i {
    color: #6b7280;
    font-size: 0.8rem;
}

.time-slot.available i {
    color: #10b981;
}

.loading,
.no-slots {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
    font-style: italic;
}

.no-slots {
    color: #dc2626;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border-radius: 8px;
    border: 1px solid #fecaca;
}

/* Responsive Design */
@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        align-items: stretch;
    }

    .appointment-tabs {
        justify-content: center;
    }

    .appointments-grid {
        grid-template-columns: 1fr;
    }

    .appointment-header {
        flex-direction: column;
        gap: 8px;
    }

    .appointment-actions {
        justify-content: center;
    }

    .dashboard-header {
        padding: 2rem 1rem;
    }

    .welcome-section h1 {
        font-size: 2rem;
    }

    .booking-section {
        padding: 20px;
    }

    .appointments-section {
        padding: 20px;
    }

    .availability-section {
        padding: 20px;
    }

    .availability-controls {
        flex-direction: column;
    }

    .control-group {
        min-width: auto;
    }

    .availability-details {
        flex-direction: column;
    }

    .time-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
}
</style>

<script>
// Blocked dates from server
window.blockedDates = <?php echo json_encode($blockedDates); ?>;

function isBlocked(dateStr) {
    return window.blockedDates.indexOf(dateStr) !== -1;
}

// initialize flatpickr to visually disable blocked dates and prevent selection
</script>

<!-- flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const dateInput = document.getElementById('date');
flatpickr(dateInput, {
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'F j, Y',
    minDate: new Date().toISOString().split('T')[0],
    disable: window.blockedDates || [],
});

// Prevent form submission if date is blocked (defensive)
const bookingForm = document.querySelector('.booking-form');
if (bookingForm) {
    bookingForm.addEventListener('submit', function(e) {
        const val = dateInput.value;
        if (val && isBlocked(val)) {
            e.preventDefault();
            alert('The selected date is not available for booking.');
        }
    });
}
</script>

<script>
// Prepare booking/calendar data from server
const bookedSlots = <?php echo json_encode($bookedSlots); ?>;
const blockedDatesArr = <?php echo json_encode($blockedDates); ?>;
const dentistsArr = <?php echo json_encode($dentists); ?>;

// Default available times
const defaultTimes = ['09:00','10:00','11:00','13:00','14:00','15:00','16:00'];

function isBlocked(dateStr) {
    return blockedDatesArr.indexOf(dateStr) !== -1;
}

function buildEvents(selectedDentist = '') {
    const events = [];
    bookedSlots.forEach(bs => {
        // if a dentist filter is set, only include that dentist's bookings
        if (selectedDentist === '' || String(bs.dentistId) === String(selectedDentist)) {
            if (bs.date && bs.time) {
                events.push({ title: 'Booked', start: bs.date + 'T' + bs.time, color: '#ef4444' });
            }
        }
    });
    blockedDatesArr.forEach(d => {
        events.push({ start: d, display: 'background', color: '#ffedd5' });
    });
    return events;
}

// Manage FullCalendar instance
let fcCalendar = null;
function initCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;
    fcCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        events: buildEvents(),
        dateClick: function(info) {
            const dateStr = info.dateStr;
            if (isBlocked(dateStr)) { alert('This date is blocked for bookings.'); return; }
            if (dateInput._flatpickr) dateInput._flatpickr.setDate(dateStr);
            updateTimeOptions(dateStr);
            dateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showQuickTimes(dateStr);
        }
    });
    fcCalendar.render();
}

function rebuildCalendarEvents() {
    if (!fcCalendar) return;
    const dentistFilter = document.getElementById('dentist_select');
    const selectedDentist = dentistFilter ? dentistFilter.value : '';
    fcCalendar.removeAllEventSources();
    fcCalendar.addEventSource(buildEvents(selectedDentist));
}

// Quick-times modal
function showQuickTimes(dateStr) {
    const modal = document.getElementById('quick-book-modal');
    const container = document.getElementById('quick-times');
    container.innerHTML = '';
    const dentistFilter = document.getElementById('dentist_select');
    const selectedDentist = dentistFilter ? dentistFilter.value : '';
    const booked = bookedSlots.filter(b => b.date === dateStr && (selectedDentist === '' || String(b.dentistId) === String(selectedDentist))).map(b => b.time);
    const available = defaultTimes.filter(t => booked.indexOf(t) === -1 && blockedDatesArr.indexOf(dateStr) === -1);
    if (available.length === 0) {
        const p = document.createElement('div'); p.textContent = 'No available slots on this date.'; container.appendChild(p);
    } else {
        available.forEach(t => {
            const btn = document.createElement('button');
            btn.className = 'btn-primary';
            btn.style.marginRight = '6px';
            btn.textContent = (function(hm){ const [hh,mm]=hm.split(':'); const hour=parseInt(hh,10); const ampm = hour>=12?'PM':'AM'; const hr12 = ((hour+11)%12)+1; return hr12+':'+(mm==='00'?'00':mm)+' '+ampm; })(t);
            btn.addEventListener('click', function(){
                if (dateInput._flatpickr) dateInput._flatpickr.setDate(dateStr);
                updateTimeOptions(dateStr);
                setTimeout(()=>{ const ts = document.getElementById('time'); if(ts){ ts.value = t; }} , 150);
                if (selectedDentist) { const bf = document.getElementById('dentist'); if(bf) bf.value = selectedDentist; }
                modal.style.display = 'none';
                dateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            container.appendChild(btn);
        });
    }
    const close = document.getElementById('quick-close'); if(close){ close.onclick = function(){ modal.style.display='none'; }}
    modal.style.display = 'flex';
}

// Wire dentist filter
const dentistFilterEl = document.getElementById('dentist_select');
if (dentistFilterEl) {
    dentistFilterEl.addEventListener('change', function(){
        // copy selection to booking form dentist select
        const bf = document.getElementById('dentist'); if (bf) bf.value = this.value;
        rebuildCalendarEvents();
    });
}

// init calendar when DOM ready
document.addEventListener('DOMContentLoaded', function(){
    initCalendar();
    initCostCalculator();
    initTreatmentSelection();
    initAvailabilityCalendar();
});

// Multi-Step Form Navigation
function nextStep(stepNumber) {
    const currentStep = document.querySelector('.form-step.active');
    const nextStep = document.querySelector(`.form-step[data-step="${stepNumber}"]`);

    if (currentStep && nextStep) {
        // Validate current step
        if (stepNumber === 2 && !validateStep1()) {
            return;
        }

        currentStep.classList.remove('active');
        nextStep.classList.add('active');
    }
}

function prevStep(stepNumber) {
    const currentStep = document.querySelector('.form-step.active');
    const prevStep = document.querySelector(`.form-step[data-step="${stepNumber}"]`);

    if (currentStep && prevStep) {
        currentStep.classList.remove('active');
        prevStep.classList.add('active');
    }
}

function validateStep1() {
    const dateInput = document.getElementById('date');
    const dentistSelect = document.getElementById('dentist');
    const timeSelect = document.getElementById('time');

    if (!dateInput.value) {
        alert('Please select a date.');
        dateInput.focus();
        return false;
    }

    if (!timeSelect.value) {
        alert('Please select a time.');
        timeSelect.focus();
        return false;
    }

    return true;
}

// Treatment Selection and Cost Calculator
function initTreatmentSelection() {
    const treatmentOptions = document.querySelectorAll('.treatment-option');
    const selectedCountSpan = document.getElementById('selected-count');
    const totalCostSpan = document.getElementById('total-cost');

    treatmentOptions.forEach(option => {
        option.addEventListener('click', function() {
            const checkbox = this.querySelector('input[type="checkbox"]');
            const isSelected = this.classList.contains('selected');

            if (isSelected) {
                this.classList.remove('selected');
                checkbox.checked = false;
            } else {
                this.classList.add('selected');
                checkbox.checked = true;
            }

            updateCostSummary();
        });
    });

    function updateCostSummary() {
        const selectedTreatments = document.querySelectorAll('.treatment-option.selected');
        let totalCost = 0;

        selectedTreatments.forEach(treatment => {
            const price = parseFloat(treatment.getAttribute('data-price')) || 0;
            totalCost += price;
        });

        selectedCountSpan.textContent = selectedTreatments.length;
        totalCostSpan.textContent = totalCost.toFixed(2);
    }
}

// Cost Calculator (legacy - keeping for compatibility)
function initCostCalculator() {
    const treatmentsSelect = document.getElementById('treatments');
    const totalCostSpan = document.getElementById('total-cost');
    const selectedTreatmentsDiv = document.getElementById('selected-treatments');

    if (!treatmentsSelect) return;

    treatmentsSelect.addEventListener('change', function() {
        updateCost();
    });

    function updateCost() {
        const selectedOptions = Array.from(treatmentsSelect.selectedOptions);
        let totalCost = 0;
        const selectedNames = [];

        selectedOptions.forEach(option => {
            const price = parseFloat(option.getAttribute('data-price')) || 0;
            totalCost += price;
            selectedNames.push(option.text.split(' - ')[0]); // Get treatment name without price
        });

        totalCostSpan.textContent = totalCost.toFixed(2);
        selectedTreatmentsDiv.textContent = selectedNames.length > 0 ?
            'Selected: ' + selectedNames.join(', ') : '';
    }
}

// Tab switching functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.appointment-tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });

    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });

    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-appointments');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Add active class to clicked button
    const clickedButton = Array.from(tabButtons).find(btn => btn.textContent.toLowerCase() === tabName);
    if (clickedButton) {
        clickedButton.classList.add('active');
    }
}

// Dentist Availability Calendar
let availabilityCalendar = null;

function initAvailabilityCalendar() {
    // Initialize date picker for availability
    const availabilityDateInput = document.getElementById('availability-date');
    if (availabilityDateInput) {
        flatpickr(availabilityDateInput, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            minDate: new Date().toISOString().split('T')[0],
            disable: window.blockedDates || [],
            onChange: function(selectedDates, dateStr) {
                if (dateStr) {
                    showAvailabilityForDate(dateStr);
                }
            }
        });
    }

    // Initialize availability calendar
    const availabilityCalendarEl = document.getElementById('availability-calendar');
    if (availabilityCalendarEl) {
        availabilityCalendar = new FullCalendar.Calendar(availabilityCalendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            events: [],
            dateClick: function(info) {
                const dateStr = info.dateStr;
                if (isBlocked(dateStr)) {
                    alert('This date is blocked for bookings.');
                    return;
                }
                showAvailabilityForDate(dateStr);
            }
        });
        availabilityCalendar.render();
    }

    // Wire dentist filter for availability
    const availabilityDentistFilter = document.getElementById('availability-dentist');
    if (availabilityDentistFilter) {
        availabilityDentistFilter.addEventListener('change', function() {
            const selectedDate = document.getElementById('availability-date').value;
            if (selectedDate) {
                showAvailabilityForDate(selectedDate);
            }
        });
    }
}

function showAvailabilityForDate(dateStr) {
    const selectedDentist = document.getElementById('availability-dentist').value;
    const selectedDateText = document.getElementById('selected-date-text');
    const selectedDateInfo = document.getElementById('selected-date-info');
    const timeSlots = document.getElementById('time-slots');

    // Update date display
    const date = new Date(dateStr);
    selectedDateText.textContent = date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Show the info section
    selectedDateInfo.style.display = 'block';

    // Clear previous time slots
    timeSlots.innerHTML = '<div class="loading">Loading availability...</div>';

    // Get availability data
    const availableSlots = getAvailableSlotsForDate(dateStr, selectedDentist);

    // Display time slots
    displayTimeSlots(availableSlots, dateStr, selectedDentist);
}

function getAvailableSlotsForDate(dateStr, selectedDentist) {
    const availableSlots = [];

    // Get all dentists or selected dentist
    const dentistsToCheck = selectedDentist ? [selectedDentist] : dentistsArr.map(d => d.id);

    dentistsToCheck.forEach(dentistId => {
        const dentist = dentistsArr.find(d => d.id == dentistId);
        if (!dentist) return;

        // Check each time slot
        defaultTimes.forEach(time => {
            const isBooked = bookedSlots.some(bs =>
                bs.date === dateStr &&
                bs.time === time &&
                bs.dentistId == dentistId
            );

            const isBlocked = blockedDatesArr.includes(dateStr);

            if (!isBooked && !isBlocked) {
                availableSlots.push({
                    time: time,
                    dentist: dentist,
                    available: true
                });
            }
        });
    });

    return availableSlots;
}

function displayTimeSlots(slots, dateStr, selectedDentist) {
    const timeSlots = document.getElementById('time-slots');
    timeSlots.innerHTML = '';

    if (slots.length === 0) {
        timeSlots.innerHTML = '<div class="no-slots">No available time slots for this date.</div>';
        return;
    }

    // Group by dentist if showing all dentists
    if (!selectedDentist) {
        const groupedByDentist = {};
        slots.forEach(slot => {
            if (!groupedByDentist[slot.dentist.id]) {
                groupedByDentist[slot.dentist.id] = {
                    dentist: slot.dentist,
                    slots: []
                };
            }
            groupedByDentist[slot.dentist.id].slots.push(slot);
        });

        Object.values(groupedByDentist).forEach(group => {
            const dentistSection = document.createElement('div');
            dentistSection.className = 'dentist-availability';
            dentistSection.innerHTML = `
                <h5><i class="fas fa-user-md"></i> ${group.dentist.name}</h5>
                <div class="time-grid">
                    ${group.slots.map(slot => `
                        <div class="time-slot available" onclick="selectTimeSlot('${dateStr}', '${slot.time}', '${group.dentist.id}')">
                            <i class="fas fa-clock"></i>
                            ${formatTime(slot.time)}
                        </div>
                    `).join('')}
                </div>
            `;
            timeSlots.appendChild(dentistSection);
        });
    } else {
        // Show slots for selected dentist
        const timeGrid = document.createElement('div');
        timeGrid.className = 'time-grid';

        slots.forEach(slot => {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot available';
            timeSlot.onclick = () => selectTimeSlot(dateStr, slot.time, selectedDentist);
            timeSlot.innerHTML = `
                <i class="fas fa-clock"></i>
                ${formatTime(slot.time)}
            `;
            timeGrid.appendChild(timeSlot);
        });

        timeSlots.appendChild(timeGrid);
    }
}

function selectTimeSlot(dateStr, time, dentistId) {
    // Scroll to booking section and pre-fill the form
    const bookingSection = document.querySelector('.booking-section');
    if (bookingSection) {
        bookingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Pre-fill the booking form
        setTimeout(() => {
            const dateInput = document.getElementById('date');
            const dentistSelect = document.getElementById('dentist');
            const timeSelect = document.getElementById('time');

            if (dateInput._flatpickr) {
                dateInput._flatpickr.setDate(dateStr);
            }

            if (dentistSelect) {
                dentistSelect.value = dentistId;
            }

            if (timeSelect) {
                timeSelect.value = time;
            }

            // Move to step 2
            nextStep(2);
        }, 500);
    }
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = ((hour + 11) % 12) + 1;
    return `${hour12}:${minutes === '00' ? '00' : minutes} ${ampm}`;
}

// Reschedule appointment functionality
function rescheduleAppointment(appointmentId) {
    // Set the appointment ID in the hidden input
    const appointmentIdInput = document.getElementById('reschedule-appointment-id');
    if (appointmentIdInput) {
        appointmentIdInput.value = appointmentId;
    }

    // Initialize the date picker for reschedule modal
    const rescheduleDateInput = document.getElementById('reschedule-date');
    if (rescheduleDateInput && !rescheduleDateInput._flatpickr) {
        flatpickr(rescheduleDateInput, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            minDate: new Date().toISOString().split('T')[0],
            disable: window.blockedDates || [],
        });
    }

    // Show the modal
    const modal = document.getElementById('reschedule-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeRescheduleModal() {
    const modal = document.getElementById('reschedule-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function viewAppointmentDetails(appointmentId) {
    alert('View details functionality would be implemented here. Appointment ID: ' + appointmentId);
}

function bookAgain() {
    // Scroll to booking section
    const bookingSection = document.querySelector('.booking-section');
    if (bookingSection) {
        bookingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>

<script src="<?php echo $base_url; ?>/assets/client-appointments.js"></script>

<?php include("../includes/footer.php"); ?>
