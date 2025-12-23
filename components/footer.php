<!-- Footer Component - Save as: components/footer.php -->
<style>
    .site-footer {
        background: #2A2F67;
        color: white;
        padding: 60px 0 30px;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 40px;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1.5fr;
        gap: 50px;
        margin-bottom: 40px;
    }

    .footer-column h3 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #D66F34;
    }

    .footer-logo-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .footer-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    /* Updated logo styling to match header */
    .footer-logo-img {
        width: 48px !important;
        height: 41px !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: flex-start !important;
        justify-content: center !important;
        background: white !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12) !important;
    }

    .footer-logo-img img {
        width: 100% !important;
        height: auto !important;
        object-fit: cover !important;
    }

    .footer-logo-text {
        font-size: 24px;
        font-weight: 800;
        color: white;
    }

    .footer-description {
        font-size: 14px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.8);
    }

    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 12px;
    }

    .footer-links a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
        display: inline-block;
    }

    .footer-links a:hover {
        color: #D66F34;
        padding-left: 5px;
    }

    .footer-contact-item {
        display: flex;
        align-items: start;
        gap: 12px;
        margin-bottom: 15px;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
    }

    .footer-contact-icon {
        font-size: 18px;
        color: #D66F34;
        margin-top: 2px;
    }

    .footer-contact-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-contact-item a:hover {
        color: #D66F34;
    }

    .footer-social {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }

    .social-link {
        width: 40px;
        height: 41px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        font-size: 18px;
        transition: all 0.3s ease;
    }

    .social-link:hover {
        background: #D66F34;
        transform: translateY(-3px);
    }

    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        color: rgba(255, 255, 255, 0.6);
    }

    .footer-bottom-links {
        display: flex;
        gap: 20px;
    }

    .footer-bottom-links a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-bottom-links a:hover {
        color: #D66F34;
    }

    @media (max-width: 968px) {
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .footer-bottom {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .footer-bottom-links {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <!-- Logo & Description -->
            <div class="footer-column footer-logo-section">
                <div class="footer-logo">
                    <div class="footer-logo-img">
                        <img src="logo.jpeg" alt="pari-var">
                    </div>
                    <div class="footer-logo-text">PARI-VAR</div>
                </div>
                <p class="footer-description">
                    Empowering communities and changing lives through child sponsorship. Together, we create lasting impact and brighter futures.
                </p>
                <div class="footer-social">
                    <a href="#" class="social-link" title="Facebook">f</a>
                    <a href="#" class="social-link" title="Twitter">𝕏</a>
                    <a href="#" class="social-link" title="Instagram">📷</a>
                    <a href="#" class="social-link" title="LinkedIn">in</a>
                </div>
            </div>

            <!-- About Us -->
            <div class="footer-column">
                <h3>About Us</h3>
                <ul class="footer-links">
                    <li><a href="about-us.html">Our Story</a></li>
                    <li><a href="how-we-work.html">How We Work</a></li>
                    <li><a href="our-journey.html">Our Journey</a></li>
                    <li><a href="team.html">Our Team</a></li>
                    <li><a href="success-stories.html">Success Stories</a></li>
                    <li><a href="financial-accountability.html">Transparency</a></li>
                </ul>
            </div>

            <!-- Quick Links -->
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="sponsor-child.html">Sponsor a Child</a></li>
                    <li><a href="education.html">Education</a></li>
                    <li><a href="where-most-needed.html">Donate</a></li>
                    <li><a href="gallery.html">Gallery</a></li>
                    <li><a href="faq.html">FAQ</a></li>
                    <li><a href="careers.html">Careers</a></li>
                </ul>
            </div>

            <!-- Contact Us -->
            <div class="footer-column">
                <h3>Contact Us</h3>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">📧</span>
                    <div>
                        <strong>Email:</strong><br>
                        <a href="mailto:pari_admin@gmail.com">pari_admin@gmail.com</a>
                    </div>
                </div>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">📞</span>
                    <div>
                        <strong>Phone:</strong><br>
                        <a href="tel:+911234567890">+91 123 456 7890</a><br>
                        <a href="tel:+919876543210">+91 987 654 3210</a>
                    </div>
                </div>
                <div class="footer-contact-item">
                    <span class="footer-contact-icon">📍</span>
                    <div>
                        <strong>Address:</strong><br>
                        Lal Bahadur Nagar<br>
                        Telangana, India
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; 2024 PARIVAR. All rights reserved.</div>
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>