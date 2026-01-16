<?php
require_once "../includes/guards.php";
if (!in_array(role(), ['admin', 'dentist', 'dentist_pending'])) {
  header('Location: ' . $base_url . '/public/login.php');
  exit;
}
include("../includes/header.php");
require_once "../models/Appointments.php";
require_once "../models/users.php";

// Create DentalRecords table if it doesn't exist
global $pdo;
$createTableSQL = "
  CREATE TABLE IF NOT EXISTS dentalrecords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patientId INT,
    patientName VARCHAR(255),
    patientPhone VARCHAR(20),
    recordDate DATE,
    age INT,
    gender VARCHAR(10),
    periodontal VARCHAR(100),
    occlusion VARCHAR(50),
    dentalData JSON,  
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
";
try {
  $pdo->exec($createTableSQL);
} catch (Exception $e) {
  // Table may already exist
}

$patientId = $_GET['patient_id'] ?? null;
$walkinName = $_GET['walkin_name'] ?? null;
$walkinPhone = $_GET['walkin_phone'] ?? null;

if (!$patientId && (!$walkinName || !$walkinPhone)) {
  header('Location: patients.php');
  exit;
}

$isWalkin = false;
if ($walkinName && $walkinPhone) {
  $isWalkin = true;
  $patient = [
    'name' => $walkinName,
    'phone' => $walkinPhone,
    'email' => 'Walk-in Patient',
    'id' => null
  ];
} else {
  // Get patient info
  $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
  $stmt->execute([$patientId]);
  $patient = $stmt->fetch();

  if (!$patient) {
    header('Location: patients.php');
    exit;
  }
}

// Get appointments for this patient
if ($isWalkin) {
  $stmt = $pdo->prepare("
    SELECT a.*, d.name as dentistName
    FROM Appointments a
    LEFT JOIN Users d ON a.dentistId = d.id
    WHERE a.walk_in_name = ? AND a.walk_in_phone = ?
    ORDER BY a.date DESC, a.time DESC
  ");
  $stmt->execute([$walkinName, $walkinPhone]);
} else {
  $stmt = $pdo->prepare("
    SELECT a.*, d.name as dentistName
    FROM Appointments a
    LEFT JOIN Users d ON a.dentistId = d.id
    WHERE a.clientId = ?
    ORDER BY a.date DESC, a.time DESC
  ");
  $stmt->execute([$patientId]);
}
$appointments = $stmt->fetchAll();

// Calculate statistics
$totalAppointments = count($appointments);
$completedAppointments = 0;
$totalRevenue = 0;
$lastVisit = null;

foreach ($appointments as $appointment) {
  if ($appointment['status'] === 'completed') {
    $completedAppointments++;
    $totalRevenue += $appointment['price'] ?? 0;
  }
  if (!$lastVisit || strtotime($appointment['date']) > strtotime($lastVisit)) {
    $lastVisit = $appointment['date'];
  }
}

// Handle dental record submission - save to database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dental_record'])) {
  global $pdo;

  // Prepare dental data as JSON
  $dentalData = [
    'teeth' => [],
    'restorations' => $_POST['restoration'] ?? [],
    'surgery' => $_POST['surgery'] ?? [],
    'xray' => $_POST['xray'] ?? [],
    'appliances' => $_POST['appliances'] ?? [],
    'tmd' => $_POST['tmd'] ?? []
  ];

  // Collect teeth data
  foreach ($_POST as $key => $value) {
    if (strpos($key, 'tooth_') === 0) {
      $dentalData['teeth'][$key] = $value;
    }
  }

  $editRecordId = $_POST['edit_record_id'] ?? null;

  if ($editRecordId) {
    // Update existing record
    $stmt = $pdo->prepare("
      UPDATE dentalrecords SET
        patientName = ?, recordDate = ?, age = ?, gender = ?, periodontal = ?, occlusion = ?, dentalData = ?, updatedAt = CURRENT_TIMESTAMP
      WHERE id = ?
    ");

    $stmt->execute([
      $_POST['name'] ?? $patient['name'],
      $_POST['date'] ?? date('Y-m-d'),
      $_POST['age'] ?? null,
      $_POST['gender'] ?? '',
      $_POST['periodontal'] ?? '',
      $_POST['occlusion'] ?? '',
      json_encode($dentalData),
      $editRecordId
    ]);

    $successMessage = "Dental record updated successfully!";
  } else {
    // Insert new record
    $stmt = $pdo->prepare("
      INSERT INTO dentalrecords (patientId, patientName, patientPhone, recordDate, age, gender, periodontal, occlusion, dentalData)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
      $isWalkin ? null : $patientId,
      $_POST['name'] ?? $patient['name'],
      $isWalkin ? $walkinPhone : ($patient['phone'] ?? ''),
      $_POST['date'] ?? date('Y-m-d'),
      $_POST['age'] ?? null,
      $_POST['gender'] ?? '',
      $_POST['periodontal'] ?? '',
      $_POST['occlusion'] ?? '',
      json_encode($dentalData)
    ]);

    $successMessage = "Dental record saved successfully!";
  }
}

// Handle delete record
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['record_id'])) {
  global $pdo;
  $recordId = $_GET['record_id'];

  $stmt = $pdo->prepare("DELETE FROM dentalrecords WHERE id = ?");
  $stmt->execute([$recordId]);

  header("Location: patient_details.php?" . ($isWalkin ? "walkin_name=" . urlencode($walkinName) . "&walkin_phone=" . urlencode($walkinPhone) : "patient_id=" . $patientId));
  exit;
}

