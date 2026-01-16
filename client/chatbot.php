<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/appointments.php";

$userId = $_SESSION['user_id'];

// Get user's appointments for context
$appointments = listClientAppointments($userId);
$upcomingAppointments = array_filter($appointments, function($appt) {
    return strtotime($appt['date']) >= strtotime(date('Y-m-d'));
});
$recentAppointments = array_filter($appointments, function($appt) {
    return strtotime($appt['date']) < strtotime(date('Y-m-d'));
});
?>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1><img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="AI" class="header-icon"> AI Appointment Assistant</h1>
            <p class="page-subtitle">Get instant answers about your dental care and appointments</p>
        </div>
        <div class="header-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo count($upcomingAppointments); ?></span>
                <span class="stat-label">Upcoming</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($recentAppointments); ?></span>
                <span class="stat-label">Completed</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($appointments); ?></span>
                <span class="stat-label">Total Visits</span>
        </div>
    </div>

        <!-- Sidebar with Appointment Info -->
        <div class="sidebar-section">
            <div class="sidebar-widget">
                <h3><img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Appointments" class="section-icon-small"> Your Appointments</h3>

                <?php if (!empty($upcomingAppointments)): ?>
                    <div class="upcoming-appointments">
                        <h4>Upcoming</h4>
                        <?php foreach (array_slice($upcomingAppointments, 0, 3) as $appt): ?>
                            <div class="appointment-item">
                                <div class="appointment-date">
                                    <span class="day"><?php echo date('j', strtotime($appt['date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($appt['date'])); ?></span>
                                </div>
                                <div class="appointment-details">
                                    <div class="appointment-time"><?php echo date('g:i A', strtotime($appt['time'])); ?></div>
                                    <div class="appointment-status <?php echo $appt['status']; ?>"><?php echo ucfirst($appt['status']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($recentAppointments)): ?>
                    <div class="recent-appointments">
                        <h4>Recent</h4>
                        <?php foreach (array_slice(array_reverse($recentAppointments), 0, 2) as $appt): ?>
                            <div class="appointment-item past">
                                <div class="appointment-date">
                                    <span class="day"><?php echo date('j', strtotime($appt['date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($appt['date'])); ?></span>
                                </div>
                                <div class="appointment-details">
                                    <div class="appointment-time"><?php echo date('M j', strtotime($appt['date'])); ?></div>
                                    <div class="appointment-status completed">Completed</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="quick-actions">
                    <a href="appointments.php" class="action-btn">
                        <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="View">
                        View All Appointments
                    </a>
                    <a href="profile.php" class="action-btn">
                        <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Profile">
                        Update Profile
                    </a>
                </div>
            </div>

            <div class="sidebar-widget">
                <h3><img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Help" class="section-icon-small"> Quick Help</h3>
                <div class="help-section">
                    <div class="help-item">
                        <h5>💬 AI Assistant</h5>
                        <p>Ask me anything about dental care, appointments, or clinic services.</p>
                    </div>
                    <div class="help-item">
                        <h5>📞 Emergency?</h5>
                        <p>For dental emergencies, call our clinic directly at (555) 123-4567.</p>
                    </div>
                    <div class="help-item">
                        <h5>🕒 Office Hours</h5>
                        <p>Mon-Fri: 9AM-6PM<br>Sat: 9AM-2PM<br>Sun: Closed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="content-grid">
        <!-- Main Chat Section -->
        <div class="main-chat-section">
            <div class="chat-container">
        <div class="chat-messages" id="chatMessages">
            <!-- Initial bot message -->
            <div class="message bot-message">
                <div class="message-avatar">
                    <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Bot">
                </div>
                <div class="message-content">
                    <p>Hello! I'm your appointment assistant. I can help you with questions about scheduling, services, and your appointments. How can I assist you today?</p>
                    <small><?php echo date('H:i'); ?></small>
                </div>
            </div>
        </div>

        <div class="quick-questions">
            <!-- Dental-specific questions -->
            <button class="quick-btn" onclick="sendQuickMessage('How do I book an appointment?')">📅 Book Appointment</button>
            <button class="quick-btn" onclick="sendQuickMessage('What services do you offer?')">🦷 Our Services</button>
            <button class="quick-btn" onclick="sendQuickMessage('What are your clinic hours?')">🕒 Clinic Hours</button>
            <button class="quick-btn" onclick="sendQuickMessage('Do you accept insurance?')">💳 Insurance Info</button>

        </div>

        <div class="chat-input">
            <input type="text" id="messageInput" placeholder="Type your message..." onkeypress="handleKeyPress(event)">
            <button onclick="startVoiceInput()" id="voiceButton" title="Voice Input">🎤</button>
            <button onclick="sendMessage()" id="sendButton">Send</button>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #0ea5e9;
    --primary-light: #38bdf8;
    --primary-dark: #0284c7;
    --secondary: #f59e0b;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --text-muted: #9ca3af;
    --bg-light: #f8fafc;
    --bg-white: #ffffff;
    --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --border: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
    --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 24px;
}

* {
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    color: white;
}

.header-content h1 {
    font-size: 3rem;
    font-weight: 800;
    margin: 0 0 15px 0;
    background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.header-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.page-subtitle {
    font-size: 1.3rem;
    opacity: 0.9;
    margin: 0;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.header-stats {
    display: flex;
    gap: 40px;
    margin-top: 30px;
    justify-content: center;
}

.stat-box {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-lg);
    padding: 25px 30px;
    text-align: center;
    min-width: 140px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.stat-number {
    display: block;
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 8px;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.stat-label {
    font-size: 1.1rem;
    opacity: 0.9;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: white;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.main-chat-section {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
}

.chat-container {
    display: flex;
    flex-direction: column;
    height: 700px;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 30px 25px;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    scroll-behavior: smooth;
    position: relative;
}

.chat-messages::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border-light), transparent);
}

.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.message {
    display: flex;
    margin-bottom: 24px;
    align-items: flex-start;
    gap: 16px;
    animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.message:last-child {
    margin-bottom: 0;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.message-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
    border: 3px solid rgba(255, 255, 255, 0.8);
    transition: all 0.3s ease;
}

.message-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 25px rgba(14, 165, 233, 0.4);
}

.message-avatar img {
    width: 26px;
    height: 26px;
    filter: brightness(0) invert(1);
}

.message-content {
    flex: 1;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    max-width: 75%;
    border: 1px solid rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    position: relative;
    transition: all 0.3s ease;
}

.message-content::before {
    content: '';
    position: absolute;
    top: 16px;
    width: 0;
    height: 0;
    border: 8px solid transparent;
}

.bot-message .message-content::before {
    left: -8px;
    border-right-color: white;
    border-left: 0;
}

.user-message .message-content::before {
    right: -8px;
    border-left-color: var(--success);
    border-right: 0;
}

.bot-message .message-content {
    background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
    color: var(--text-dark);
    border: 1px solid rgba(14, 165, 233, 0.2);
    box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
}

.bot-message .message-content:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 35px rgba(14, 165, 233, 0.2);
}

.user-message {
    flex-direction: row-reverse;
}

.user-message .message-content {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
    text-align: right;
    border: 1px solid rgba(16, 185, 129, 0.3);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.2);
}

.user-message .message-content:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 35px rgba(16, 185, 129, 0.25);
}

.message-content p {
    margin: 0 0 8px 0;
    line-height: 1.6;
    word-wrap: break-word;
    font-size: 1rem;
}

.message-content p:last-child {
    margin-bottom: 0;
}

.message-content small {
    font-size: 0.8rem;
    opacity: 0.7;
    display: block;
    margin-top: 8px;
    font-weight: 500;
}

.quick-questions {
    padding: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    position: relative;
}

.quick-questions::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
}

.quick-btn {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(14, 165, 233, 0.2);
    padding: 12px 20px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-dark);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.quick-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.1), transparent);
    transition: left 0.5s;
}

