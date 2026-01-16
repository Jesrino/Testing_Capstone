<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/users.php";

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $profileData = [
    'phone' => $_POST['phone'] ?? '',
    'address' => $_POST['address'] ?? '',
    'dateOfBirth' => $_POST['dateOfBirth'] ?? '',
    'gender' => $_POST['gender'] ?? '',
    'emergencyContact' => $_POST['emergencyContact'] ?? '',
    'emergencyPhone' => $_POST['emergencyPhone'] ?? '',
    'medicalHistory' => $_POST['medicalHistory'] ?? '',
    'allergies' => $_POST['allergies'] ?? '',
    'currentMedications' => $_POST['currentMedications'] ?? ''
  ];

  updateUserProfile($userId, $profileData);
  $message = 'Profile updated successfully!';
  $user = getUserById($userId); // Refresh user data
}
?>

<!-- Enhanced Profile Header -->
<div class="profile-header">
  <div class="header-gradient">
    <div class="header-content">
      <div class="welcome-section">
        <h1><img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Profile" class="header-icon"> My Profile</h1>
        <p>Manage your personal information and medical details</p>
        <div class="profile-status">
          <div class="status-item">
            <span class="status-label">Account Status:</span>
            <span class="status-value active">Active Patient</span>
          </div>
          <div class="status-item">
            <span class="status-label">Last Updated:</span>
            <span class="status-value"><?php echo date('M d, Y', strtotime($user['updatedAt'] ?? 'now')); ?></span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div class="quick-stats">
          <div class="stat-mini">
            <span class="stat-number"><?php
              global $pdo;
              $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Appointments WHERE clientId = ?");
              $stmt->execute([$userId]);
              echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            ?></span>
            <span class="stat-label">Total Visits</span>
          </div>
          <div class="stat-mini">
            <span class="stat-number"><?php
              $stmt = $pdo->prepare("SELECT COUNT(*) as upcoming FROM Appointments WHERE clientId = ? AND date >= CURDATE() AND status IN ('pending', 'confirmed')");
              $stmt->execute([$userId]);
              echo $stmt->fetch(PDO::FETCH_ASSOC)['upcoming'];
            ?></span>
            <span class="stat-label">Upcoming</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($message): ?>
  <div class="success-message">
    <div class="message-icon">✓</div>
    <div class="message-content">
      <h4>Profile Updated Successfully!</h4>
      <p><?php echo htmlspecialchars($message); ?></p>
    </div>
    <button class="message-close" onclick="this.parentElement.style.display='none'">×</button>
  </div>
<?php endif; ?>