// Get saved dental records
$dentalRecords = [];
if ($isWalkin) {
  $stmt = $pdo->prepare("SELECT * FROM dentalrecords WHERE patientPhone = ? ORDER BY recordDate DESC");
  $stmt->execute([$walkinPhone]);
} else {
  $stmt = $pdo->prepare("SELECT * FROM dentalrecords WHERE patientId = ? ORDER BY recordDate DESC");
  $stmt->execute([$patientId]);
}
$dentalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1>Patient Details</h1>
    <p><?php echo htmlspecialchars($patient['name']); ?> - Treatment History</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Patient" style="width: 60px; height: 60px; border-radius: 50%;">
  </div>
</div>

<div class="container">

<!-- Patient Info -->
<div class="patient-overview">
  <div class="overview-header">
    <div class="patient-avatar">
      <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Patient Avatar">
      <div class="patient-status">
        <span class="status-dot <?php echo $isWalkin ? 'walkin' : 'registered'; ?>"></span>
        <span class="status-text"><?php echo $isWalkin ? 'Walk-in Patient' : 'Registered Patient'; ?></span>
      </div>
    </div>
    <div class="patient-basic-info">
      <h1><?php echo htmlspecialchars($patient['name']); ?></h1>
      <p class="patient-contact">
        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?>
        <?php if (!$isWalkin && !empty($patient['phone'])): ?>
          | <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-calendar-check"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo $totalAppointments; ?></div>
        <div class="stat-label">Total Appointments</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo $completedAppointments; ?></div>
        <div class="stat-label">Completed</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-clock"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo $totalAppointments - $completedAppointments; ?></div>
        <div class="stat-label">Pending</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-calendar-alt"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?php echo $lastVisit ? date('M d', strtotime($lastVisit)) : 'Never'; ?></div>
        <div class="stat-label">Last Visit</div>
      </div>
    </div>
  </div>
</div>

