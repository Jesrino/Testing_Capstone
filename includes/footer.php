</main>

<!-- Unified footer -->
<footer class="site-footer">
    <div class="footer-inner">

        <div class="footer-col">
            <h3>Dents-City Dental Clinic</h3>
            <p>
                Your trusted partner for comprehensive dental care. We provide quality dental services
                with a focus on patient comfort and satisfaction.
            </p>
            <div class="social-links">
                <a href="#" aria-label="Facebook">F</a>
                <a href="#" aria-label="Twitter">T</a>
                <a href="#" aria-label="Instagram">I</a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Services</h4>
            <ul>
                <li>General Dentistry</li>
                <li>Cosmetic Dentistry</li>
                <li>Orthodontics</li>
                <li>Emergency Care</li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="<?php echo $base_url; ?>/public/index.php">Home</a></li>
                <li><a href="<?php echo $base_url; ?>/public/about.php">About Us</a></li>
                <li><a href="<?php echo $base_url; ?>/public/services.php">Services</a></li>
                <li><a href="<?php echo $base_url; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contact Info</h4>
            <ul class="contact-list">
                <li><strong>Address:</strong> 123 Dental Street, City Center<br>Baguio City 2600, Philippines</li>
                <li><strong>Phone:</strong> +63 74 442 3316</li>
                <li><strong>Email:</strong> <a href="mailto:info@dentscity.com">info@dentscity.com</a></li>
                <li><strong>Hours:</strong> Mon–Fri: 8AM–6PM; Sat: 9AM–4PM</li>
            </ul>

            <form class="newsletter-form" action="<?php echo $base_url; ?>/api/newsletter.php" method="post">
                <input type="email" name="email" placeholder="Subscribe to our newsletter" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>

    </div>
</footer>

</div>
</body>
</html>
