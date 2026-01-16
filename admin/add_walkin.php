<?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/Appointments.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walkin'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $dentistId = $_POST['dentist_id'];
    $date = $_POST['date'] ?: date('Y-m-d'); // Use today if not provided
    $time = $_POST['time'] ?: date('H:i'); // Use current time if not provided
    $selectedTreatments = $_POST['treatments'] ?? [];

    if (empty($name) || empty($phone) || empty($dentistId) || empty($selectedTreatments)) {
        $error = "Patient name, phone, dentist, and treatments are required.";
    } else {
        // For walk-in, allow past dates/times since they're immediate
        $appointmentId = createAppointment(null, $dentistId, $date, $time, null, $name, $phone);
        if ($appointmentId) {
            // Add treatments to junction table
            foreach ($selectedTreatments as $treatmentId) {
                $stmt = $pdo->prepare("INSERT INTO AppointmentTreatments (appointmentId, treatmentId) VALUES (?, ?)");
                $stmt->execute([$appointmentId, $treatmentId]);
            }

            // Calculate total amount and create payment
            $totalStmt = $pdo->prepare("SELECT SUM(t.price) as total FROM Treatments t JOIN AppointmentTreatments at ON t.id = at.treatmentId WHERE at.appointmentId = ?");
            $totalStmt->execute([$appointmentId]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total > 0) {
                require_once "../models/payments.php";
                logPayment($appointmentId, $total, 'bank', 'pending');
            }

            $success = "Walk-in appointment added successfully!";
        } else {
            $error = "Failed to add appointment. Please try again.";
        }
    }
}