<!-- Dental Record Chart -->
<div class="activity-section">
  <h2>Dental Record Chart</h2>

  <?php if (isset($successMessage)) { ?>
    <div style="margin-bottom: 20px; padding: 15px; background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 8px; color: #065f46;">
      ✓ <?php echo $successMessage; ?>
    </div>
  <?php } ?>

  <form method="POST" style="margin-top: 20px;">
    <input type="hidden" name="save_dental_record" value="1">

    <!-- Patient Info -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Name:</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
      </div>

      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Age:</label>
        <input type="number" name="age" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
      </div>

      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Gender:</label>
        <select name="gender" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
          <option value="">Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Date:</label>
        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
      </div>
    </div>

    <hr style="margin: 30px 0;">

    <h3 style="margin-bottom: 20px;">Intraoral Examination</h3>
    
    <!-- PERMANENT TEETH GRID -->
    <div style="margin-bottom: 30px;">
      <h4 style="margin-bottom: 15px; text-align: center; color: #1e40af;">Permanent Teeth (Upper)</h4>
      <div style="display: flex; justify-content: center; gap: 4px; flex-wrap: wrap; padding: 15px; background: linear-gradient(to bottom, #f0f9ff, #e0f2fe); border-radius: 12px; border: 2px solid #0284c7;">
        <?php
          $upperTeeth = array_merge(range(18,11), range(21,28));
          foreach ($upperTeeth as $t) {
            echo "<div style='display: flex; flex-direction: column; align-items: center; gap: 4px;'>
                    <div style='width: 35px; height: 45px; background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%); border: 2px solid #1e40af; border-radius: 4px 4px 8px 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #1e40af; font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.8); position: relative;'>
                      $t
                      <div style='position: absolute; top: 2px; left: 0; right: 0; height: 1px; background: rgba(255,255,255,0.6);'></div>
                    </div>
                    <input type='text' name='tooth_$t' placeholder='D/F/M' style='width: 35px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 3px; text-align: center; font-size: 11px; background: #fafafa;'>
                  </div>";
          }
        ?>
      </div>
    </div>

    <div style="margin-bottom: 20px;">
      <h4 style="margin-bottom: 15px; text-align: center; color: #7c3aed;">Permanent Teeth (Lower)</h4>
      <div style="display: flex; justify-content: center; gap: 4px; flex-wrap: wrap; padding: 15px; background: linear-gradient(to bottom, #faf5ff, #f3e8ff); border-radius: 12px; border: 2px solid #a855f7;">
        <?php
          $lowerTeeth = array_merge(range(48,41), range(31,38));
          foreach ($lowerTeeth as $t) {
            echo "<div style='display: flex; flex-direction: column; align-items: center; gap: 4px;'>
                    <input type='text' name='tooth_$t' placeholder='D/F/M' style='width: 35px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 3px; text-align: center; font-size: 11px; background: #fafafa;'>
                    <div style='width: 35px; height: 45px; background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%); border: 2px solid #7c3aed; border-radius: 8px 8px 4px 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #7c3aed; font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.8); position: relative;'>
                      $t
                      <div style='position: absolute; bottom: 2px; left: 0; right: 0; height: 1px; background: rgba(0,0,0,0.1);'></div>
                    </div>
                  </div>";
          }
        ?>
      </div>
    </div>

    <hr style="margin: 30px 0;">

    <!-- LEGEND -->
    <h3 style="margin-bottom: 15px;">Legend (Condition)</h3>
    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <ul style="margin: 0; padding-left: 20px;">
        <li><strong>D</strong> – Decayed Tooth</li>
        <li><strong>F</strong> – Filled Tooth</li>
        <li><strong>M</strong> – Missing Tooth (Caries)</li>
        <li><strong>MO</strong> – Missing Tooth (Other Causes)</li>
        <li><strong>U</strong> – Unerupted</li>
        <li><strong>Fr</strong> – Fractured Tooth</li>
        <li><strong>Ab</strong> – Abutment</li>
        <li><strong>Rm</strong> – Removable Denture</li>
      </ul>
    </div>

    <hr style="margin: 30px 0;">

    <!-- RESTORATIONS -->
    <h3 style="margin-bottom: 15px;">Restorations & Prosthetics</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 20px;">
      <label style="display: flex; align-items: center;"><input type="checkbox" name="restoration[]" value="Amalgam" style="margin-right: 8px;"> Amalgam</label>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="restoration[]" value="Composite" style="margin-right: 8px;"> Composite</label>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="restoration[]" value="Jacket Crown" style="margin-right: 8px;"> Jacket Crown</label>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="restoration[]" value="Pontic" style="margin-right: 8px;"> Pontic</label>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="restoration[]" value="Implant" style="margin-right: 8px;"> Implant</label>
    </div>

    <hr style="margin: 30px 0;">

    <!-- SURGERY -->
    <h3 style="margin-bottom: 15px;">Surgery</h3>
    <div style="margin-bottom: 20px;">
      <label style="display: flex; align-items: center;"><input type="checkbox" name="surgery[]" value="X" style="margin-right: 8px;"> Extraction (Caries)</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="surgery[]" value="XO" style="margin-right: 8px;"> Extraction (Other causes)</label>
    </div>

    <hr style="margin: 30px 0;">

    <!-- X-RAY -->
    <h3 style="margin-bottom: 15px;">X-Ray Taken</h3>
    <div style="margin-bottom: 20px;">
      <label style="display: flex; align-items: center;"><input type="checkbox" name="xray[]" value="Periapical" style="margin-right: 8px;"> Periapical</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="xray[]" value="Panoramic" style="margin-right: 8px;"> Panoramic</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="xray[]" value="Cephalometric" style="margin-right: 8px;"> Cephalometric</label>
    </div>

    <hr style="margin: 30px 0;">

    <!-- PERIODONTAL -->
    <h3 style="margin-bottom: 15px;">Periodontal Screening</h3>
    <select name="periodontal" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 20px;">
      <option value="Gingivitis">Gingivitis</option>
      <option value="Early Periodontitis">Early Periodontitis</option>
      <option value="Moderate">Moderate</option>
      <option value="Advanced">Advanced</option>
    </select>

    <hr style="margin: 30px 0;">

    <!-- OCCLUSION -->
    <h3 style="margin-bottom: 15px;">Occlusion</h3>
    <div style="margin-bottom: 20px;">
      <label style="display: flex; align-items: center;"><input type="radio" name="occlusion" value="Class I" style="margin-right: 8px;"> Class I</label><br>
      <label style="display: flex; align-items: center;"><input type="radio" name="occlusion" value="Class II" style="margin-right: 8px;"> Class II</label><br>
      <label style="display: flex; align-items: center;"><input type="radio" name="occlusion" value="Class III" style="margin-right: 8px;"> Class III</label>
    </div>

    <hr style="margin: 30px 0;">

    <!-- APPLIANCES -->
    <h3 style="margin-bottom: 15px;">Appliances</h3>
    <div style="margin-bottom: 20px;">
      <label style="display: flex; align-items: center;"><input type="checkbox" name="appliances[]" value="Orthodontic" style="margin-right: 8px;"> Orthodontic</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="appliances[]" value="Stayplate" style="margin-right: 8px;"> Stayplate</label>
    </div>

    <hr style="margin: 30px 0;">

    <!-- TMD -->
    <h3 style="margin-bottom: 15px;">TMD Screening</h3>
    <div style="margin-bottom: 30px;">
      <label style="display: flex; align-items: center;"><input type="checkbox" name="tmd[]" value="Clenching" style="margin-right: 8px;"> Clenching</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="tmd[]" value="Clicking" style="margin-right: 8px;"> Clicking</label><br>
      <label style="display: flex; align-items: center;"><input type="checkbox" name="tmd[]" value="Tenderness" style="margin-right: 8px;"> Tenderness</label>
    </div>

    <div style="text-align: center;">
      <button type="submit" style="background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer;">Save Dental Record</button>
    </div>
  </form>
