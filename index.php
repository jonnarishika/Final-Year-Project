<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsor a Child - PARIVAR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #FDEDD3;
            overflow-x: hidden;
        }

        /* HEADER */
        .header {
            background: #2A2F67;
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            width: 100%;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
        }
        .logo-placeholder {
    width: 48px !important;
    height: 41px !important;   /* square = hides text */
    border-radius: 8px !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: flex-start !important; /* important */
    justify-content: center !important;
    background: white !important;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12) !important;
}

.logo-placeholder img {
    width: 100% !important;
    height: auto !important;
    object-fit: cover !important;
}
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 35px;
            align-items: center;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 0;
        }

        .nav-link:hover {
            color: #D66F34;
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s ease;
            display: inline-block;
        }

        .dropdown:hover .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            padding: 8px 0;
            margin-top: 12px;
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            margin-top: 8px;
        }

        .dropdown-link {
            display: block;
            padding: 12px 24px;
            color: #2A2F67;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .dropdown-link:hover {
            background: rgba(214, 111, 52, 0.1);
            color: #D66F34;
            padding-left: 28px;
        }

        .login-btn {
            background: #D66F34;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(214, 111, 52, 0.3);
        }

        .login-btn:hover {
            background: #C05E28;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(214, 111, 52, 0.4);
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        /* HERO SECTION CSS */
        .hero-section {
            position: relative;
            overflow: hidden;
            min-height: 600px;
        }

        .hero-video-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .hero-video-background video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 40px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 60px;
            position: relative;
            z-index: 2;
        }

        .hero-text h1 {
            font-size: 52px;
            font-weight: 800;
            color: #FFFFFF;
            line-height: 1.1;
            margin-bottom: 24px;
            text-shadow: 0 4px 22px rgba(0, 0, 0, 0.35);
        }

        .hero-text p {
            font-size: 19px;
            color: #F1F5F9;
            margin-bottom: 35px;
            line-height: 1.6;
            text-shadow: 0 2px 14px rgba(0, 0, 0, 0.25);
        }

        .hero-btn {
            background: #D66F34;
            color: #FFFFFF;
            border: none;
            padding: 16px 40px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(214, 111, 52, 0.35);
        }

        .hero-btn:hover {
            background: #C05E28;
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(214, 111, 52, 0.45);
        }

        .hero-image {
            position: relative;
            height: 550px;
            width: 100%;
        }

        /* STORIES SECTION */
        .stories-section {
            background: #679797;
            padding: 80px 0;
        }

        .stories-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
        }

        .stories-section h2 {
            color: white;
            font-size: 38px;
            font-weight: 700;
            margin-bottom: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stories-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .story-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
            text-decoration: none;
            display: block;
        }

        .story-card:hover {
            transform: translateY(-8px);
        }

        .story-image {
            width: 100%;
            height: 220px;
            position: relative;
        }

        .story-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .story-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 50px 20px 20px;
        }

        .story-title {
            color: white;
            font-size: 22px;
            font-weight: 700;
        }

        /* TRANSPARENCY SECTION */
        .transparency-section {
            background: linear-gradient(135deg, #D4C5E0 0%, #DDD0E8 50%, #D4C5E0 100%);
            padding: 80px 0;
            position: relative;
        }

        .transparency-section::before {
            content: 'TRANSPARENCY & TRUST';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 20px 0;
            font-size: 20px;
            font-weight: 600;
            color: #2A2F67;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .transparency-container {
            max-width: 1200px;
            margin: 60px auto 0;
            padding: 0 40px;
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 100px;
            align-items: start;
            position: relative;
        }

        .transparency-container::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(42, 47, 103, 0.2), transparent);
        }

        .transparency-text {
            position: relative;
            padding-right: 30px;
        }

        .transparency-text h2 {
            font-size: 42px;
            font-weight: 700;
            color: #2A2F67;
            margin-bottom: 32px;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .transparency-text ul {
            list-style: none;
            color: #2A2F67;
            font-size: 20px;
            line-height: 2.3;
            margin-bottom: 40px;
            font-weight: 600;
        }

        .transparency-text li {
            position: relative;
            padding-left: 8px;
            transition: all 0.3s ease;
        }

        .transparency-text li:hover {
            padding-left: 12px;
        }

        .transparency-text li:before {
            content: "• ";
            font-weight: bold;
            margin-right: 12px;
            font-size: 26px;
            color: #679797;
        }

        .trust-badge {
            margin-top: 30px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .trust-badge img {
            display: block;
            max-width: 100%;
            height: auto;
        }

        .transparency-steps {
            display: flex;
            flex-direction: column;
            gap: 22px;
            position: relative;
        }

        .transparency-steps::before {
            content: '';
            position: absolute;
            left: 37px;
            top: 85px;
            bottom: 85px;
            width: 2px;
            background: linear-gradient(180deg, 
                rgba(103, 151, 151, 0.3) 0%, 
                rgba(214, 111, 52, 0.3) 50%, 
                rgba(42, 47, 103, 0.3) 100%);
        }

        .trust-title {
            font-size: 24px;
            font-weight: 600;
            color: #2A2F67;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .step-card {
            background: white;
            padding: 32px 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 32px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
        }

        .step-card::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-left: 8px solid rgba(42, 47, 103, 0.15);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .step-card:hover::before {
            opacity: 1;
            left: -12px;
        }

        .step-card:nth-child(2) {
            background: #679797;
            color: white;
        }

        .step-card:nth-child(3) {
            background: #D66F34;
            color: white;
        }

        .step-card:nth-child(4) {
            background: white;
            color: #2A2F67;
        }

        .step-card:hover {
            transform: translateX(10px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .step-icon {
            font-size: 44px;
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.25);
            border-radius: 18px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .step-card:nth-child(4) .step-icon {
            background: rgba(42, 47, 103, 0.1);
        }

        .step-text h4 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .step-text p {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            opacity: 0.95;
        }

        /* IMPACT SECTION */
        .impact-section {
            background: linear-gradient(135deg, #D4C5E0 0%, #DDD0E8 50%, #D4C5E0 100%);
            padding: 0 0 80px 0;
        }

        .impact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
        }

        .impact-section h2 {
            font-size: 38px;
            font-weight: 700;
            color: #2A2F67;
            margin-bottom: 50px;
            text-transform: uppercase;
        }

        .impact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .impact-card {
            background: white;
            padding: 50px 40px;
            border-radius: 24px;
            text-align: center;
            transition: transform 0.3s;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .impact-card:nth-child(1) {
            background: #2A2F67;
            color: white;
        }

        .impact-card:nth-child(2) {
            background: #D66F34;
            color: white;
        }

        .impact-card:nth-child(3) {
            background: #679797;
            color: white;
        }

        .impact-card:hover {
            transform: translateY(-10px);
        }

        .impact-number {
            font-size: 58px;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1;
        }

        .impact-label {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* MOBILE */
        @media (max-width: 968px) {
            .hero-container,
            .transparency-container {
                grid-template-columns: 1fr;
            }

            .stories-grid {
                grid-template-columns: 1fr;
            }

            .impact-grid {
                grid-template-columns: 1fr;
            }

            .hero-text h1 {
                font-size: 38px;
            }

            .hero-image {
                position: relative;
                height: 520px;
                width: 100%;
                overflow: hidden;
            }

            .transparency-steps::before {
                display: none;
            }

            .transparency-container::before {
                display: none;
            }

            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #2A2F67;
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            .nav-menu.active {
                display: flex;
            }

            .mobile-toggle {
                display: block;
            }

            .dropdown-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <div class="logo-placeholder">
                        <img src="logo.jpeg" alt="Logo">
                    </div>
                </div>
                
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Home</a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Who We Are
                            <span class="dropdown-arrow">▼</span>
                        </a>
                        <div class="dropdown-menu">
                            <a href="about-us.html" class="dropdown-link">About Us</a>
                            <a href="how-we-work.html" class="dropdown-link">How We Work</a>
                            <a href="our-journey.html" class="dropdown-link">Our Journey</a>
                            <a href="success-stories.html" class="dropdown-link">Success Stories</a>
                            <a href="team.html" class="dropdown-link">Team</a>
                        </div>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Donate
                            <span class="dropdown-arrow">▼</span>
                        </a>
                        <div class="dropdown-menu">
                            <a href="sponsor-child.html" class="dropdown-link">Sponsor a Child</a>
                            <a href="education.html" class="dropdown-link">Education</a>
                            <a href="where-most-needed.html" class="dropdown-link">Where Most Needed</a>
                        </div>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Media & Resources
                            <span class="dropdown-arrow">▼</span>
                        </a>
                        <div class="dropdown-menu">
                            <a href="gallery.html" class="dropdown-link">Gallery</a>
                            <a href="faq.html" class="dropdown-link">FAQ</a>
                            <a href="financial-accountability.html" class="dropdown-link">Financial Accountability</a>
                        </div>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Get in Touch
                            <span class="dropdown-arrow">▼</span>
                        </a>
                        <div class="dropdown-menu">
                            <a href="contact-us.html" class="dropdown-link">Contact Us</a>
                            <a href="careers.html" class="dropdown-link">Careers</a>
                        </div>
                    </li>
                </ul>
                
                <a href="signup_and_login/login_template.php" class="login-btn">Login</a>
                <button class="mobile-toggle">☰</button>
            </div>
        </nav>
    </header>

    <!-- HERO SECTION HTML -->
    <section class="hero-section">
        <!-- FULL BACKGROUND VIDEO -->
        <div class="hero-video-background">
            <video autoplay muted loop playsinline>
                <source src="video.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
        
        <div class="hero-container">
            <div class="hero-text">
                <h1>Empowering Communities,<br>Changing Lives</h1>
                <p>Make a lasting impact through child sponsorship</p>
                <form action="all_children_profiles_sponser.php" method="get" style="display: inline;">
                    <button type="submit" class="hero-btn">Sponsor a Child</button>
                </form>
            </div>
            <div class="hero-image">
                <!-- Empty space for layout -->
            </div>
        </div>
    </section>

    <section class="stories-section">
        <div class="stories-container">
            <h2>Stories of Change</h2>
            <div class="stories-grid">
                <a href="index.php" class="story-card">
                    <div class="story-image">
                        <img src="eduindex.jpg" alt="Education">
                        <div class="story-overlay">
                            <div class="story-title">Education for All</div>
                        </div>
                    </div>
                </a>
                <a href="index.php" class="story-card">
                    <div class="story-image">
                        <img src="food.jpg" alt="Nourishing">
                        <div class="story-overlay">
                            <div class="story-title">Nourishing Futures</div>
                        </div>
                    </div>
                </a>
                <a href="index.php" class="story-card">
                    <div class="story-image">
                        <img src="safe.jpeg" alt="Safe Haven">
                        <div class="story-overlay">
                            <div class="story-title">Safe Havens</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="transparency-section">
        <div class="transparency-container">
            <div class="transparency-text">
                <h2>Your Generosity Protected</h2>
                <ul>
                    <li>Verifiable Projects</li>
                    <li>Independent Audits</li>
                    <li>Zero Fraud Tolerance</li>
                </ul>
                <div class="trust-badge">
                    <img src="happy_drawing.png" alt="Happy Family Drawing">
                </div>
            </div>
            <div class="transparency-steps">
                <div class="trust-title">How It Works</div>
                <div class="step-card">
                    <div class="step-icon">1</div>
                    <div class="step-text">
                        <h4>CHOOSE</h4>
                        <p>CAUSE</p>
                    </div>
                </div>
                <div class="step-card">
                    <div class="step-icon">2</div>
                    <div class="step-text">
                        <h4>DONATE</h4>
                        <p>SECURELY</p>
                    </div>
                </div>
                <div class="step-card">
                    <div class="step-icon">3</div>
                    <div class="step-text">
                        <h4>TRACK</h4>
                        <p>IMPACT</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="impact-section">
        <div class="impact-container">
            <h2>Global Impact</h2>
            <div class="impact-grid">
                <div class="impact-card">
                    <div class="impact-number">2.5M+</div>
                    <div class="impact-label">Children Reached</div>
                </div>
                <div class="impact-card">
                    <div class="impact-number">1500+</div>
                    <div class="impact-label">Projects Funded</div>
                </div>
                <div class="impact-card">
                    <div class="impact-number">92%</div>
                    <div class="impact-label">Funds Directly to Causes</div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'components/footer.php'; ?>

    <script>
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>
</html>