<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

// Fetch actual counts
$stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
$actual_users = $stmt_users->fetchColumn();

$stmt_workers = $pdo->query("SELECT COUNT(*) FROM workers");
$actual_workers = $stmt_workers->fetchColumn();

// Base starts + actual
$target_users = 5000 + ($actual_users ? $actual_users : 0);
$target_workers = 500 + ($actual_workers ? $actual_workers : 0);
$target_categories = 7; // Actual count
?>

<section id="stats-section" class="py-5 overflow-hidden position-relative stats-redesign">
    <div class="container py-5 text-center">
        <!-- Headline -->
        <div class="mb-5 reveal-fade">
            <h2 class="display-4 fw-bold mb-3">One Trusted Network <span class="text-gradient">Unlike Any Other</span></h2>
            <p class="text-muted lead mx-auto" style="max-width: 700px;">Providing essential services across every district of West Bengal with speed, security, and reliability.</p>
        </div>

        <div class="row align-items-center justify-content-center position-relative">
            
            <!-- LEFT COLUMN: Customers & Services -->
            <div class="col-md-3 d-flex flex-column gap-5 position-relative z-2 order-2 order-md-1">
                <!-- Customers (Top Left) -->
                <div class="stat-premium-card card-left reveal-fade" data-delay="100">
                    <div class="stat-dot dot-primary mx-auto mx-md-0 ms-md-auto"></div>
                    <h2 class="display-5 fw-bold text-md-end"><span class="counter-item" data-target="<?php echo $target_users; ?>">0</span>+</h2>
                    <p class="stat-label text-md-end">Satisfied Customers <br><span class="label-sub">across the state</span></p>
                </div>

                <!-- Services (Bottom Left) -->
                <div class="stat-premium-card card-left reveal-fade" data-delay="300">
                    <div class="stat-dot dot-success mx-auto mx-md-0 ms-md-auto"></div>
                    <h2 class="display-5 fw-bold text-md-end"><span class="counter-item" data-target="<?php echo $target_categories; ?>">0</span>+</h2>
                    <p class="stat-label text-md-end">Service Categories <br><span class="label-sub">for every need</span></p>
                </div>
            </div>

            <!-- CENTER COLUMN: MAP -->
            <div class="col-md-6 position-relative order-1 order-md-2 mb-5 mb-md-0">
                <div class="map-perspective reveal-fade" data-delay="500">
                    <!-- SVG Connection Lines -->
                    <svg class="map-connection-strings" viewBox="0 0 600 400" preserveAspectRatio="none">
                        <!-- Connecting Map Center (300, 200) to corners -->
                        
                        <!-- To Top Left (Customers) -->
                        <path d="M 300,200 Q 200,100 0,50" class="connection-string stroke-blue" />
                        
                        <!-- To Top Right (Workers) -->
                        <path d="M 300,200 Q 400,100 600,50" class="connection-string stroke-yellow" />
                        
                        <!-- To Bottom Left (Services) -->
                        <path d="M 300,200 Q 200,300 0,350" class="connection-string stroke-green" />
                        
                        <!-- To Bottom Right (24/7) -->
                        <path d="M 300,200 Q 400,300 600,350" class="connection-string stroke-red" />
                        
                        <circle cx="300" cy="200" r="6" fill="#f59e0b" class="pulse-dot" />
                    </svg>

                    <img src="<?php echo $path_prefix; ?>assets/img/wb_map_stats.png" alt="West Bengal Map" class="img-fluid wb-static-map shadow-premium">
                    
                    <!-- Map Overlay Badges -->
                    <div class="map-overlay-badge badge-1">
                        <span class="pulse-ring"></span>
                        <i class="fas fa-shield-alt me-2 text-primary"></i> 100% Secure
                    </div>
                    <div class="map-overlay-badge badge-2">
                        <span class="pulse-ring bg-warning"></span>
                        <i class="fas fa-bolt me-2 text-warning"></i> Fast Response
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Workers & 24/7 -->
            <div class="col-md-3 d-flex flex-column gap-5 position-relative z-2 order-3 order-md-3">
                <!-- Workers (Top Right) -->
                <div class="stat-premium-card card-right reveal-fade" data-delay="200">
                    <div class="stat-dot dot-warning mx-auto mx-md-0 me-md-auto"></div>
                    <h2 class="display-5 fw-bold text-md-start"><span class="counter-item" data-target="<?php echo $target_workers; ?>">0</span>+</h2>
                    <p class="stat-label text-md-start">Verified Workers <br><span class="label-sub">ready to serve</span></p>
                </div>

                <!-- 24/7 (Bottom Right) -->
                <div class="stat-premium-card card-right reveal-fade" data-delay="400">
                    <div class="stat-dot dot-danger mx-auto mx-md-0 me-md-auto"></div>
                    <h2 class="display-5 fw-bold text-md-start">
                        <span class="counter-item" data-target="24">0</span><span style="font-size: 0.7em;">/7</span>
                    </h2>
                    <p class="stat-label text-md-start">Live Support <br><span class="label-sub">available anytime</span></p>
                </div>
            </div>

        </div>
    </div>