</div>

<!-- SAVED DENTAL RECORDS -->
<div class="activity-section" style="margin-top: 40px;">
  <h2><i class="fas fa-file-medical"></i> Dental Records</h2>

  <?php if (empty($dentalRecords)) { ?>
    <div class="no-records">
      <i class="fas fa-clipboard-list"></i>
      <h3>No Dental Records</h3>
      <p>No dental records found for this patient. Submit the form above to create one.</p>
    </div>
  <?php } else { ?>
    <?php foreach ($dentalRecords as $record) { ?>
      <div class="record-card">
        <div class="record-header">
          <div class="record-info">
            <h3><?php echo htmlspecialchars($record['patientName']); ?></h3>
            <div class="record-meta">
              <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($record['recordDate'])); ?></span>
              <span><i class="fas fa-user"></i> Age: <?php echo $record['age'] ?? 'N/A'; ?></span>
              <span><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($record['gender']); ?></span>
              <span><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($record['createdAt'])); ?></span>
            </div>
          </div>
          <div class="record-actions">
            <button class="btn-edit" onclick="editRecord(<?php echo $record['id']; ?>)">
              <i class="fas fa-edit"></i> Edit
            </button>
            <a href="patient_details.php?<?php echo $isWalkin ? 'walkin_name=' . urlencode($walkinName) . '&walkin_phone=' . urlencode($walkinPhone) : 'patient_id=' . $patientId; ?>&action=delete&record_id=<?php echo $record['id']; ?>" onclick="return confirm('Are you sure you want to delete this record?')" class="btn-delete">
              <i class="fas fa-trash"></i> Delete
            </a>
          </div>
        </div>

        <div class="record-details">
          <?php
            $dentalData = json_decode($record['dentalData'], true);

            if (!empty($dentalData['restorations'])) {
              echo "<div><strong>Restorations:</strong> " . implode(', ', $dentalData['restorations']) . "</div>";
            }

            if (!empty($dentalData['surgery'])) {
              echo "<div><strong>Surgery:</strong> " . implode(', ', $dentalData['surgery']) . "</div>";
            }

            if (!empty($dentalData['xray'])) {
              echo "<div><strong>X-Ray:</strong> " . implode(', ', $dentalData['xray']) . "</div>";
            }

            if (!empty($record['periodontal'])) {
              echo "<div><strong>Periodontal:</strong> " . htmlspecialchars($record['periodontal']) . "</div>";
            }

            if (!empty($record['occlusion'])) {
              echo "<div><strong>Occlusion:</strong> " . htmlspecialchars($record['occlusion']) . "</div>";
            }

            if (!empty($dentalData['appliances'])) {
              echo "<div><strong>Appliances:</strong> " . implode(', ', $dentalData['appliances']) . "</div>";
            }

            if (!empty($dentalData['tmd'])) {
              echo "<div><strong>TMD:</strong> " . implode(', ', $dentalData['tmd']) . "</div>";
            }
          ?>
        </div>

        <details class="record-details-toggle">
          <summary><i class="fas fa-chevron-down"></i> View Full Details</summary>
          <div class="record-full-details">
            <pre><?php echo htmlspecialchars(json_encode($dentalData, JSON_PRETTY_PRINT)); ?></pre>
          </div>
        </details>
      </div>
    <?php } ?>
  <?php } ?>
