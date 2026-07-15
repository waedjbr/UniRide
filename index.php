
You said:
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniRide - Student Transportation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Reset and Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content:center;
        }
        .logo {
            height: 40px;
            margin-left: 1rem;
            margin-right: 1rem;
        }
        .nav-auth {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            padding-right: 3rem;
        }

        .nav-auth .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1.5rem 0.5rem 1.5rem;
            margin-top: -0.1rem;
        }

        .nav-links {
            display: flex;
            justify-content: center;
            list-style: none;
            margin-left: 3rem;
        }

        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            transition: color 0.3s;
            font-size: 1.1rem;
            font-weight: bold;
            
        }

        .nav-links a:hover {
            color: #028A99;
        }

        .hamburger {
            display: none;
            cursor: pointer;
            
        }

        /* Hero Section */
        
        .hero {
            height: 100vh;
            background: #ffffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: black;
            padding: 0 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-container{
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3rem;
        }

        /* LEFT */
        .hero-left{
            width: 50%;
            padding-bottom: 5rem;
        }
        .hero-left h1{
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero-left p{
            font-size: 18px;
        }

        .hero-left p span{
            font-size: 20px;
            font-weight: bold;
        }

        /* RIGHT IMAGE */
        .hero-right{
            width: 50%;
        }
        .hero-right img{
            width: 100%;
            max-width: 500px;
            border-radius: 10px;
        }
        .hero-divider {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            pointer-events: none; 
        }
        .hero-divider svg {
            display: block;
            width: 100%;
        }
        #register {
            scroll-margin-top: 100px; /* adjust to navbar height */
        }
        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background:  #028A99;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            margin-top:1rem ;
        }

        .btn:hover {
            background: #01606B;
        }

        /* How It Works */
        .how-it-works {
            padding: 5rem 1rem;
            text-align: center;
            background: #28A6B4;
            color:black;
        }

        .steps {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 2rem;
        }

        .step {
            flex: 1;
            min-width: 250px;
            padding: 2rem;
            background: white;   
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .step:hover {
            transform: translateY(-5px);
        }

        .step i {
            font-size: 2.5rem;
            color: white;
            margin-bottom: 1rem;
            
        }

        /* Services */
        .services {
            background: #f8f9fa;
            padding: 5rem 1rem;
            text-align: center;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }

        .service-card {
            text-align: center;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            color: white;
            transition: transform 0.3s;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        /* Contact */
        .contact {
            padding: 5rem 1rem;
            text-align: center;
            background: #028A99;
            color: white;
        }

        .social-links {
            margin: 2rem 0;
        }

        .social-links a {
            color: white;
            font-size: 1.5rem;
            margin: 0 1rem;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: black;
        }

        .partners {
            background: #f8f9fa;
            padding: 5rem 1rem;
            text-align: center;
        }
        .partners img{
            height:300px;
            width: 300px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .logo {
                height: 30px;
                position: absolute;
                left: 1rem;
                margin: 0;
            }
            .nav-auth {
                padding: 1rem 0;
                text-align: center;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                margin-left: 0;
            }
            .navbar {
                position: fixed;
                padding: 1rem;
                min-height: 60px;
            }
            .nav-links {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: white;
                padding: 1rem;
            }

            .nav-links.active {
                display: flex;
            }

            .hamburger {
                display: block;
                position: absolute;
                right: 1rem;
                top: 1rem;
            }
            .hero {
                height: auto;
                min-height: 100vh;
                padding-top: 120px; /* space for navbar */
                padding-bottom: 120px;
                align-items: flex-start;
            }

            .hero-container{
                flex-direction: column;
                text-align: center;
            }

            .hero-right img{
                max-width: 330px;
            }

            .hero-left h1{
                font-size: 1.8rem;
            }
            .hero-left, .hero-right{
                width: 100%;
            }
            .hero-left{
                margin-top: 0;
            }
            .hero-right{
                margin-bottom : 0;
            }
        }
    #preloader {
            position: fixed;
            inset: 0;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
            transition: opacity 0.6s ease;
            opacity: 1;
        }

        #lottie-container {
            width: 100px;    /* adjust size */
            height: 100px;
        }
        .scroll-wrapper {
            overflow: hidden;       /* Hide overflowing images */
            width: 100%;
            margin-top:30px;
        }

        .scroll-images {
            display: flex;
            width: max-content;     /* Expand to fit all images */
            animation: scroll 12s linear infinite;
        }

        .scroll-images img {
            margin-right: 70px;     /* Space between logos */
            width: 200px;
            height: 200px;
        }

        /* Keyframes for seamless scrolling */
        @keyframes scroll {
            0% {
            transform: translateX(0);
            }
            100% {
            transform: translateX(-50%); /* Move by width of first set */
            }
        }
        .register {
            padding: 5rem 1rem;
            text-align: center;
            background: #028A99;
            color: white;
        }

        .register-container {
            max-width: 1100px;
            margin: auto;
        }

        .register-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .register-text p span {
            font-weight: bold;
        }

        .register-cards {
            margin-top: 3rem;
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .reg-card {
            background: white;
            color: #333;
            width: 320px;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform .3s, box-shadow .3s;
        }

        .reg-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }

        .icon {
            font-size: 3rem;
            color: #028A99;
            margin-bottom: 1rem;
        }

        .reg-btn {
            display: inline-block;
            margin-top: 1.2rem;
            padding: 0.7rem 1.8rem;
            background: #028A99;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: background .3s;
        }

        .reg-btn:hover {
            background: #01606B;
        }

        /* Mobile */
        @media(max-width:768px){
            .register-text h2{
                font-size: 2rem;
            }
        }
        .footer {
            background: white;
            padding: 60px 20px 20px;
            color: #333;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: auto;
        }

        .footer-box h3 {
            margin-bottom: 15px;
            color: #028A99;
        }

        .footer-box p {
            line-height: 1.6;
        }

        .footer-box ul {
            list-style: none;
        }

        .footer-box ul li {
            margin-bottom: 10px;
        }

        .footer-box ul li a {
            text-decoration: none;
            color: #333;
            transition: 0.3s;
        }

        .footer-box ul li a:hover {
            color: #028A99;
        }

        .social-links a {
            font-size: 1.5rem;
            margin-right: 15px;
            color: #028A99;
            transition: 0.3s;
        }

        .social-links a:hover {
            color: black;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Lottie Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

    <div id="preloader">
        <div id="lottie-container"></div>
    </div>

    

    <!-- Navigation -->
    <nav class="navbar">
        <img src="logo1.png" alt="UniRide Logo" class="logo">
        <div class="hamburger">
            <i class="fas fa-bars"></i>
        </div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#how-it-works">How It Works</a></li>    
            <li><a href="#partners">Our Partners</a></li>
            <li><a href="#register">Join Us</a></li>
        </ul>
        <div class="nav-auth">
            <a href="login.php" class="btn">Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
    <div class="hero-container">
        
        <!-- LEFT TEXT -->
        <div class="hero-left">
            <h1>Connecting Students to <BR>Their Universities</h1>
            <p>Safe and reliable transportation for students in 
                <span>Rashaya</span> and 
                <span>Hasbaya</span>
            </p>
            <a href="#register" class="btn">Get Started</a> 
        </div>

        <!-- RIGHT IMAGE -->
        <div class="hero-right">
            <img src="pics/hero.jpg" alt="Transportation Image">
        </div>

    </div>
    <div class="hero-divider">
        <svg viewBox="0 0 1440 320">
            <path fill="#028A99" fill-opacity="1" 
            d="M0,224L60,218.7C120,213,240,203,360,208C480,213,600,235,720,229.3C840,224,960,192,1080,192C1200,192,1320,224,1380,240L1440,256V320H0Z">
            </path>
        </svg>
    </div>
    </section>


    <!-- Services -->
    <section id="services" class="services">
        <h2>Our Services</h2>
        <div class="services-grid">
            <div class="service-card" style="background:#028A99">
                <i class="fas fa-calendar-check"></i>
                <h3>Trip Booking</h3>
                <p>Easy and flexible ride scheduling</p>
            </div>
            <div class="service-card" style="background:#028A99">
                <i class="fas fa-user-shield"></i>
                <h3>Driver Posting</h3>
                <p>Offer rides on your schedule</p>
            </div>
            <div class="service-card" style="background:#028A99">
                <i class="fas fa-check-circle"></i>
                <h3>Admin Approval</h3>
                <p>Verified drivers for your safety</p>
            </div>
            <div class="service-card" style="background:#028A99">
                <i class="fas fa-shield-alt"></i>
                <h3>Safe Rides</h3>
                <p>Security and comfort guaranteed</p>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <i class="fas fa-user-plus" style="color:#028A99;"></i>
                <h3>Step 1: Sign Up</h3>
                <p>Create your account as a student or driver</p>
            </div>
            <div class="step">
                <i class="fas fa-car" style="color:#028A99;"></i>
                <h3>Step 2: Book or Post Trips</h3>
                <p>Find available rides or offer your services</p>
            </div>
            <div class="step">
                <i class="fas fa-route" style="color:#028A99;"></i>
                <h3>Step 3: Ride Together</h3>
                <p>Connect with others and share your journey</p>
            </div>
        </div>
    </section>
    <section id="partners" class="partners">
        <h2>Our Partners</h2>
        <div class="scroll-wrapper">
            <div class="scroll-images">
                <img src="pics/MUBS.jpg" alt="">
                <img src="pics/liu1.png" alt="">
                <img src="pics/lu.png" alt="">
                <img src="pics/cnam.png" alt="">
                <img src="pics/auce.jpg" alt="">
                <img src="pics/aust.jpg" alt="">
 
                <!-- Duplicate images for seamless effect -->
                <img src="pics/MUBS.jpg" alt="">
                <img src="pics/liu1.png" alt="">
                <img src="pics/lu.png" alt="">
                <img src="pics/cnam.png" alt="">
                <img src="pics/auce.jpg" alt="">
                <img src="pics/aust.jpg" alt="">

            </div>
        </div>
    </section>
    <section id="register" class="register">
        <div class="register-container">

            <div class="register-text">
                <h2>Join UniRide Today</h2>
                <p>
                    If you are a <strong>student</strong> or <strong>driver</strong> in 
                    <span>Rashaya</span> or <span>Hasbaya</span>,
                    register now and be part of a safer, easier, and smarter transportation system.
                </p>
            </div>

            <div class="register-cards">

                <!-- Student Card -->
                <div class="reg-card">
                    <i class="fas fa-user-graduate icon"></i>
                    <h3>Student Registration</h3>
                    <p>Book trips easily, connect with trusted drivers, and reach your university safely.</p>
                    <a href="register.php" class="reg-btn">Register as Student</a>
                </div>

                <!-- Driver Card -->
                <div class="reg-card">
                    <i class="fas fa-user-tie icon"></i>
                    <h3>Driver Registration</h3>
                    <p>Offer rides, help students in your area, and earn money with verified trips.</p>
                    <a href="register.php" class="reg-btn">Register as Driver</a>
                </div>

            </div>
        </div>
    </section>

    

   <footer class="footer">
    <div class="footer-container">

        <!-- Column 1 -->
        <div class="footer-box">
            <h3>UniRide</h3>
            <p>
                UniRide connects students and drivers in Rashaya and Hasbaya,
                providing safe, reliable, and affordable transportation to universities.
            </p>
        </div>

        <!-- Column 2 -->
        <div class="footer-box">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#partners">Our Partners</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </div>

        <!-- Column 3 -->
        <div class="footer-box">
            <h3>Contact Us</h3>
            <p>Email: info@uniride.com</p>
            <p>Phone: +961 1 234 567</p>

            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; UniRide 2025. All rights reserved.</p>
    </div>
</footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <script>
        // Load Lottie Animation
        lottie.loadAnimation({
            container: document.getElementById('lottie-container'),
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: 'bus.json' // your animation file
        });

        // Hide preloader when page fully loads
        window.addEventListener("load", function () {
            setTimeout(() => {
                document.getElementById("preloader").style.opacity = "0";

                setTimeout(() => {
                    document.getElementById("preloader").style.display = "none";
                }, 2000);

            }, 2000);
        });
    </script>


    <script>
        // Hamburger Menu
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');

        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });

        // Form Validation
        const form = document.getElementById('driverRegistrationForm');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            if (fullName.length < 3) {
                alert('Please enter a valid name');
                return;
            }
            
            if (!email.includes('@')) {
                alert('Please enter a valid email');
                return;
            }
            
            if (phone.length < 8) {
                alert('Please enter a valid phone number');
                return;
            }
            
            alert('Form submitted successfully!');
            form.reset();
        });

        // Close mobile menu when clicking a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });
    </script>
</body>
</html>