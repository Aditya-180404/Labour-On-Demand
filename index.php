<?php
require_once 'config/security.php';
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Labour On Demand</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        /* Theme Variables */
        :root {
            --hero-bg: #ffffff;
            --hero-text: #1e293b;
            --orbit-circle-stroke: rgba(0, 0, 0, 0.08);
            --network-line-stroke: rgba(0, 0, 0, 0.15);
            --icon-bg: #ffffff;
            --icon-stroke-primary: #334155;
            --icon-stroke-accent: #f59e0b;
        }

        [data-bs-theme="dark"] {
            --hero-bg: #0a0e27;
            --hero-text: #ffffff;
            --orbit-circle-stroke: rgba(255, 140, 0, 0.15);
            --network-line-stroke: rgba(255, 140, 0, 0.2);
            --icon-bg: #1a1f3a;
            --icon-stroke-primary: #f8fafc;
        }

        /* Override Hero Background - Theme Aware */
        .hero-section {
            background-color: var(--hero-bg) !important;
            background-image: none !important; /* Disable index.css gradient */
            color: var(--hero-text) !important;
            position: relative;
            overflow: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* SVG Overlay Styles */
        .hero-bg-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        
        /* Themed SVG Elements */
        .hero-bg-rect { fill: var(--hero-bg); transition: fill 0.3s ease; }
        .orbit-circle { stroke: var(--orbit-circle-stroke) !important; transition: stroke 0.3s ease; }
        .network-line { stroke: var(--network-line-stroke) !important; transition: stroke 0.3s ease; }
        .icon-bg { fill: var(--icon-bg); transition: fill 0.3s ease; }
        .central-path { stroke: var(--icon-stroke-accent) !important; }
        
        /* Specific overrides for light mode visibility */
        [data-bs-theme="light"] .hero-section .text-white {
            color: #1e293b !important;
        }
        [data-bs-theme="light"] .search-box {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }

        /* Animation Keyframes */
        @keyframes pulse { 0%, 100% { r: 3; opacity: 1; } 50% { r: 5; opacity: 0.4; } }
        .pulse-node { animation: pulse 3s ease-in-out infinite; }
        .node-1 { animation-delay: 0s; } .node-2 { animation-delay: 0.5s; } .node-3 { animation-delay: 1s; } .node-4 { animation-delay: 1.5s; }

        @keyframes shimmer { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
        
        @keyframes dataFlow { 0% { stroke-dashoffset: 40; } 100% { stroke-dashoffset: 0; } }
        .data-flow { stroke-dasharray: 4 4; animation: dataFlow 3s linear infinite; }
        .flow-1 { animation-delay: 0s; } .flow-2 { animation-delay: 1s; }

        @keyframes orbit { 0% { transform: rotate(0deg) translateX(120px) rotate(0deg); } 100% { transform: rotate(360deg) translateX(120px) rotate(-360deg); } }
        .orbit-icon { animation: orbit 25s linear infinite; } 
        .icon-user { animation-delay: 0s; } .icon-worker { animation-delay: -6.25s; } 
        .icon-location { animation-delay: -12.5s; } .icon-tools { animation-delay: -18.75s; }

        @keyframes cloudFloat { 0%, 100% { transform: translate(300px, 200px) translateY(0); } 50% { transform: translate(300px, 200px) translateY(-8px); } }
        .central-cloud { animation: cloudFloat 5s ease-in-out infinite; }

        @keyframes rotateOrbit { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .orbit-circle { transform-origin: center; animation: rotateOrbit 80s linear infinite; }
        
        /* Ensure content is above SVG */
        .hero-section .container { z-index: 2; position: relative; }
    </style>
<body>
    

    <?php 
    $path_prefix = '';
    include 'includes/navbar.php'; 
    ?>

    <header class="hero-section text-center d-flex align-items-center justify-content-center">
        <!-- SVG Animation Background (Theme Aware) -->
        <svg viewBox="-150 -100 900 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" class="hero-bg-svg">
            <rect x="-150" y="-100" width="100%" height="100%" class="hero-bg-rect"/>
            
            <!-- Network Sphere Background -->
            <g class="network-sphere">
                <!-- Concentric Circles -->
                <circle cx="300" cy="200" r="120" fill="none" class="orbit-circle" stroke-width="1"/>
                <circle cx="300" cy="200" r="90" fill="none" class="orbit-circle" stroke-width="1"/>
                <circle cx="300" cy="200" r="180" fill="none" class="orbit-circle" stroke-width="1"/>
                
                <!-- Network Lines -->
                <line x1="100" y1="50" x2="300" y2="200" class="network-line line-1" stroke-width="1"/>
                <line x1="500" y1="50" x2="300" y2="200" class="network-line line-2" stroke-width="1"/>
                <line x1="100" y1="350" x2="300" y2="200" class="network-line line-3" stroke-width="1"/>
                <line x1="500" y1="350" x2="300" y2="200" class="network-line line-4" stroke-width="1"/>
                
                <!-- Pulsing Nodes (Brand Color) -->
                <circle cx="100" cy="50" r="3" fill="#f59e0b" class="pulse-node node-1"/>
                <circle cx="500" cy="50" r="3" fill="#f59e0b" class="pulse-node node-2"/>
                <circle cx="100" cy="350" r="3" fill="#f59e0b" class="pulse-node node-3"/>
                <circle cx="500" cy="350" r="3" fill="#f59e0b" class="pulse-node node-4"/>
            </g>
            
            <!-- Central Icon: House -->
            <g class="central-cloud" transform="translate(300, 200)">
                <path d="M -25,0 L 0,-25 L 25,0 L 25,25 L -25,25 Z" 
                      fill="none" class="central-path" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M -8,25 L -8,12 L 8,12 L 8,25" fill="none" class="central-path" stroke-width="2"/>
                <path d="M 15,-15 L 15,-20 L 20,-20" fill="none" class="central-path" stroke-width="2"/>
            </g>
            
            <!-- Orbiting Icons -->
            <!-- User Icon -->
            <g class="orbit-icon icon-user" transform-origin="300 200">
                <circle cx="300" cy="80" r="14" class="icon-bg" stroke="#f59e0b" stroke-width="1.5"/>
                <circle cx="300" cy="76" r="4" fill="#f59e0b"/>
                <path d="M 294,86 Q 300,90 306,86" fill="none" stroke="#f59e0b" stroke-width="1.5"/>
            </g>
            
            <!-- Worker Icon: Helmet -->
            <g class="orbit-icon icon-worker" transform-origin="300 200">
                <circle cx="420" cy="200" r="14" class="icon-bg" stroke="#f59e0b" stroke-width="1.5"/>
                <path d="M 412,202 Q 412,194 420,194 Q 428,194 428,202 L 430,202 L 410,202 L 412,202 Z" fill="#f59e0b"/>
            </g>
            
            <!-- Location Icon -->
            <g class="orbit-icon icon-location" transform-origin="300 200">
                <circle cx="300" cy="320" r="14" class="icon-bg" stroke="#f59e0b" stroke-width="1.5"/>
                <path d="M 300,312 Q 296,312 296,316 Q 296,320 300,326 Q 304,320 304,316 Q 304,312 300,312" fill="none" stroke="#f59e0b" stroke-width="1.5"/>
                <circle cx="300" cy="316" r="1.5" fill="#f59e0b"/>
            </g>
            
            <!-- Tools Icon -->
            <g class="orbit-icon icon-tools" transform-origin="300 200">
                <circle cx="180" cy="200" r="14" class="icon-bg" stroke="#f59e0b" stroke-width="1.5"/>
                <path d="M 176,198 L 180,194 L 184,198 M 180,194 L 180,208" fill="none" stroke="#f59e0b" stroke-width="1.5"/>
            </g>
            
            <!-- Data Flow Particles -->
            <circle r="2" fill="#f59e0b" class="particle particle-1">
                <animateMotion dur="4s" repeatCount="indefinite"><mpath href="#path1"/></animateMotion>
            </circle>
            <circle r="2" fill="#f59e0b" class="particle particle-2">
                <animateMotion dur="5s" repeatCount="indefinite"><mpath href="#path2"/></animateMotion>
            </circle>
            
            <defs>
                <path id="path1" d="M 200,120 Q 250,160 300,200"/>
                <path id="path2" d="M 400,120 Q 350,160 300,200"/>
            </defs>
        </svg>
        
        <div class="container parallax-layer">
            <h1 class="display-3 fw-bold mb-4">
                <span id="typingText" class="typing-text"></span>
            </h1>
            <p class="lead mb-5">Plumbers, Electricians, Cleaners, and more at your doorstep.</p>
            <div class="search-box bg-body-tertiary p-3 rounded-pill shadow mx-auto" style="border: none;">
                <form action="customer/workers.php" method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control bg-transparent border-0 me-2" placeholder="What service do you need? (e.g. Plumber)">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Search</button>
                </form>
            </div>
        </div>
    </header>



    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Popular Services</h2>
            <div class="row g-4">
                <?php
                $stm = $pdo->query("SELECT * FROM categories LIMIT 6");
                while($cat = $stm->fetch()):
                ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="customer/workers.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                        <div class="card category-card text-center h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas <?php echo $cat['icon']; ?> fa-3x mb-3 text-primary"></i>
                                <h5 class="card-title text-body"><?php echo $cat['name']; ?></h5>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
            <div class="text-center mt-5">
                <a href="customer/services.php" class="btn btn-outline-primary rounded-pill px-4">View All Services</a>
            </div>
        </div>
    </section>

    <!-- Mechanics / How it Works -->
    <section class="py-5 bg-body-tertiary">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">How It Works</h2>
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-search fa-2x text-primary"></i>
                        </div>
                        <h4>1. Search</h4>
                        <p class="text-muted">Choose from a wide range of services or search for a specific worker near you.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-calendar-check fa-2x text-primary"></i>
                        </div>
                        <h4>2. Book</h4>
                        <p class="text-muted">Select a suitable time and date. Book your worker instantly.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-smile fa-2x text-primary"></i>
                        </div>
                        <h4>3. Relax</h4>
                        <p class="text-muted">The worker comes to your doorstep. Rate them after the job is done.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Typing Animation
        const text = "Find Local Workers in Minutes";
        const typingElement = document.getElementById('typingText');
        let index = 0;
        
        function typeWriter() {
            if (index < text.length) {
                typingElement.textContent += text.charAt(index);
                index++;
                setTimeout(typeWriter, 80); // 80ms delay between letters
            } else {
                // Remove cursor after typing is complete
                setTimeout(() => {
                    typingElement.classList.remove('typing-text');
                }, 500);
            }
        }
        
        // Start typing animation when page loads
        window.addEventListener('load', () => {
            setTimeout(typeWriter, 500); // Start after 500ms delay
        });
        
        // Generate Dot Matrix - REMOVED for SVG Background
        /*
        const dotMatrix = document.getElementById('dotMatrix');
        const dotCount = 100;
        
        for (let i = 0; i < dotCount; i++) {
        ...
        }
        */
        
        // Parallax Effect on Mouse Move
        document.addEventListener('mousemove', (e) => {
            const parallaxLayers = document.querySelectorAll('.parallax-layer');
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            parallaxLayers.forEach((layer, index) => {
                const speed = (index + 1) * 5;
                const x = (mouseX - 0.5) * speed;
                const y = (mouseY - 0.5) * speed;
                layer.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
    </script>
</body>
</html>