<div class="profile-container">
  <!-- Profile Overview Card -->
  <div class="profile-overview-card">
    <div class="profile-avatar-section">
      <div class="avatar-container">
        <img src="<?php echo $base_url; ?>/assets/images/patient_icon.svg" alt="Profile Picture" class="profile-avatar">
        <div class="avatar-overlay">
          <button class="change-photo-btn" type="button">
            <img src="<?php echo $base_url; ?>/assets/images/upload_icon.png" alt="Upload" style="width: 20px; height: 20px;">
          </button>
        </div>
      </div>
      <div class="avatar-info">
        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
        <p><?php echo htmlspecialchars($user['email']); ?></p>
        <div class="profile-badges">
          <span class="badge badge-patient">Patient</span>
          <span class="badge badge-verified">Verified</span>
        </div>
      </div>
    </div>
    <div class="profile-quick-info">
      <div class="info-item">
        <div class="info-icon">
          <img src="<?php echo $base_url; ?>/assets/images/home_icon.svg" alt="Phone">
        </div>
        <div class="info-content">
          <span class="info-label">Phone</span>
          <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
        </div>
      </div>
      <div class="info-item">
        <div class="info-icon">
          <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="DOB">
        </div>
        <div class="info-content">
          <span class="info-label">Date of Birth</span>
          <span class="info-value"><?php echo $user['dateOfBirth'] ? date('M d, Y', strtotime($user['dateOfBirth'])) : 'Not provided'; ?></span>
        </div>
      </div>
      <div class="info-item">
        <div class="info-icon">
          <img src="<?php echo $base_url; ?>/assets/images/people_icon.svg" alt="Gender">
        </div>
        <div class="info-content">
          <span class="info-label">Gender</span>
          <span class="info-value"><?php echo ucfirst($user['gender'] ?? 'Not specified'); ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile Form -->
  <form method="POST" class="profile-form">
    <!-- Personal Information Section -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="card-icon">
          <img src="<?php echo $base_url; ?>/assets/images/people_icon.svg" alt="Personal">
        </div>
        <div class="card-title">
          <h3>Personal Information</h3>
          <p>Basic details about yourself</p>
        </div>
      </div>

      <div class="form-card-content">
        <div class="form-grid">
          <div class="form-field">
            <label for="phone" class="field-label">
              <span class="label-icon">📱</span>
              Phone Number
            </label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+63 (123) 456-7890" class="field-input">
            <span class="field-hint">Used for appointment reminders</span>
          </div>

          <div class="form-field">
            <label for="dateOfBirth" class="field-label">
              <span class="label-icon">🎂</span>
              Date of Birth
            </label>
            <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($user['dateOfBirth'] ?? ''); ?>" class="field-input">
            <span class="field-hint">Required for medical records</span>
          </div>

          <div class="form-field">
            <label for="gender" class="field-label">
              <span class="label-icon">⚧</span>
              Gender
            </label>
            <select id="gender" name="gender" class="field-select">
              <option value="">Select Gender</option>
              <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
              <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
              <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>

          <div class="form-field form-field-full">
            <label for="address" class="field-label">
              <span class="label-icon">🏠</span>
              Home Address
            </label>
            <textarea id="address" name="address" rows="3" placeholder="Enter your full address" class="field-textarea"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            <span class="field-hint">Complete address for billing and records</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Emergency Contact Section -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="card-icon">
          <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Emergency">
        </div>
        <div class="card-title">
          <h3>Emergency Contact</h3>
          <p>Who to contact in case of emergency</p>
        </div>
      </div>

      <div class="form-card-content">
        <div class="form-grid">
          <div class="form-field">
            <label for="emergencyContact" class="field-label">
              <span class="label-icon">👤</span>
              Contact Name
            </label>
            <input type="text" id="emergencyContact" name="emergencyContact" value="<?php echo htmlspecialchars($user['emergencyContact'] ?? ''); ?>" placeholder="Full name of emergency contact" class="field-input">
          </div>

          <div class="form-field">
            <label for="emergencyPhone" class="field-label">
              <span class="label-icon">📞</span>
              Contact Phone
            </label>
            <input type="tel" id="emergencyPhone" name="emergencyPhone" value="<?php echo htmlspecialchars($user['emergencyPhone'] ?? ''); ?>" placeholder="+63 (123) 456-7890" class="field-input">
          </div>
        </div>
      </div>
    </div>

    <!-- Medical Information Section -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="card-icon">
          <img src="<?php echo $base_url; ?>/assets/images/doctor_icon.svg" alt="Medical">
        </div>
        <div class="card-title">
          <h3>Medical Information</h3>
          <p>Important health details for your dental care</p>
        </div>
      </div>

      <div class="form-card-content">
        <div class="medical-alert">
          <div class="alert-icon">⚠️</div>
          <div class="alert-content">
            <h4>Important Medical Information</h4>
            <p>Please provide accurate medical information to ensure safe and effective dental treatment.</p>
          </div>
        </div>

        <div class="form-field form-field-full">
          <label for="medicalHistory" class="field-label">
            <span class="label-icon">📋</span>
            Medical History
          </label>
          <textarea id="medicalHistory" name="medicalHistory" rows="4" placeholder="Please describe any relevant medical history, past surgeries, or conditions (e.g., heart conditions, diabetes, etc.)" class="field-textarea"><?php echo htmlspecialchars($user['medicalHistory'] ?? ''); ?></textarea>
          <span class="field-hint">Include any conditions that may affect dental treatment</span>
        </div>

        <div class="form-field form-field-full">
          <label for="allergies" class="field-label">
            <span class="label-icon">🚫</span>
            Allergies
          </label>
          <textarea id="allergies" name="allergies" rows="3" placeholder="List any known allergies (medications, latex, anesthetics, metals, etc.)" class="field-textarea"><?php echo htmlspecialchars($user['allergies'] ?? ''); ?></textarea>
          <span class="field-hint">Critical for safe treatment - include severity and reactions</span>
        </div>

        <div class="form-field form-field-full">
          <label for="currentMedications" class="field-label">
            <span class="label-icon">💊</span>
            Current Medications
          </label>
          <textarea id="currentMedications" name="currentMedications" rows="3" placeholder="List all medications you are currently taking (including dosage and frequency)" class="field-textarea"><?php echo htmlspecialchars($user['currentMedications'] ?? ''); ?></textarea>
          <span class="field-hint">Include over-the-counter medications and supplements</span>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-card">
      <div class="actions-content">
        <div class="actions-info">
          <h4>Save Your Changes</h4>
          <p>Review your information before saving. Changes will be reflected immediately.</p>
        </div>
        <div class="actions-buttons">
          <button type="submit" class="btn-primary">
            <span class="btn-icon">💾</span>
            Update Profile
          </button>
          <a href="<?php echo $base_url; ?>/client/dashboard.php" class="btn-secondary">
            <span class="btn-icon">←</span>
            Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