</section>

<style>
/* Stats Redesign Visual System */
.stats-redesign {
    background-color: var(--bs-body-bg);
    position: relative;
    z-index: 1;
}

.text-gradient {
    background-image: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

[data-bs-theme="dark"] .text-gradient {
    background-image: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
}

.stat-premium-card {
    position: relative;
    padding: 10px;
    transition: all 0.3s ease;
}

.stat-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-bottom: 10px;
}

.dot-primary { background-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }
.dot-warning { background-color: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, 0.5); }
.dot-success { background-color: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
.dot-danger  { background-color: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }

.stat-label {
    font-weight: 700;
    color: var(--bs-body-color);
    line-height: 1.2;
}

.label-sub {
    font-size: 0.85rem;
    font-weight: 400;
    color: var(--bs-secondary-color);
}

/* Map Styling */
.map-perspective {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
}

.wb-static-map {
    max-height: 400px;
    border-radius: 30px;
    position: relative;
    z-index: 2; /* Map above lines */
    transition: transform 0.5s ease;
}

.shadow-premium {
    filter: drop-shadow(0 30px 60px rgba(0,0,0,0.15));
}

[data-bs-theme="dark"] .shadow-premium {
    filter: drop-shadow(0 30px 60px rgba(0,0,0,0.4));
}

/* Connection Strings */
.map-connection-strings {
    position: absolute;
    top: -50px;
    left: -50px;
    width: calc(100% + 100px);
    height: calc(100% + 100px);
    z-index: 1; /* Lines BEHIND map */
    pointer-events: none;
    overflow: visible;
}

.connection-string {
    fill: none;
    stroke-width: 2;
    opacity: 0.6;
    stroke-dasharray: 8, 8;
    animation: dashFlow 3s linear infinite;
}

/* Line Colors */
.stroke-blue   { stroke: #3b82f6; }
.stroke-yellow { stroke: #f59e0b; }
.stroke-green  { stroke: #10b981; }
.stroke-red    { stroke: #ef4444; }

@keyframes dashFlow {
    to {
        stroke-dashoffset: -100; /* Reverse flow: Map to Cards */
    }
}

.pulse-dot {
    animation: dotPulse 2s infinite;
}

/* Overlay Badges */
.map-overlay-badge {
    position: absolute;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0,0,0,0.1);
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    z-index: 3;
    display: flex;
    align-items: center;
    white-space: nowrap;
}

[data-bs-theme="dark"] .map-overlay-badge {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(255,255,255,0.1);
    color: white;
}

.badge-1 { bottom: 15%; left: -5%; }
.badge-2 { top: 15%; right: -5%; }

.pulse-ring {
    width: 6px;
    height: 6px;
    background-color: var(--bs-primary);
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
    flex-shrink: 0;
    position: relative;
}

.pulse-ring::after {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border: 2px solid inherit;
    border-radius: 50%;
    animation: badgePulse 2s infinite;
}

@keyframes badgePulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(2.5); opacity: 0; }
}

@media (max-width: 768px) {
    .map-connection-strings { display: none; } /* Hide complicated lines on mobile */
    .badge-1, .badge-2 { display: none; } /* Hide floating badges on mobile to prevent clutter */
    
    .stat-premium-card {
        text-align: center !important;
        padding: 20px;
        background: var(--bs-tertiary-bg);
        border-radius: 15px;
        margin-bottom: 10px;
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05); /* Added slight shadow for better separation */
    }
    
    .stat-dot { margin: 0 auto 10px auto !important; }
    .text-md-end, .text-md-start { text-align: center !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.2,
        rootMargin: "0px"
    };

    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = entry.target.getAttribute('data-delay') || 0;
                setTimeout(() => {
                    entry.target.classList.add('revealed');
                    
                    const counter = entry.target.querySelector('.counter-item');
                    if (counter) {
                        const target = parseInt(counter.getAttribute('data-target'));
                        animateCounter(counter, target);
                    }
                }, delay);
                statsObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal-fade').forEach(el => statsObserver.observe(el));

    function animateCounter(el, target) {
        if(target === 0) {
            el.innerText = '0';
            return;
        }
        let current = 0;
        const duration = 2000;
        const stepTime = 16;
        const steps = duration / stepTime;
        const increment = target / steps;
        
        // Ensure we at least increment by 1
        const safeIncrement = increment < 1 ? 1 : increment;

        const timer = setInterval(() => {
            current += safeIncrement;
            if (current >= target) {
                el.innerText = target.toLocaleString();
                clearInterval(timer);
            } else {
                el.innerText = Math.floor(current).toLocaleString();
            }
        }, stepTime);
    }
});
</script>