// Get available dentists
global $pdo;
$stmt = $pdo->query("SELECT id, name FROM Users WHERE role = 'dentist' ORDER BY name");
$dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get treatments for the dropdown
$stmt = $pdo->query("SELECT id, name FROM treatments ORDER BY name");
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1><img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Add" class="header-icon"> Add Walk-in Patient</h1>
            <p class="page-subtitle">Quickly register patients who need immediate dental care</p>
        </div>
        <div class="header-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo count($dentists); ?></span>
                <span class="stat-label">Available Dentists</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($treatments); ?></span>
                <span class="stat-label">Treatment Options</span>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <img src="<?php echo $base_url; ?>/assets/images/cross_icon.png" alt="Error" class="alert-icon">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <img src="<?php echo $base_url; ?>/assets/images/tick_icon.svg" alt="Success" class="alert-icon">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="walkin-section">
        <div class="section-header">
            <h2><img src="<?php echo $base_url; ?>/assets/images/patient_icon.svg" alt="Patient" class="section-icon"> Patient Information</h2>
            <p class="section-description">Enter the walk-in patient's basic details. For immediate appointments, date and time are optional.</p>
        </div>

        <form method="POST" id="walkinForm">
        <div class="form-sections">
            <!-- Patient Details Section -->
            <div class="form-section">
                <h3><img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Details" class="section-icon-small"> Patient Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" placeholder="Enter patient's full name" required>
                        <small class="field-help">Enter the patient's complete name as it appears on ID</small>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" placeholder="e.g., +1 (555) 123-4567" required>
                        <small class="field-help">Include country code for international numbers</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthday">Date of Birth</label>
                        <input type="date" name="birthday" id="birthday" max="<?php echo date('Y-m-d'); ?>">
                        <small class="field-help">Patient's date of birth for age calculation</small>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <small class="field-help">Patient's gender</small>
                    </div>
                </div>
            </div>

            <!-- Assignment Section -->
            <div class="form-section">
                <h3><img src="<?php echo $base_url; ?>/assets/images/doctor_icon.svg" alt="Assignment" class="section-icon-small"> Assignment</h3>
                <div class="form-group">
                    <label for="dentist_id">Select Dentist <span class="required">*</span></label>
                    <select name="dentist_id" id="dentist_id" required>
                        <option value="">Choose available dentist...</option>
                        <?php foreach ($dentists as $dentist): ?>
                            <option value="<?php echo $dentist['id']; ?>"><?php echo htmlspecialchars($dentist['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">The dentist who will perform the treatment</small>
                </div>
            </div>

            <!-- Treatments Section -->
            <div class="form-section">
                <h3><img src="<?php echo $base_url; ?>/assets/images/ToothFillings.svg" alt="Treatments" class="section-icon-small"> Treatments <span class="required">*</span></h3>
                <div class="treatments-grid">
                    <?php foreach ($treatments as $treatment): ?>
                        <label class="treatment-option">
                            <input type="checkbox" name="treatments[]" value="<?php echo $treatment['id']; ?>" class="treatment-checkbox">
                            <span class="treatment-label"><?php echo htmlspecialchars($treatment['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div id="selected-treatments" class="selected-treatments">
                    <strong>Selected Treatments:</strong> <span id="treatment-count">0</span>
                    <div id="treatment-list"></div>
                </div>
            </div>

            <!-- Scheduling Section -->
            <div class="form-section">
                <h3><img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Schedule" class="section-icon-small"> Scheduling</h3>
                <p class="section-note">For immediate walk-in appointments, leave date and time blank. The system will use current date/time.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Appointment Date</label>
                        <input type="date" name="date" id="date" min="<?php echo date('Y-m-d'); ?>">
                        <small class="field-help">Leave blank for today</small>
                    </div>

                    <div class="form-group">
                        <label for="time">Appointment Time</label>
                        <select name="time" id="time">
                            <option value="">Select time...</option>
                            <option value="09:00">9:00 AM</option>
                            <option value="09:30">9:30 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="10:30">10:30 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="11:30">11:30 AM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="13:30">1:30 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="14:30">2:30 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="15:30">3:30 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="16:30">4:30 PM</option>
                            <option value="17:00">5:00 PM</option>
                        </select>
                        <small class="field-help">Leave blank for immediate</small>
                    </div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="form-section summary-section">
                <h3><img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Summary" class="section-icon-small"> Appointment Summary</h3>
                <div class="summary-content">
                    <div class="summary-item">
                        <span class="summary-label">Patient:</span>
                        <span id="summary-name">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Phone:</span>
                        <span id="summary-phone">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Birthday:</span>
                        <span id="summary-birthday">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Gender:</span>
                        <span id="summary-gender">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Dentist:</span>
                        <span id="summary-dentist">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Treatments:</span>
                        <span id="summary-treatments">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Scheduled:</span>
                        <span id="summary-schedule">Immediate walk-in</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset Form</button>
                <button type="submit" name="add_walkin" class="btn btn-primary">Add Walk-in Patient</button>
            </div>
        </div>
        </form>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
}

.header-content h1 {
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-icon {
    width: 32px;
    height: 32px;
}

.page-subtitle {
    margin: 5px 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.header-stats {
    display: flex;
    gap: 20px;
}

.stat-box {
    text-align: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: bold;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Alerts */
.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-icon {
    width: 20px;
    height: 20px;
}

/* Main Section */
.walkin-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.section-header {
    background: #f8fafc;
    padding: 20px 30px;
    border-bottom: 1px solid #e5e7eb;
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1f2937;
}

.section-icon {
    width: 24px;
    height: 24px;
}

.section-description {
    margin: 8px 0 0 0;
    color: #6b7280;
}

/* Form Sections */
.form-sections {
    padding: 30px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f3f4f6;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h3 {
    margin: 0 0 15px 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #374151;
}

.section-icon-small {
    width: 20px;
    height: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #374151;
}

.required {
    color: #dc2626;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.field-help {
    display: block;
    margin-top: 4px;
    font-size: 0.85rem;
    color: #6b7280;
}

/* Treatments */
.treatments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 15px;
}

.treatment-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.treatment-option:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
}

.treatment-checkbox {
    width: 18px;
    height: 18px;
    accent-color: #3b82f6;
}

.treatment-label {
    font-weight: 500;
    color: #374151;
}

.selected-treatments {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

#treatment-list {
    margin-top: 8px;
    font-size: 0.9rem;
    color: #6b7280;
}

/* Scheduling */
.section-note {
    background: #fef3c7;
    color: #92400e;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

/* Summary */
.summary-section {
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}

.summary-content {
    display: grid;
    gap: 10px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-label {
    font-weight: 600;
    color: #374151;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .header-stats {
        justify-content: center;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .treatments-grid {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .walkin-section {
        margin: 0 -15px;
    }

    .form-sections {
        padding: 20px;
    }
}
</style>

<script>
// Form interaction script
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('walkinForm');
    const treatmentCheckboxes = document.querySelectorAll('.treatment-checkbox');
    const treatmentCount = document.getElementById('treatment-count');
    const treatmentList = document.getElementById('treatment-list');

    // Update summary in real-time
    function updateSummary() {
        const name = document.getElementById('name').value;
        const phone = document.getElementById('phone').value;
        const birthday = document.getElementById('birthday').value;
        const genderSelect = document.getElementById('gender');
        const gender = genderSelect.options[genderSelect.selectedIndex]?.text || '-';
        const dentistSelect = document.getElementById('dentist_id');
        const dentist = dentistSelect.options[dentistSelect.selectedIndex]?.text || '-';
        const date = document.getElementById('date').value;
        const time = document.getElementById('time').value;

        document.getElementById('summary-name').textContent = name || '-';
        document.getElementById('summary-phone').textContent = phone || '-';
        document.getElementById('summary-birthday').textContent = birthday || '-';
        document.getElementById('summary-gender').textContent = gender === 'Select Gender' ? '-' : gender;
        document.getElementById('summary-dentist').textContent = dentist;

        const selectedTreatments = Array.from(treatmentCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.nextElementSibling.textContent);

        document.getElementById('summary-treatments').textContent = selectedTreatments.length ?
            selectedTreatments.join(', ') : '-';

        const schedule = date && time ? `${date} at ${time}` :
                       date ? `${date} (time not set)` :
                       time ? `Today at ${time}` : 'Immediate walk-in';
        document.getElementById('summary-schedule').textContent = schedule;
    }

    // Treatment selection
    treatmentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selected = Array.from(treatmentCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.nextElementSibling.textContent);

            treatmentCount.textContent = selected.length;
            treatmentList.textContent = selected.join(', ') || 'None selected';

            updateSummary();
        });
    });

    // Form field changes
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);

    // Reset form
    window.resetForm = function() {
        form.reset();
        treatmentCount.textContent = '0';
        treatmentList.textContent = '';
        updateSummary();
    };

    // Initial summary update
    updateSummary();
});
</script>