/* Enhanced Profile Header */
.profile-header {
  margin-bottom: 30px;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-gradient {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  position: relative;
}

.header-gradient::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="50" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="30" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
  opacity: 0.3;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 40px;
  position: relative;
  z-index: 1;
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

.profile-status {
  display: flex;
  gap: 20px;
  margin-top: 15px;
}

.status-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.status-label {
  font-size: 0.85rem;
  opacity: 0.8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-value {
  font-size: 1rem;
  font-weight: 600;
}

.status-value.active {
  color: #d1fae5;
}

.header-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 20px;
}

.quick-stats {
  display: flex;
  gap: 20px;
}

.stat-mini {
  text-align: center;
  padding: 15px 20px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-number {
  display: block;
  font-size: 2rem;
  font-weight: bold;
  color: white;
  margin-bottom: 4px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.stat-mini .stat-label {
  font-size: 0.85rem;
  opacity: 0.9;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: white;
}

/* Success Message */
.success-message {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  border: 1px solid #6ee7b7;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 30px;
  box-shadow: 0 4px 12px rgba(110, 231, 183, 0.2);
  position: relative;
}

.message-icon {
  font-size: 2rem;
  color: #065f46;
  flex-shrink: 0;
}

.message-content h4 {
  margin: 0 0 8px 0;
  color: #065f46;
  font-size: 1.2rem;
  font-weight: 600;
}

.message-content p {
  margin: 0;
  color: #065f46;
  font-weight: 500;
}

.message-close {
  position: absolute;
  top: 12px;
  right: 12px;
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #065f46;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.3s ease;
}

.message-close:hover {
  opacity: 1;
}

/* Profile Overview Card */
.profile-overview-card {
  background: white;
  border-radius: 16px;
  padding: 30px;
  margin-bottom: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.profile-avatar-section {
  display: flex;
  align-items: center;
  gap: 30px;
  margin-bottom: 30px;
}

.avatar-container {
  position: relative;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  border: 4px solid #10b981;
}

.profile-avatar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(16, 185, 129, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.avatar-container:hover .avatar-overlay {
  opacity: 1;
}

.change-photo-btn {
  background: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  transition: transform 0.3s ease;
}

.change-photo-btn:hover {
  transform: scale(1.1);
}

.avatar-info h2 {
  margin: 0 0 8px 0;
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
}

.avatar-info p {
  margin: 0 0 16px 0;
  font-size: 1.1rem;
  color: #6b7280;
  font-weight: 500;
}

.profile-badges {
  display: flex;
  gap: 12px;
}

.badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-patient {
  background: #dbeafe;
  color: #1e40af;
}

.badge-verified {
  background: #d1fae5;
  color: #065f46;
}

.profile-quick-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.info-item:hover {
  background: #f1f5f9;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.info-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.info-icon img {
  width: 24px;
  height: 24px;
  filter: brightness(0) invert(1);
}

.info-content {
  flex: 1;
}

.info-label {
  display: block;
  font-size: 0.9rem;
  color: #6b7280;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  display: block;
  font-size: 1.1rem;
  color: #1f2937;
  font-weight: 600;
}

/* Form Cards */
.form-card {
  background: white;
  border-radius: 16px;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.form-card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px 30px;
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border-bottom: 1px solid #e5e7eb;
}

.card-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.card-icon img {
  width: 24px;
  height: 24px;
  filter: brightness(0) invert(1);
}

.card-title h3 {
  margin: 0 0 4px 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.card-title p {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
}

.form-card-content {
  padding: 30px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field-full {
  grid-column: 1 / -1;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.label-icon {
  font-size: 1.1rem;
}

.field-input,
.field-select,
.field-textarea {
  padding: 12px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 1rem;
  font-family: inherit;
  transition: all 0.3s ease;
  background: white;
}

.field-input:focus,
.field-select:focus,
.field-textarea:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.field-textarea {
  resize: vertical;
  min-height: 80px;
}

.field-hint {
  font-size: 0.85rem;
  color: #6b7280;
  font-style: italic;
  margin-top: 4px;
}

/* Medical Alert */
.medical-alert {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 1px solid #f59e0b;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.alert-icon {
  font-size: 1.5rem;
  color: #92400e;
  flex-shrink: 0;
}

.alert-content h4 {
  margin: 0 0 8px 0;
  color: #92400e;
  font-size: 1.1rem;
  font-weight: 600;
}

.alert-content p {
  margin: 0;
  color: #92400e;
  font-weight: 500;
}

/* Form Actions */
.form-actions-card {
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.actions-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 30px;
}

.actions-info h4 {
  margin: 0 0 8px 0;
  font-size: 1.3rem;
  font-weight: 700;
  color: #1f2937;
}

.actions-info p {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
}

.actions-buttons {
  display: flex;
  gap: 16px;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
  background: linear-gradient(135deg, #059669, #047857);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  background: white;
  color: #6b7280;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #374151;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-icon {
  font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .header-content {
    flex-direction: column;
    text-align: center;
    gap: 30px;
  }

  .profile-status {
    justify-content: center;
  }

  .header-actions {
    align-items: center;
  }

  .actions-content {
    flex-direction: column;
    text-align: center;
    gap: 24px;
  }

  .actions-buttons {
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .welcome-section h1 {
    font-size: 2.2rem;
  }

  .profile-avatar-section {
    flex-direction: column;
    text-align: center;
    gap: 20px;
  }

  .profile-quick-info {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-card-header {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }

  .actions-content {
    flex-direction: column;
    gap: 20px;
  }

  .actions-buttons {
    flex-direction: column;
  }

  .btn-primary,
  .btn-secondary {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .header-content {
    padding: 30px 20px;
  }

  .welcome-section h1 {
    font-size: 1.8rem;
  }

  .profile-overview-card,
  .form-card,
  .form-actions-card {
    padding: 20px;
  }

  .avatar-container {
    width: 100px;
    height: 100px;
  }

  .avatar-info h2 {
    font-size: 1.6rem;
  }

  .info-item {
    padding: 16px;
  }

  .form-card-content {
    padding: 20px;
  }
}
</style>