.quick-btn:hover::before {
    left: 100%;
}

.quick-btn:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-color: var(--primary);
    color: white;
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
}

.quick-btn:active {
    transform: translateY(-1px) scale(0.98);
}

.chat-input {
    display: flex;
    padding: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    gap: 16px;
    align-items: center;
    position: relative;
}

.chat-input::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
}

.chat-input input {
    flex: 1;
    padding: 16px 24px;
    border: 2px solid rgba(14, 165, 233, 0.2);
    border-radius: 30px;
    outline: none;
    font-size: 1.1rem;
    color: var(--text-dark);
    background: rgba(255, 255, 255, 0.9);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: inherit;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.chat-input input::placeholder {
    color: var(--text-muted);
    font-weight: 400;
}

.chat-input input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15), 0 8px 25px rgba(14, 165, 233, 0.2);
    background: white;
    transform: translateY(-1px);
}

.chat-input button {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

#voiceButton {
    background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
    padding: 16px 20px;
    font-size: 1.2rem;
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

#voiceButton:hover {
    background: linear-gradient(135deg, #f59e0b 0%, var(--warning) 100%);
    box-shadow: 0 12px 35px rgba(245, 158, 11, 0.4);
}

#voiceButton.listening {
    background: linear-gradient(135deg, var(--error) 0%, #f87171 100%);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.chat-input button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.chat-input button:hover::before {
    left: 100%;
}

.chat-input button:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 35px rgba(14, 165, 233, 0.4);
}

