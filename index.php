<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sip Happens Coffee Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.unsplash.com/photo-1498804103079-a6351b050096?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .navbar-brand img {
            height: 50px;
        }
        .hero {
            padding: 100px 0;
            text-align: center;
        }
        .hero h1 {
            font-size: 4rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #f8f9fa;
        }
        .tagline {
            font-size: 1.5rem;
            font-style: italic;
            margin-bottom: 30px;
            color: #e9ecef;
        }
        .description {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        .btn-primary {
            background-color: #6f4e37;
            border-color: #6f4e37;
            padding: 12px 30px;
            font-size: 1.1rem;
        }
        .btn-primary:hover {
            background-color: #5a3f2e;
            border-color: #5a3f2e;
        }
        .login-btn {
            background-color: transparent;
            border: 2px solid #fff;
            color: #fff;
        }
        .login-btn:hover {
            background-color: #fff;
            color: #000;
        }
        .footer {
            position: absolute;
            bottom: 20px;
            width: 100%;
            text-align: center;
            color: #adb5bd;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.png" alt="Sip Happens Logo" onerror="this.src='https://via.placeholder.com/150x50/6f4e37/ffffff?text=Sip+Happens'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
                    <li class="nav-item">
                        <a href="login.php" class="btn btn-outline-light ms-2">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Sip Happens</h1>
            <p class="tagline">When life spills, we refill.</p>
            <p class="description">
                No matter what happens, a good sip makes everything better. 
                Welcome to our cozy corner where every cup tells a story.
            </p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card bg-dark bg-opacity-75 text-white p-4">
                        <h3 class="mb-4">Love Coffee</h3>
                        <p class="lead mb-0">
                            Lorem ipsum dolor sit amet consectetur adipiscing elit, 
                            sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container my-5" id="about">
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <i class="fas fa-coffee fa-3x mb-3" style="color: #6f4e37;"></i>
                <h4>Artisan Coffee</h4>
                <p>Carefully sourced and expertly brewed</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-heart fa-3x mb-3" style="color: #6f4e37;"></i>
                <h4>Fresh Pastries</h4>
                <p>Baked fresh daily with love</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-users fa-3x mb-3" style="color: #6f4e37;"></i>
                <h4>Cozy Atmosphere</h4>
                <p>Perfect spot to relax and unwind</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sip Happens Coffee Shop. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>