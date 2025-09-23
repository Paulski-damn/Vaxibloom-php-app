<?php
session_start();
require 'system/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaxiBloom - Child Vaccination Scheduling System</title>
    <link rel="icon" href="img/logo1.png" type="image/png">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Fredoka+One&display=swap" rel="stylesheet">

</head>
<body>
    <header>
            <a href="index.php" class="logo">
                <img src="img/logo1.png" alt="VaxiBloom Logo">
                <span class="logo-text">VaxiBloom</span>
            </a>
            
            <button class="menu-toggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav id="nav-menu">
                <a href="system/admin/login.php">Admin</a>
                <a href="system/user/login.php" class="signin">Sign In</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container hero-container">
                <div class="hero-content">
                    <h1 class="hero-title">Plan, track, and manage the child's vaccination schedule in your barangay.</h1>
                    <p class="hero-subtitle">Baby's health journey in your barangay starts here. Keep track of upcoming vaccines and appointments all in one place.</p>
                    <div class="hero-buttons">
                        <a href="system/user/login.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule an Appointment
                        </a>
                    </div>
                </div>
                
                <div class="carousel">
                    <div class="carousel-slide active">
                        <img src="img/background.jpg" alt="Happy baby with mother">
                    </div>
                    <div class="carousel-slide">
                        <img src="img/background1.jpg" alt="Doctor with baby">
                    </div>
                    <div class="carousel-slide">
                        <img src="img/background2.jpg" alt="Vaccination process">
                    </div>
                    <div class="carousel-nav">
                        <div class="carousel-dot active" data-slide="0"></div>
                        <div class="carousel-dot" data-slide="1"></div>
                        <div class="carousel-dot" data-slide="2"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features">
            <div class="container">
                <h2 class="section-title">Why Choose VaxiBloom?</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Vaccine Reminders</h3>
                        <p>Never miss an important vaccination with our automated reminders and notifications.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Easy Scheduling</h3>
                        <p>Book appointments with healthcare providers in just a few clicks.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <h3>Digital Records</h3>
                        <p>Access your child's complete vaccination history anytime, anywhere.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.getElementById('nav-menu').classList.toggle('active');
        });

        // Carousel functionality
        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dot');
        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            // Hide all slides
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            // Show current slide
            slides[index].classList.add('active');
            dots[index].classList.add('active');
            currentSlide = index;
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        // Add click event to dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                clearInterval(slideInterval);
                showSlide(index);
                startCarousel();
            });
        });

        function startCarousel() {
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        }

        // Initialize carousel
        showSlide(0);
        startCarousel();
    </script>
</body>
</html>