<?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/payments.php";
require_once "../models/Appointments.php";

// Get completed appointments with outstanding payments (only show bills when service is done)
global $pdo;
$stmt = $pdo->prepare("
    SELECT a.*,
           COALESCE(u.name, CONCAT('Walk-in: ', a.walk_in_name)) as clientName,
           GROUP_CONCAT(t.name SEPARATOR ', ') as treatmentNames
    FROM Appointments a
    LEFT JOIN Users u ON a.clientId = u.id
    LEFT JOIN AppointmentTreatments at ON a.id = at.appointmentId
    LEFT JOIN Treatments t ON at.treatmentId = t.id
    WHERE a.status = 'completed'
    GROUP BY a.id
    ORDER BY a.date DESC, a.time DESC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appointmentsWithOutstanding = [];
foreach ($appointments as $appt) {
    $outstanding = getOutstandingAmount($appt['id']);
    if ($outstanding > 0) {
        $appt['outstanding'] = $outstanding;
        $appt['total'] = getBillForAppointment($appt['id'])['total'];
        $appt['paid'] = getPaidAmountForAppointment($appt['id']);
        $appointmentsWithOutstanding[] = $appt;
    }
}

// Get all payments for display
$allPaymentsStmt = $pdo->prepare("
    SELECT p.*, a.date, a.time, 
           COALESCE(u.name, CONCAT('Walk-in: ', a.walk_in_name)) as clientName
    FROM Payments p
    JOIN Appointments a ON p.appointmentId = a.id
    LEFT JOIN Users u ON a.clientId = u.id
    ORDER BY p.createdAt DESC
");
$allPaymentsStmt->execute();
$allPayments = $allPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1><img src="<?php echo $base_url; ?>/assets/images/payments.svg" alt="Payments" class="header-icon"> Payment Management</h1>
    <p>Record cash payments and manage payment statuses for completed services</p>
  </div>
</div>

<div class="container">

<!-- Payment Statistics -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/payments.svg" alt="Outstanding">
    </div>
    <div class="stat-content">
      <h3><?php echo count($appointmentsWithOutstanding); ?></h3>
      <p>Outstanding Bills</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="Total Payments">
    </div>
    <div class="stat-content">
      <h3><?php echo count($allPayments); ?></h3>
      <p>Total Payments</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/earning_icon.svg" alt="Total Amount">
    </div>
    <div class="stat-content">
      <h3>₱<?php
        $totalAmount = array_sum(array_column($allPayments, 'amount'));
        echo number_format($totalAmount, 2);
      ?></h3>
      <p>Total Collected</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Completed Services">
    </div>
    <div class="stat-content">
      <h3><?php
        $completedCount = count(array_filter($allPayments, function($p) { return $p['status'] === 'confirmed'; }));
        echo $completedCount;
      ?></h3>
      <p>Confirmed Payments</p>
    </div>
  </div>
</div>

<!-- Outstanding Payments Section -->
<?php if (count($appointmentsWithOutstanding) > 0): ?>
<div class="section">
    <div class="section-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Outstanding Payments</h2>
        <p>Completed services awaiting payment</p>
    </div>
    <div class="appointments-list">
        <?php foreach ($appointmentsWithOutstanding as $appt): ?>
            <div class="appointment-card">
                <div class="appointment-info">
                    <div class="appointment-header">
                        <h3>Appointment #<?php echo $appt['id']; ?></h3>
                        <span class="appointment-date"><?php echo date('M d, Y', strtotime($appt['date'])); ?> at <?php echo $appt['time']; ?></span>
                    </div>
                    <div class="client-info">
                        <span class="client-name">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($appt['clientName']); ?>
                            <?php if (!$appt['clientId'] && $appt['walk_in_phone']): ?>
                                (<?php echo htmlspecialchars($appt['walk_in_phone']); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="treatment-info">
                        <i class="fas fa-tooth"></i> <?php echo htmlspecialchars($appt['treatmentNames'] ?? 'N/A'); ?>
                    </div>
                    <div class="payment-summary">
                        <div class="payment-item total">
                            <span class="label">Total:</span>
                            <span class="amount">₱<?php echo number_format($appt['total'], 2); ?></span>
                        </div>
                        <div class="payment-item paid">
                            <span class="label">Paid:</span>
                            <span class="amount">₱<?php echo number_format($appt['paid'], 2); ?></span>
                        </div>
                        <div class="payment-item outstanding">
                            <span class="label">Outstanding:</span>
                            <span class="amount">₱<?php echo number_format($appt['outstanding'], 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="appointment-actions">
                    <button class="btn-record-payment" onclick="openPaymentModal(<?php echo $appt['id']; ?>, <?php echo $appt['outstanding']; ?>)">
                        <i class="fas fa-cash-register"></i> Record Payment
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="no-outstanding">
    <div class="no-outstanding-icon">
        <i class="fas fa-check-circle"></i>
    </div>
    <h3>All Caught Up!</h3>
    <p>No outstanding payments for completed services.</p>
</div>
<?php endif; ?>

<!-- All Payments Section -->
<div class="section">
    <div class="section-header">
        <h2><i class="fas fa-history"></i> Payment History</h2>
        <p>All recorded payments</p>
    </div>
    <?php if (count($allPayments) > 0): ?>
    <div class="payments-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Appointment</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allPayments as $payment): ?>
                <tr>
                    <td><?php echo $payment['id']; ?></td>
                    <td>
                        <span class="appointment-link">#<?php echo $payment['appointmentId']; ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($payment['clientName']); ?></td>
                    <td class="amount-cell">₱<?php echo number_format($payment['amount'], 2); ?></td>
                    <td>
                        <span class="method-badge <?php echo $payment['method']; ?>">
                            <i class="fas fa-<?php echo $payment['method'] === 'cash' ? 'money-bill-wave' : 'credit-card'; ?>"></i>
                            <?php echo ucfirst($payment['method']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status status-<?php echo $payment['status']; ?>">
                            <?php echo ucfirst($payment['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y H:i', strtotime($payment['createdAt'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-payments">
        <div class="no-payments-icon">
            <i class="fas fa-receipt"></i>
        </div>
        <h3>No Payments Yet</h3>
        <p>Payment records will appear here once clients start making payments.</p>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Cash Payment</h3>
            <span class="close" onclick="closePaymentModal()">&times;</span>
        </div>
        <form id="paymentForm" method="POST">
            <input type="hidden" name="appointmentId" id="appointmentId">
            <div class="form-group">
                <label for="amount">Amount (₱):</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="method">Payment Method:</label>
                <select id="method" name="method" required>
                    <option value="cash">Cash</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="confirmed">Paid</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closePaymentModal()">Cancel</button>
                <button type="submit" class="btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Dashboard Header */
.dashboard-header {
  margin-bottom: 30px;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.dashboard-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: relative;
}

.dashboard-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="50" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="30" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
  opacity: 0.3;
}

.welcome-section h1 {
  margin: 0 0 8px 0;
  font-size: 2.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 15px;
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.header-icon {
  width: 48px;
  height: 48px;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.welcome-section p {
  margin: 0;
  font-size: 1.2rem;
  opacity: 0.95;
  font-weight: 400;
  color: white;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.stat-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon img {
  width: 32px;
  height: 32px;
  opacity: 0.8;
}

.stat-content h3 {
  margin: 0 0 8px 0;
  font-size: 2.5rem;
  font-weight: 700;
  color: #1f2937;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-content p {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
  font-weight: 500;
}

.section {
    margin-bottom: 40px;
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}

.section-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e5e7eb;
}

.section-header h2 {
    margin: 0 0 8px 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-header h2::before {
    font-size: 1.5rem;
}

.section-header p {
    margin: 0;
    color: #6b7280;
    font-size: 1rem;
}

.appointments-list {
    display: grid;
    gap: 20px;
}

.appointment-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    transition: all 0.3s ease;
}

.appointment-card:hover {
    background: #f1f5f9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.appointment-info {
    flex: 1;
}

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.appointment-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 1.2rem;
}

.appointment-date {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.client-info, .treatment-info {
    margin-bottom: 8px;
}

.client-name, .treatment-info {
    color: #374151;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.client-name i, .treatment-info i {
    color: #6b7280;
    width: 16px;
}

.payment-summary {
    display: flex;
    gap: 20px;
    margin-top: 16px;
}

.payment-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.payment-item .label {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.payment-item .amount {
    font-size: 1.1rem;
    font-weight: 700;
}

.payment-item.total .amount { color: #1f2937; }
.payment-item.paid .amount { color: #059669; }
.payment-item.outstanding .amount { color: #dc2626; }

.appointment-actions {
    margin-left: 24px;
}

.btn-record-payment {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.btn-record-payment:hover {
    background: linear-gradient(135deg, #047857, #065f46);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
}

.btn-record-payment i {
    font-size: 1rem;
}

/* No Outstanding */
.no-outstanding {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border-radius: 16px;
    border: 2px solid #bbf7d0;
}

.no-outstanding-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    color: #16a34a;
    font-size: 3rem;
}

.no-outstanding h3 {
    margin: 0 0 12px 0;
    color: #166534;
    font-size: 1.5rem;
}

.no-outstanding p {
    margin: 0;
    color: #166534;
    font-size: 1rem;
}

/* Payments Table */
.payments-table {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
}

tbody tr:hover {
    background: #f8fafc;
}

.appointment-link {
    color: #3b82f6;
    font-weight: 600;
    text-decoration: none;
}

.appointment-link:hover {
    text-decoration: underline;
}

.amount-cell {
    font-weight: 700;
    color: #059669;
    font-size: 1rem;
}

.method-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.method-badge.cash {
    background: #fef3c7;
    color: #d97706;
}

.method-badge.card {
    background: #dbeafe;
    color: #2563eb;
}

.status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-confirmed {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #991b1b;
}

/* No Payments */
.no-payments {
    text-align: center;
    padding: 60px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #d1d5db;
}

.no-payments-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 20px;
}

.no-payments h3 {
    margin: 0 0 12px 0;
    color: #374151;
    font-size: 1.5rem;
}

.no-payments p {
    margin: 0;
    color: #6b7280;
    font-size: 1rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 24px 30px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 16px 16px 0 0;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 700;
}

.close {
    color: #6b7280;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.close:hover {
    color: #374151;
    background: #e5e7eb;
}

#paymentForm {
    padding: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

button[type="button"] {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

button[type="button"]:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }

    .welcome-section h1 {
        font-size: 2.2rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .appointment-card {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }

    .payment-summary {
        justify-content: center;
        flex-wrap: wrap;
    }

    .appointment-actions {
        margin-left: 0;
    }

    .modal-content {
        width: 95%;
        margin: 20% auto;
    }

    .modal-header,
    #paymentForm {
        padding: 20px;
    }
}
</style>

<script>
function openPaymentModal(appointmentId, outstandingAmount) {
    document.getElementById('appointmentId').value = appointmentId;
    document.getElementById('amount').value = outstandingAmount.toFixed(2);
    document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.getElementById('paymentForm').reset();
}

// Handle form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        appointmentId: formData.get('appointmentId'),
        amount: parseFloat(formData.get('amount')),
        method: formData.get('method'),
        status: formData.get('status')
    };

    fetch('<?php echo $base_url; ?>/api/payments.php?action=create_payment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Payment recorded successfully!');
            closePaymentModal();
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while recording the payment.');
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target == modal) {
        closePaymentModal();
    }
}
</script>

<?php include("../includes/footer.php"); ?>