.chat-input button:active {
    transform: translateY(-1px) scale(0.98);
}

.typing-indicator {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    color: var(--text-muted);
    font-style: italic;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.typing-dots {
    display: flex;
    gap: 8px;
}

.typing-dots span {
    width: 10px;
    height: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    animation: typing 1.4s infinite;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    30% {
        transform: translateY(-8px) scale(1.1);
        opacity: 0.8;
    }
}

/* Page Header */
.page-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 12px;
    color: white;
    min-height: 120px;
    text-align: center;
}

.header-content h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    font-weight: 700;
}

.header-icon {
    width: 40px;
    height: 40px;
}

.page-subtitle {
    margin: 0;
    opacity: 0.95;
    font-size: 1.3rem;
    font-weight: 400;
    max-width: 600px;
    line-height: 1.4;
}

.header-stats {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    justify-content: center;
}

.stat-box {
    text-align: center;
    padding: 20px 25px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 120px;
}

.stat-number {
    display: block;
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.main-chat-section, .sidebar-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

/* Sidebar */
.sidebar-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-widget {
    background: #f8fafc;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.sidebar-widget h3 {
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

.upcoming-appointments h4, .recent-appointments h4 {
    margin: 15px 0 10px 0;
    font-size: 1rem;
    color: #6b7280;
    font-weight: 600;
}

.appointment-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    margin-bottom: 8px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
}

.appointment-item:hover {
    border-color: #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.appointment-item.past {
    opacity: 0.7;
}

.appointment-date {
    text-align: center;
    min-width: 50px;
}

.appointment-date .day {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #10b981;
    line-height: 1;
}

.appointment-date .month {
    display: block;
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}

.appointment-details {
    flex: 1;
}

.appointment-time {
    font-weight: 600;
    color: #374151;
    margin-bottom: 2px;
}

.appointment-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.appointment-status.pending {
    background: #fef3c7;
    color: #d97706;
}

.appointment-status.confirmed {
    background: #dbeafe;
    color: #2563eb;
}

.appointment-status.completed {
    background: #d1fae5;
    color: #065f46;
}

.quick-actions {
    margin-top: 20px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: #10b981;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
    margin-bottom: 8px;
}

.action-btn:hover {
    background: #059669;
    transform: translateY(-1px);
}

.action-btn img {
    width: 16px;
    height: 16px;
}

.help-section {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.help-item {
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.help-item h5 {
    margin: 0 0 8px 0;
    font-size: 1rem;
    color: #374151;
}

.help-item p {
    margin: 0;
    font-size: 0.9rem;
    color: #6b7280;
    line-height: 1.4;
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

    .content-grid {
        grid-template-columns: 1fr;
    }

    .sidebar-section {
        order: -1;
    }

    h1 {
        font-size: 2rem;
    }

    .message-content {
        max-width: 85%;
    }

    .chat-container {
        border-radius: 12px;
        height: 700px;
    }

    .chat-messages {
        padding: 20px 15px;
    }

    .chat-input {
        padding: 15px;
        flex-direction: column;
    }

    .chat-input button {
        width: 100%;
    }

    .quick-questions {
        padding: 15px;
    }

    .sidebar-widget {
        padding: 15px;
    }

    .appointment-item {
        padding: 10px;
    }
}
</style>

<script>
let chatMessages = document.getElementById('chatMessages');
let messageInput = document.getElementById('messageInput');

// Auto-scroll to bottom
function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Add message to chat
function addMessage(content, isUser = false, timestamp = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isUser ? 'user-message' : 'bot-message'}`;

    const avatarDiv = document.createElement('div');
    avatarDiv.className = 'message-avatar';

    const avatarImg = document.createElement('img');
    avatarImg.src = isUser ? '<?php echo $base_url; ?>/assets/images/patient_icon.svg' : '<?php echo $base_url; ?>/assets/images/info_icon.svg';
    avatarImg.alt = isUser ? 'You' : 'Bot';
    avatarDiv.appendChild(avatarImg);

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';

    const messageP = document.createElement('p');
    messageP.textContent = content;
    contentDiv.appendChild(messageP);

    const timeSmall = document.createElement('small');
    timeSmall.textContent = timestamp || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    contentDiv.appendChild(timeSmall);

    messageDiv.appendChild(avatarDiv);
    messageDiv.appendChild(contentDiv);

    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

// Show typing indicator
function showTyping() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot-message typing-indicator';
    typingDiv.id = 'typingIndicator';

    const avatarDiv = document.createElement('div');
    avatarDiv.className = 'message-avatar';
    const avatarImg = document.createElement('img');
    avatarImg.src = '<?php echo $base_url; ?>/assets/images/info_icon.svg';
    avatarImg.alt = 'Bot';
    avatarDiv.appendChild(avatarImg);

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.innerHTML = '<em>Typing...</em><div class="typing-dots"><span></span><span></span><span></span></div>';

    typingDiv.appendChild(avatarDiv);
    typingDiv.appendChild(contentDiv);

    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}
// Hide typing indicator
function hideTyping() {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

// Send message (calls server API)
async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message) return;

    addMessage(message, true);
    messageInput.value = '';

    showTyping();

    const startTime = Date.now();

    try {
        const res = await fetch('../api/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        });

        const data = await res.json();
        const elapsed = Date.now() - startTime;

        // Ensure thinking animation shows for at least 1.5-3 seconds
        const minThinkingTime = 1500 + Math.random() * 1500; // 1.5-3 seconds
        const remainingTime = Math.max(0, minThinkingTime - elapsed);

        setTimeout(() => {
            hideTyping();
            if (data && data.success) {
                addMessage(data.reply, false);
            } else {
                addMessage(data.error || 'Sorry, something went wrong with the chatbot.', false);
            }
        }, remainingTime);

    } catch (err) {
        hideTyping();
        addMessage('Sorry, unable to reach the chatbot. Please try again later.', false);
        console.error('Chatbot error:', err);
    }
}

// Send quick message
function sendQuickMessage(message) {
    messageInput.value = message;
    sendMessage();
}

// Handle enter key
function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Voice input functionality
let recognition = null;
let isListening = false;

function startVoiceInput() {
    const voiceButton = document.getElementById('voiceButton');

    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        addMessage('Voice input is not supported in your browser. Please use a modern browser like Chrome.', false);
        return;
    }

    if (isListening) {
        stopVoiceInput();
        return;
    }

    // Initialize speech recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US'; // You can make this configurable

    recognition.onstart = function() {
        isListening = true;
        voiceButton.classList.add('listening');
        voiceButton.textContent = '🎙️';
        addMessage('Listening... Speak now or click the microphone again to stop.', false);
    };

    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        messageInput.value = transcript;
        addMessage(`Voice input: "${transcript}"`, false);
        stopVoiceInput();
        // Optionally auto-send the message
        // sendMessage();
    };

    recognition.onerror = function(event) {
        console.error('Speech recognition error:', event.error);
        addMessage('Voice recognition failed. Please try again or type your message.', false);
        stopVoiceInput();
    };

    recognition.onend = function() {
        stopVoiceInput();
    };

    try {
        recognition.start();
    } catch (error) {
        console.error('Error starting speech recognition:', error);
        addMessage('Unable to start voice input. Please try again.', false);
    }
}

function stopVoiceInput() {
    const voiceButton = document.getElementById('voiceButton');

    if (recognition && isListening) {
        recognition.stop();
    }

    isListening = false;
    voiceButton.classList.remove('listening');
    voiceButton.textContent = '🎤';
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    messageInput.focus();
});
</script>

<?php include("../includes/footer.php"); ?>
