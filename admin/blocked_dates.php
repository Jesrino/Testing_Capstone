<?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/BlockedDates.php";

$userId = $_SESSION['user_id'];

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_block'])) {
    $date = $_POST['date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    if (!$date) {
        $error = 'Please choose a date.';
    } else {
        if (addBlockedDate($date, $reason, $userId)) {
            $success = 'Blocked date added.';
        } else {
            $error = 'Failed to add blocked date (maybe already exists).';
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_block'])) {
    $id = intval($_POST['block_id']);
    if ($id && removeBlockedDate($id)) {
        $success = 'Blocked date removed.';
    } else {
        $error = 'Failed to remove blocked date.';
    }
}

$blocked = listBlockedDates();
$upcomingBlocked = array_filter($blocked, function($b) {
    return strtotime($b['date']) >= strtotime('today');
});
?>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1><img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Calendar" class="header-icon"> Blocked Dates Management</h1>
            <p class="page-subtitle">Manage holidays, closures, and unavailable dates</p>
        </div>
        <div class="header-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo count($blocked); ?></span>
                <span class="stat-label">Total Blocked</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($upcomingBlocked); ?></span>
                <span class="stat-label">Upcoming</span>
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

    <div class="content-grid">
        <!-- Add Blocked Date Section -->
        <div class="main-section">
            <div class="section-header">
                <h2><img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Add" class="section-icon"> Add Blocked Date</h2>
                <p class="section-description">Prevent appointments from being booked on specific dates</p>
            </div>

            <div class="form-container">
                <div class="info-box">
                    <h4><img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Info" class="info-icon"> What are blocked dates?</h4>
                    <ul>
                        <li>Appointments cannot be booked on these dates</li>
                        <li>Existing appointments remain unaffected</li>
                        <li>Useful for holidays, maintenance, or closures</li>
                        <li>Both clients and walk-in patients are blocked</li>
                    </ul>
                </div>

                <form method="POST" class="block-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Select Date <span class="required">*</span></label>
                            <input type="text" name="date" id="date" required placeholder="Click to select date">
                            <small class="field-help">Choose the date to block from appointments</small>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <input type="text" name="reason" id="reason" placeholder="e.g., Independence Day, Office Closure">
                            <small class="field-help">Optional description for the blocked date</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="add_block" class="btn btn-primary">
                            <img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Add" class="btn-icon">
                            Block This Date
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Calendar Preview -->
        <div class="sidebar-section">
            <div class="calendar-preview">
                <h3><img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Calendar" class="section-icon-small"> Calendar Preview</h3>
                <div id="calendar-preview" class="calendar-widget">
                    <!-- Calendar will be rendered here -->
                </div>
                <div class="calendar-legend">
                    <div class="legend-item">
                        <span class="legend-color available"></span>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color blocked"></span>
                        <span>Blocked</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Blocked Dates -->
    <div class="blocked-dates-section">
        <div class="section-header">
            <h2><img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="List" class="section-icon"> Blocked Dates List</h2>
            <p class="section-description">All currently blocked dates and their reasons</p>
        </div>

        <?php if (empty($blocked)): ?>
            <div class="empty-state">
                <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="No dates" class="empty-icon">
                <h3>No Blocked Dates</h3>
                <p>All dates are currently available for appointments. Add blocked dates above to restrict booking.</p>
            </div>
        <?php else: ?>
            <div class="dates-grid">
                <?php
                $currentMonth = '';
                foreach ($blocked as $b):
                    $month = date('F Y', strtotime($b['date']));
                    if ($month !== $currentMonth):
                        if ($currentMonth !== '') echo '</div>';
                        $currentMonth = $month;
                ?>
                    <div class="month-group">
                        <h4 class="month-title"><?php echo $month; ?></h4>
                <?php endif; ?>

                <div class="date-card <?php echo strtotime($b['date']) < strtotime('today') ? 'past' : 'future'; ?>">
                    <div class="date-info">
                        <div class="date-display">
                            <span class="day"><?php echo date('j', strtotime($b['date'])); ?></span>
                            <span class="weekday"><?php echo date('l', strtotime($b['date'])); ?></span>
                        </div>
                        <div class="date-details">
                            <div class="date-full"><?php echo date('F j, Y', strtotime($b['date'])); ?></div>
                            <?php if (!empty($b['reason'])): ?>
                                <div class="date-reason"><?php echo htmlspecialchars($b['reason']); ?></div>
                            <?php endif; ?>
                            <div class="date-added">Added <?php echo date('M j, Y', strtotime($b['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="date-actions">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this blocked date? Appointments may be bookable again.')">
                            <input type="hidden" name="block_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" name="delete_block" class="btn btn-danger btn-small">
                                <img src="<?php echo $base_url; ?>/assets/images/cross_icon.png" alt="Remove" class="btn-icon">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>

                <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
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
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.main-section, .sidebar-section {
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

.section-header h2, .section-header h3 {
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

/* Form Container */
.form-container {
    padding: 30px;
}

.info-box {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.info-box h4 {
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #92400e;
}

.info-icon {
    width: 20px;
    height: 20px;
}

.info-box ul {
    margin: 0;
    padding-left: 20px;
    color: #92400e;
}

.info-box li {
    margin-bottom: 5px;
}

.block-form {
    display: grid;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
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

.form-group input {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.field-help {
    margin-top: 4px;
    font-size: 0.85rem;
    color: #6b7280;
}

.form-actions {
    display: flex;
    justify-content: flex-start;
    padding-top: 10px;
}

/* Buttons */
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
    text-decoration: none;
}

.btn-primary {
    background: #f59e0b;
    color: white;
}

.btn-primary:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.btn-small {
    padding: 8px 16px;
    font-size: 14px;
}

.btn-icon {
    width: 16px;
    height: 16px;
}

/* Calendar Preview */
.calendar-preview {
    padding: 20px;
}

.section-icon-small {
    width: 20px;
    height: 20px;
}

.calendar-widget {
    background: #f8fafc;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calendar-legend {
    display: flex;
    gap: 20px;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.legend-color.available {
    background: #d1fae5;
}

.legend-color.blocked {
    background: #fee2e2;
}

/* Blocked Dates Section */
.blocked-dates-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-icon {
    width: 48px;
    height: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #374151;
}

.dates-grid {
    padding: 30px;
}

.month-group {
    margin-bottom: 30px;
}

.month-title {
    font-size: 1.3rem;
    color: #374151;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}

.date-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.date-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
}

.date-card.past {
    opacity: 0.7;
    background: #f9fafb;
}

.date-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.date-display {
    text-align: center;
    min-width: 60px;
}

.day {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    color: #f59e0b;
    line-height: 1;
}

.weekday {
    display: block;
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}

.date-details {
    flex: 1;
}

.date-full {
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.date-reason {
    color: #6b7280;
    font-style: italic;
    margin-bottom: 2px;
}

.date-added {
    font-size: 0.8rem;
    color: #9ca3af;
}

.date-actions {
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }

    .sidebar-section {
        order: -1;
    }
}

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

    .date-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .date-info {
        width: 100%;
    }

    .date-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
    }

    .form-container, .dates-grid {
        padding: 20px;
    }
}
</style>

<!-- Flatpickr for calendar UI -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const blocked = <?php echo json_encode(array_map(function($b){ return $b['date']; }, $blocked)); ?>;

    // Initialize date picker
    flatpickr('#date', {
        dateFormat: 'Y-m-d',
        minDate: new Date().toISOString().split('T')[0],
        disable: blocked,
        altInput: true,
        altFormat: 'F j, Y',
        onChange: function(selectedDates, dateStr, instance) {
            // Update calendar preview when date is selected
            updateCalendarPreview(dateStr);
        }
    });

    // Simple calendar preview
    function updateCalendarPreview(selectedDate) {
        const preview = document.getElementById('calendar-preview');
        if (selectedDate) {
            const date = new Date(selectedDate);
            const month = date.toLocaleString('default', { month: 'long' });
            const year = date.getFullYear();
            const day = date.getDate();

            preview.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: bold; color: #f59e0b; margin-bottom: 10px;">
                        ${month} ${year}
                    </div>
                    <div style="font-size: 2rem; font-weight: bold; color: #dc2626;">
                        ${day}
                    </div>
                    <div style="color: #dc2626; font-weight: 500;">
                        BLOCKED
                    </div>
                </div>
            `;
        } else {
            preview.innerHTML = '<div style="color: #6b7280; text-align: center;">Select a date to preview</div>';
        }
    }

    // Initialize calendar preview
    updateCalendarPreview();
});
</script>

<?php include('../includes/footer.php'); ?>
