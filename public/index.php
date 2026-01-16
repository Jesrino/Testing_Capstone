<?php include("../includes/header.php"); ?>

<!-- Hero Section -->
<div class="hero-section">
  <h1>Welcome to Dents-City</h1>
  <p>Your trusted partner for comprehensive dental care and beautiful smiles</p>
  <div class="hero-buttons">
    <a href="<?php echo $base_url; ?>/public/login.php" class="btn-primary">Book Appointment</a>
    <a href="<?php echo $base_url; ?>/public/about.php" class="btn-secondary">Learn More</a>
  </div>
</div>

<!-- Features Section -->
<section class="section">
  <h2 class="section-title">Why Choose Dents-City?</h2>
  <p class="section-subtitle">We provide exceptional dental care with modern technology and experienced professionals</p>
  
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">👨‍⚕️</div>
      <h3>Expert Dentists</h3>
      <p>Our team consists of highly qualified and experienced dental professionals dedicated to your oral health.</p>
    </div>
    
    <div class="feature-card">
      <div class="feature-icon">🏥</div>
      <h3>Modern Equipment</h3>
      <p>We use state-of-the-art technology and equipment to ensure the best treatment outcomes.</p>
    </div>
    
    <div class="feature-card">
      <div class="feature-icon">📅</div>
      <h3>Flexible Scheduling</h3>
      <p>Book appointments at your convenience with our easy online scheduling system.</p>
    </div>
    
    <div class="feature-card">
      <div class="feature-icon">💰</div>
      <h3>Affordable Prices</h3>
      <p>Quality dental care at competitive prices with flexible payment options available.</p>
    </div>
  </div>
</section>

<!-- Services Section -->
<section class="section" style="background: #f8fafc;">
  <h2 class="section-title">Our Services</h2>
  <p class="section-subtitle">Comprehensive dental solutions for all your oral health needs</p>
  
  <div class="services-grid">
    <a href="<?php echo $base_url; ?>/public/services.php#dental-cleaning" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/DentalCleaning.svg" alt="Dental Cleaning">
        </div>
        <h3>Dental Cleaning</h3>
        <p>Professional teeth cleaning to maintain optimal oral hygiene and prevent dental issues.</p>
      </div>
    </a>

    <a href="<?php echo $base_url; ?>/public/services.php#braces-orthodontics" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/DentalBraces.svg" alt="Dental Braces">
        </div>
        <h3>Braces & Orthodontics</h3>
        <p>Straighten your teeth and improve your smile with our orthodontic treatments.</p>
      </div>
    </a>

    <a href="<?php echo $base_url; ?>/public/services.php#dental-implants" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/DentalImplant.svg" alt="Dental Implants">
        </div>
        <h3>Dental Implants</h3>
        <p>Permanent solution for missing teeth with natural-looking dental implants.</p>
      </div>
    </a>

    <a href="<?php echo $base_url; ?>/public/services.php#tooth-fillings" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/ToothFillings.svg" alt="Tooth Fillings">
        </div>
        <h3>Tooth Fillings</h3>
        <p>Restore damaged teeth with durable and aesthetic filling materials.</p>
      </div>
    </a>

    <a href="<?php echo $base_url; ?>/public/services.php#tooth-extraction" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/ToothExtraction.svg" alt="Tooth Extraction">
        </div>
        <h3>Tooth Extraction</h3>
        <p>Safe and painless tooth removal procedures when necessary.</p>
      </div>
    </a>

    <a href="<?php echo $base_url; ?>/public/services.php#removable-dentures" class="service-card-link">
      <div class="service-card">
        <div class="service-icon">
          <img src="<?php echo $base_url; ?>/assets/images/RemovabaleDentures.svg" alt="Removable Dentures">
        </div>
        <h3>Removable Dentures</h3>
        <p>Custom-fitted dentures for a comfortable and natural-looking smile.</p>
      </div>
    </a>
  </div>
  
  <div style="text-align: center;">
    <a href="<?php echo $base_url; ?>/public/services.php" class="view-all-link">View All Services →</a>
  </div>
</section>

<!-- How It Works Section -->
<section class="section">
  <h2 class="section-title">How It Works</h2>
  <p class="section-subtitle">Getting started with Dents-City is easy and straightforward</p>
  
  <div class="steps-container">
    <div class="step-item">
      <div class="step-number">1</div>
      <h3>Create Account</h3>
      <p>Sign up for free and create your patient profile in just a few minutes.</p>
    </div>
    
    <div class="step-item">
      <div class="step-number">2</div>
      <h3>Book Appointment</h3>
      <p>Choose your preferred dentist, service, and time slot that works for you.</p>
    </div>
    
    <div class="step-item">
      <div class="step-number">3</div>
      <h3>Visit & Smile</h3>
      <p>Attend your appointment and receive top-quality dental care from our experts.</p>
    </div>
  </div>
</section>

<!-- Call to Action Section -->
<div class="cta-section">
  <h2>Ready to Transform Your Smile?</h2>
  <p>Join thousands of satisfied patients who trust Dents-City for their dental care</p>
  <div class="hero-buttons">
    <a href="<?php echo $base_url; ?>/public/register.php" class="btn-primary">Get Started Today</a>
    <a href="<?php echo $base_url; ?>/public/contact.php" class="btn-secondary">Contact Us</a>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
