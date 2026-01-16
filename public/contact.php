<?php include("../includes/header.php"); ?>

<!-- Contact Header -->
<div class="contact-header">
  <h1>Contact Us</h1>
  <p>Get in touch with Dents-City for all your dental care needs</p>
</div>

<!-- Contact Content -->
<section class="contact-section">
  <div class="contact-container">
    <!-- Contact Information -->
    <div class="contact-info">
      <h2>Get In Touch</h2>

      <div class="contact-details">
        <div class="contact-item">
          <div class="contact-icon">📍</div>
          <div>
            <h3>Address</h3>
            <p>Porta Vaga, Baguio City<br>Philippines</p>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon">📞</div>
          <div>
            <h3>Phone</h3>
            <p>0998-569-6657</p>
            <p>0917-555-2677</p>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon">✉️</div>
          <div>
            <h3>Email</h3>
            <p>essentialssmilecitydental@gmail.com</p>
            <p>appointments@dents-city.com</p>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon">🕒</div>
          <div>
            <h3>Hours</h3>
            <p>Monday - Friday: 8:00 AM - 6:00 PM</p>
            <p>Saturday: 8:00 AM - 4:00 PM</p>
            <p>Sunday: Emergency Only</p>
          </div>
        </div>
      </div>

      <!-- Emergency Contact -->
      <div class="emergency-contact">
        <h3>🚨 Emergency Dental Care</h3>
        <p>For dental emergencies outside regular hours, call our 24/7 emergency line:</p>
        <p class="emergency-number">0998-569-66577</p>
      </div>
    </div>

    <!-- Contact Form -->
    <div class="contact-form-container">
      <h2>Send Us a Message</h2>

      <?php
      $messageSent = false;
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // In a real application, you'd process the form data here
        // For now, we'll just show a success message
        $name = htmlspecialchars($_POST['name'] ?? '');
        $email = htmlspecialchars($_POST['email'] ?? '');
        $message = htmlspecialchars($_POST['message'] ?? '');

        // Basic validation
        if (!empty($name) && !empty($email) && !empty($message)) {
          $messageSent = true;
          // Here you would typically send an email or save to database
        }
      }
      ?>

      <?php if ($messageSent): ?>
        <div class="success-message">
          <p>Thank you for your message! We'll get back to you within 24 hours.</p>
        </div>
      <?php else: ?>
        <form method="post" action="#" class="contact-form">
          <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name" placeholder="Your full name" required />
          </div>

          <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" placeholder="your.email@example.com" required />
          </div>

          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="+63 (123) 456-7890" />
          </div>

          <div class="form-group">
            <label for="subject">Subject</label>
            <select id="subject" name="subject">
              <option value="">Select a subject</option>
              <option value="appointment">Book Appointment</option>
              <option value="consultation">General Consultation</option>
              <option value="emergency">Emergency</option>
              <option value="billing">Billing Question</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="form-group">
            <label for="message">Message *</label>
            <textarea id="message" name="message" placeholder="Please describe how we can help you..." required rows="6"></textarea>
          </div>

          <button type="submit" class="btn-primary">Send Message</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Map Section (Placeholder) -->
<section class="map-section">
  <h2>Find Us</h2>
  <div class="map-placeholder">
    <div class="map-icon">🗺️</div>
    <p>Interactive map would be embedded here showing our location in Porta Vaga, Baguio City, Philippines.</p>
    <p><strong>Directions:</strong> Located in the heart of Baguio City, easily accessible by public transportation.</p>
  </div>
</section>

<!-- Call to Action -->
<div class="contact-cta">
  <h2>Ready to Visit Us?</h2>
  <p>Book your appointment today and take the first step towards a healthier smile.</p>
  <div class="hero-buttons">
    <a href="<?php echo $base_url; ?>/public/register.php" class="btn-primary">Book Appointment</a>
    <a href="tel:+631234567890" class="btn-secondary">Call Now</a>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