</div>

<!-- Back Button -->
<div style="margin-top: 30px; text-align: center;">
  <a href="patients.php" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">← Back to Patients</a>
</div>

</div>

<style>
/* Patient Overview */
.patient-overview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 40px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.overview-header {
    display: flex;
    align-items: center;
    gap: 25px;
    margin-bottom: 30px;
}

.patient-avatar {
    position: relative;
}

.patient-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.patient-status {
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 4px 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-dot.registered {
    background: #10b981;
}

.status-dot.walkin {
    background: #f59e0b;
}

.status-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
}

.patient-basic-info h1 {
    margin: 0 0 8px 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.patient-contact {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.patient-contact i {
    margin-right: 8px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 2px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    font-weight: 500;
}

/* Activity Section */
.activity-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.activity-section h2 {
    margin: 0 0 20px 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

/* Form styling */
.activity-section form {
    margin-top: 20px;
}

.activity-section label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #374151;
}

.activity-section input, .activity-section select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
}

/* Dental records styling */
.record-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.record-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.record-info h3 {
    margin: 0 0 5px 0;
    color: #1f2937;
}

.record-meta {
    color: #6b7280;
    font-size: 0.9rem;
}

.record-actions {
    display: flex;
    gap: 10px;
}

.btn-edit, .btn-delete {
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-edit:hover {
    background: #2563eb;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.record-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.record-details div {
    padding: 8px;
    background: #f8fafc;
    border-radius: 4px;
    font-size: 0.9rem;
}

.record-details strong {
    color: #374151;
}

.record-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 8px;
}

.record-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
}

