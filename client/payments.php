<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/payments.php";
require_once "../models/Appointments.php";

$clientId = $_SESSION['user_id'];

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $appointmentId = $_POST['appointment_id'];
    // Calculate outstanding amount server-side to prevent tampering
    require_once __DIR__ . '/../models/payments.php';
    $amount = getOutstandingAmount($appointmentId);
    $method = $_POST['method'];

    if (empty($appointmentId) || empty($amount) || empty($method)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } else {
        // Generate a mock transaction ID
        $txId = 'TXN' . time() . rand(1000, 9999);

        $paymentId = logPayment($appointmentId, $amount, $method, 'confirmed', $txId);
        if ($paymentId) {
            $success = "Payment processed successfully! Transaction ID: " . $txId;
        } else {
            $error = "Failed to process payment. Please try again.";
        }
    }
}

// Get client's appointments for payment selection
$appointments = listClientAppointments($clientId);

// Get client's payment history
global $pdo;
$stmt = $pdo->prepare("
    SELECT p.*, a.date as appointmentDate, a.time as appointmentTime, d.name as dentistName
    FROM Payments p
    JOIN Appointments a ON p.appointmentId = a.id
    JOIN Users d ON a.dentistId = d.id
    WHERE a.clientId = ?
    ORDER BY p.createdAt DESC
");
$stmt->execute([$clientId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total spent
$totalSpent = 0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'confirmed') {
        $totalSpent += $payment['amount'];
    }
}
?>

<div class="container">
    <h1>My Payments</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Payment Summary -->
    <div class="payment-summary">
        <div class="summary-card">
            <div class="summary-icon">
                <img src="<?php echo $base_url; ?>/assets/images/earning_icon.svg" alt="Total Spent">
            </div>
            <div class="summary-content">
                <h3>₱<?php echo number_format($totalSpent, 2); ?></h3>
                <p>Total Spent</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon">
                <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Payments Made">
            </div>
            <div class="summary-content">
                <h3><?php echo count($payments); ?></h3>
                <p>Payments Made</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon">
                <img src="<?php echo $base_url; ?>/assets/images/tick_icon.svg" alt="Successful">
            </div>
            <div class="summary-content">
                <h3><?php echo count(array_filter($payments, fn($p) => $p['status'] === 'confirmed')); ?></h3>
                <p>Successful</p>
            </div>
        </div>
    </div>

    <!-- Make Payment section is now integrated into the bill modal -->

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const apptSelect = document.getElementById('appointment_id');
        const billDiv = document.getElementById('bill_details');
        const amountInput = document.getElementById('amount');

        async function loadBill(appointmentId) {
            if (!appointmentId) {
                billDiv.innerHTML = '<em>Select an appointment to view the bill.</em>';
                amountInput.value = '';
                return;
            }
            billDiv.innerHTML = 'Loading bill...';
            try {
                const res = await fetch('../api/payments.php?action=get_appointment_bill&appointment_id=' + encodeURIComponent(appointmentId), { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.success) {
                    billDiv.innerHTML = '<div style="color:#dc2626;">' + (data.error || 'Failed to load bill') + '</div>';
                    amountInput.value = '';
                    return;
                }
                const bill = data.bill;
                const paid = parseFloat(data.paid || 0);
                const outstanding = parseFloat(data.outstanding || 0);

                let html = '<ul style="list-style:none;padding:0;margin:0;">';
                bill.items.forEach(i => {
                    html += '<li style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #e5e7eb;"><span>' + escapeHtml(i.name) + '</span><strong>₱' + parseFloat(i.price).toFixed(2) + '</strong></li>';
                });
                html += '</ul>';
                html += '<div style="margin-top:10px;display:flex;justify-content:space-between;font-weight:700;"><span>Total</span><span>₱' + parseFloat(bill.total).toFixed(2) + '</span></div>';
                html += '<div style="margin-top:6px;display:flex;justify-content:space-between;color:#6b7280;"><span>Paid</span><span>₱' + paid.toFixed(2) + '</span></div>';
                html += '<div style="margin-top:8px;display:flex;justify-content:space-between;color:#b91c1c;font-weight:700;"><span>Outstanding</span><span>₱' + outstanding.toFixed(2) + '</span></div>';

                billDiv.innerHTML = html;
                amountInput.value = outstanding.toFixed(2);
            } catch (err) {
                billDiv.innerHTML = '<div style="color:#dc2626;">Error loading bill</div>';
                amountInput.value = '';
            }
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>\"]/g, function (s) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[s];
            });
        }

        apptSelect.addEventListener('change', function(){
            loadBill(this.value);
        });

        // Load selected appointment bill on page load if any
        if (apptSelect.value) loadBill(apptSelect.value);
    });
    </script>

    <!-- Payment History -->
    <div class="payments-section">
        <h2>Payment History</h2>
        <?php if (empty($payments)): ?>
            <div class="no-payments">
                <p>You haven't made any payments yet.</p>
            </div>
        <?php else: ?>
            <div class="payments-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Appointment</th>
                            <th>Dentist</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Bill</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['createdAt'])); ?><br>
                                    <small><?php echo date('H:i', strtotime($payment['createdAt'])); ?></small></td>
                                <td><?php echo date('M d, Y', strtotime($payment['appointmentDate'])) . '<br><small>' . date('H:i', strtotime($payment['appointmentTime'])) . '</small>'; ?></td>
                                <td>Dr. <?php echo htmlspecialchars($payment['dentistName']); ?></td>
                                <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($payment['method']); ?></td>
                                <td>
                                    <span class="payment-status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['transactionId'] ?? 'N/A'); ?></td>
                                <td>
                                    <button type="button" class="btn-view-bill" data-appointment-id="<?php echo $payment['appointmentId']; ?>">View Bill</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.payment-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.summary-card {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
    padding: 25px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(14, 165, 233, 0.2);
    transition: transform 0.2s;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon img {
    width: 40px;
    height: 40px;
    filter: brightness(0) invert(1);
}

.summary-content h3 {
    margin: 0 0 5px 0;
    font-size: 2rem;
    font-weight: 700;
}

.summary-content p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.payment-section {
    background: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 40px;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}

.payment-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    max-width: 800px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-group input,
.form-group select {
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.method-option {
    position: relative;
    cursor: pointer;
}

.method-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.method-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    transition: all 0.2s;
    text-align: center;
}

.method-card img {
    width: 40px;
    height: 40px;
    object-fit: contain;
}

.method-card span {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.method-option input[type="radio"]:checked + .method-card {
    border-color: #0ea5e9;
    background: #f0f9ff;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.btn-payment {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 16px 32px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    grid-column: 1 / -1;
    margin-top: 10px;
}

.btn-payment:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.payments-table {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}

.payments-table table {
    width: 100%;
    border-collapse: collapse;
}

.payments-table th {
    background: #f8fafc;
    padding: 18px 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.payments-table td {
    padding: 18px 15px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.payments-table td small {
    color: #6b7280;
    font-size: 0.85rem;
}

.payment-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.payment-status.confirmed {
    background: #d1fae5;
    color: #065f46;
}

.payment-status.pending {
    background: #fef3c7;
    color: #92400e;
}

.payment-status.failed {
    background: #fee2e2;
    color: #dc2626;
}

.no-payments {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}

.no-payments p {
    color: #6b7280;
    font-size: 1.1rem;
    margin: 0;
}

.success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    padding: 18px 24px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid #6ee7b7;
    font-weight: 600;
}

.error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
    padding: 18px 24px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid #f87171;
    font-weight: 600;
}

@media (max-width: 768px) {
    .payment-summary {
        grid-template-columns: 1fr;
    }

    .payment-form {
        grid-template-columns: 1fr;
    }

    .payment-methods {
        grid-template-columns: repeat(2, 1fr);
    }

    .payments-table {
        font-size: 0.9rem;
    }

    .payments-table th,
    .payments-table td {
        padding: 12px 8px;
    }
}
</style>

<!-- Bill Modal -->
<div id="billModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:white;max-width:720px;width:95%;border-radius:12px;padding:20px;position:relative;">
    <button id="closeBillModal" style="position:absolute;right:12px;top:12px;border:none;background:#ef4444;color:white;padding:6px 10px;border-radius:6px;cursor:pointer;">Close</button>
    <h2>Bill Details</h2>
    <div id="billContent">Loading...</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
    function qsa(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

    function escapeHtml(str) {
        return String(str).replace(/[&<>\"]/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[s];
        });
    }

    const modal = qs('#billModal');
    const billContent = qs('#billContent');
    const closeBtn = qs('#closeBillModal');

    closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });

    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btn-view-bill');
        if (!btn) return;
        const appointmentId = btn.getAttribute('data-appointment-id');
        if (!appointmentId) return;

        modal.style.display = 'flex';
        billContent.innerHTML = 'Loading bill...';

        try {
            const [billRes, paymentsRes] = await Promise.all([
                fetch('../api/payments.php?action=get_appointment_bill&appointment_id=' + encodeURIComponent(appointmentId), { credentials: 'same-origin' }),
                fetch('../api/payments.php?action=get_appointment_payments&appointment_id=' + encodeURIComponent(appointmentId), { credentials: 'same-origin' })
            ]);

            const billData = await billRes.json();
            const payData = await paymentsRes.json();

            if (!billData.success) {
                billContent.innerHTML = '<div style="color:#dc2626;">' + (billData.error || 'Failed to load bill') + '</div>';
                return;
            }

            const bill = billData.bill;
            const paid = parseFloat(billData.paid || 0);
            const outstanding = parseFloat(billData.outstanding || 0);

            let html = '<div style="max-height:60vh;overflow:auto;padding-right:8px;">';
            html += '<ul style="list-style:none;padding:0;margin:0;">';
            bill.items.forEach(i => {
                html += '<li style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #e5e7eb;"><span>' + escapeHtml(i.name) + '</span><strong>₱' + parseFloat(i.price).toFixed(2) + '</strong></li>';
            });
            html += '</ul>';
            html += '<div style="margin-top:12px;display:flex;justify-content:space-between;font-weight:700;border-top:1px solid #e5e7eb;padding-top:12px;"><span>Total</span><span>₱' + parseFloat(bill.total).toFixed(2) + '</span></div>';
            html += '<div style="margin-top:6px;display:flex;justify-content:space-between;color:#6b7280;"><span>Paid</span><span>₱' + paid.toFixed(2) + '</span></div>';
            html += '<div style="margin-top:8px;display:flex;justify-content:space-between;color:#b91c1c;font-weight:700;"><span>Outstanding</span><span>₱' + outstanding.toFixed(2) + '</span></div>';

            // Payment form if outstanding > 0
            if (outstanding > 0) {
                html += '<div style="margin-top:20px;padding:20px;background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">';
                html += '<h3 style="margin:0 0 15px 0;color:#0ea5e9;">Make a Payment</h3>';
                html += '<form id="paymentForm" data-appointment-id="' + appointmentId + '">';
                html += '<input type="hidden" name="appointment_id" value="' + appointmentId + '">';
                html += '<input type="hidden" name="amount" value="' + outstanding.toFixed(2) + '">';

                html += '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-weight:600;">Payment Method:</label>';
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">';

                const methods = [
                    {id: 'gcash', name: 'GCash', logo: '<?php echo $base_url; ?>/assets/images/gcash.png'},
                    {id: 'maya', name: 'Maya', logo: '<?php echo $base_url; ?>/assets/images/maya.png'},
                    {id: 'gotyme', name: 'GoTyme', logo: '<?php echo $base_url; ?>/assets/images/gotyme.png'},
                    {id: 'bank', name: 'Bank Transfer', logo: '<?php echo $base_url; ?>/assets/images/bank.png'}
                ];

                methods.forEach(method => {
                    html += '<label style="position:relative;cursor:pointer;">';
                    html += '<input type="radio" name="method" value="' + method.id + '" style="position:absolute;opacity:0;width:0;height:0;" required>';
                    html += '<div style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:15px 10px;border:2px solid #e5e7eb;border-radius:8px;background:white;transition:all 0.2s;">';
                    html += '<img src="' + method.logo + '" alt="' + method.name + '" style="width:30px;height:30px;object-fit:contain;" onerror="this.style.display=\'none\'">';
                    html += '<span style="font-weight:600;color:#374151;font-size:0.85rem;">' + method.name + '</span>';
                    html += '</div>';
                    html += '</label>';
                });

                html += '</div></div>';

                html += '<button type="submit" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:white;padding:12px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;width:100%;">Pay ₱' + outstanding.toFixed(2) + '</button>';
                html += '</form>';
                html += '</div>';
            }

            // Payments list
            if (payData.success && payData.payments && payData.payments.length) {
                html += '<h3 style="margin-top:16px;">Related Payments</h3>';
                html += '<table style="width:100%;border-collapse:collapse;margin-top:8px;">';
                html += '<thead><tr><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px">Date</th><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px">Amount</th><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px">Method</th><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px">Status</th></tr></thead>';
                html += '<tbody>';
                payData.payments.forEach(p => {
                    html += '<tr>';
                    html += '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' + (p.createdAt ? new Date(p.createdAt).toLocaleString() : '') + '</td>';
                    html += '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">₱' + parseFloat(p.amount).toFixed(2) + '</td>';
                    html += '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' + (p.method ? p.method : '') + '</td>';
                    html += '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' + (p.status ? p.status : '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }

            html += '</div>';

            billContent.innerHTML = html;

            // Handle payment form submission
            const paymentForm = qs('#paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());

                    // Disable button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processing...';

                    try {
                        const res = await fetch('../api/payments.php?action=process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data),
                            credentials: 'same-origin'
                        });

                        const result = await res.json();

                        if (result.success) {
                            // Success - reload page to show updated data
                            alert('Payment processed successfully! Transaction ID: ' + (result.transactionId || 'N/A'));
                            location.reload();
                        } else {
                            alert('Payment failed: ' + (result.error || 'Unknown error'));
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Pay ₱' + outstanding.toFixed(2);
                        }
                    } catch (err) {
                        alert('Error processing payment. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Pay ₱' + outstanding.toFixed(2);
                    }
                });
            }

        } catch (err) {
            billContent.innerHTML = '<div style="color:#dc2626;">Error loading bill</div>';
        }
    });
});
</script>

<?php include("../includes/footer.php"); ?>