.record-meta i {
    color: #6b7280;
}

.record-details-toggle {
    margin-top: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #f8fafc;
}

.record-details-toggle summary {
    padding: 12px 16px;
    cursor: pointer;
    font-weight: 500;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
}

.record-details-toggle summary:hover {
    background: #f1f5f9;
}

.record-full-details {
    padding: 16px;
    border-top: 1px solid #e5e7eb;
    background: white;
}

.record-full-details pre {
    margin: 0;
    font-size: 0.85rem;
    color: #374151;
    overflow-x: auto;
}

.no-records {
    text-align: center;
    padding: 60px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #d1d5db;
}

.no-records i {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 20px;
}

.no-records h3 {
    margin: 0 0 10px 0;
    color: #374151;
    font-size: 1.25rem;
}

.no-records p {
    margin: 0;
    color: #6b7280;
}

/* Responsive */
@media (max-width: 768px) {
    .patient-overview {
        padding: 20px;
    }

    .overview-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card {
        padding: 15px;
    }

    .activity-section {
        padding: 20px;
    }

    .record-header {
        flex-direction: column;
        gap: 10px;
    }

    .record-actions {
        justify-content: center;
    }

    .record-meta {
        justify-content: center;
    }
}
</style>

<script>
function editRecord(recordId) {
    // Find the record data
    const records = <?php echo json_encode($dentalRecords); ?>;
    const record = records.find(r => r.id == recordId);

    if (!record) {
        alert('Record not found');
        return;
    }

    // Populate the form with record data
    document.querySelector('input[name="name"]').value = record.patientName || '';
    document.querySelector('input[name="age"]').value = record.age || '';
    document.querySelector('select[name="gender"]').value = record.gender || '';
    document.querySelector('input[name="date"]').value = record.recordDate || '';

    // Parse dental data
    const dentalData = JSON.parse(record.dentalData || '{}');

    // Clear all checkboxes first
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

    // Populate checkboxes
    if (dentalData.restorations) {
        dentalData.restorations.forEach(restoration => {
            const checkbox = document.querySelector(`input[name="restoration[]"][value="${restoration}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }

    if (dentalData.surgery) {
        dentalData.surgery.forEach(surgery => {
            const checkbox = document.querySelector(`input[name="surgery[]"][value="${surgery}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }

    if (dentalData.xray) {
        dentalData.xray.forEach(xray => {
            const checkbox = document.querySelector(`input[name="xray[]"][value="${xray}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }

    if (dentalData.appliances) {
        dentalData.appliances.forEach(appliance => {
            const checkbox = document.querySelector(`input[name="appliances[]"][value="${appliance}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }

    if (dentalData.tmd) {
        dentalData.tmd.forEach(tmd => {
            const checkbox = document.querySelector(`input[name="tmd[]"][value="${tmd}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }

    // Populate select fields
    if (record.periodontal) {
        document.querySelector('select[name="periodontal"]').value = record.periodontal;
    }

    if (record.occlusion) {
        document.querySelector(`input[name="occlusion"][value="${record.occlusion}"]`).checked = true;
    }

    // Populate teeth data
    if (dentalData.teeth) {
        Object.keys(dentalData.teeth).forEach(key => {
            const input = document.querySelector(`input[name="${key}"]`);
            if (input) input.value = dentalData.teeth[key];
        });
    }

    // Add hidden field for record ID to indicate edit mode
    let editInput = document.querySelector('input[name="edit_record_id"]');
    if (!editInput) {
        editInput = document.createElement('input');
        editInput.type = 'hidden';
        editInput.name = 'edit_record_id';
        document.querySelector('form').appendChild(editInput);
    }
    editInput.value = recordId;

    // Change button text
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Update Dental Record';

    // Scroll to form
    document.querySelector('.activity-section').scrollIntoView({ behavior: 'smooth' });

    // Show success message
    alert('Form populated with record data. Please make your changes and click "Update Dental Record".');
}
</script>

<?php include("../includes/footer.php"); ?>
