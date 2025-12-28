<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

// Fetch counts
$stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
$actual_users = $stmt_users->fetchColumn();

$stmt_workers = $pdo->query("SELECT COUNT(*) FROM workers");
$actual_workers = $stmt_workers->fetchColumn();

// Targets (User requested 5000 and 500 as floor)
$target_users = max(5000, $actual_users);
$target_workers = max(500, $actual_workers);
?>

<section id="stats-counter" class="py-5 bg-body-tertiary">
    <div class="container text-center">
        <div class="row g-4 justify-content-center">
            <!-- User Stats -->
            <div class="col-md-5 col-lg-4">
                <div class="stats-card p-4 rounded-4 shadow-sm bg-body transition-all border border-primary border-opacity-10 h-100 d-flex flex-column align-items-center justify-content-center">
                    <div class="icon-blob bg-primary bg-opacity-10 text-primary mb-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <h2 class="display-4 fw-bold mb-1 text-primary counter-number" data-target="<?php echo $target_users; ?>">0</h2>
                    <p class="text-muted fw-semibold mb-0">Satisfied Customers</p>
                    <div class="stat-progress mt-3">
                        <div class="progress-dot dot-1"></div>
                        <div class="progress-dot dot-2"></div>
                        <div class="progress-dot dot-3"></div>
                    </div>
                </div>
            </div>

            <!-- Worker Stats -->
            <div class="col-md-5 col-lg-4">
                <div class="stats-card p-4 rounded-4 shadow-sm bg-body transition-all border border-warning border-opacity-10 h-100 d-flex flex-column align-items-center justify-content-center">
                    <div class="icon-blob bg-warning bg-opacity-10 text-warning mb-3">
                        <i class="fas fa-hard-hat fa-2x"></i>
                    </div>
                    <h2 class="display-4 fw-bold mb-1 text-warning counter-number" data-target="<?php echo $target_workers; ?>">0</h2>
                    <p class="text-muted fw-semibold mb-0">Verified Workers</p>
                    <div class="stat-progress mt-3">
                        <div class="progress-dot dot-1"></div>
                        <div class="progress-dot dot-2"></div>
                        <div class="progress-dot dot-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.stats-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

.icon-blob {
    width: 70px;
    height: 70px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: blobMorph 8s ease-in-out infinite;
}

@keyframes blobMorph {
    0%, 100% { border-radius: 20px 40px 20px 40px; }
    50% { border-radius: 40px 20px 40px 20px; }
}

.stat-progress {
    display: flex;
    gap: 5px;
}

.progress-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.3;
}

.progress-dot.dot-1 { animation: dotPulse 1.5s infinite; }
.progress-dot.dot-2 { animation: dotPulse 1.5s infinite 0.2s; }
.progress-dot.dot-3 { animation: dotPulse 1.5s infinite 0.4s; }

@keyframes dotPulse {
    0%, 100% { transform: scale(1); opacity: 0.3; }
    50% { transform: scale(1.5); opacity: 0.8; }
}

/* Theme Awareness Override */
[data-bs-theme="dark"] .stats-card {
    background-color: #1a1f3a !important;
    border-color: rgba(255,255,255,0.05) !important;
}

/* Animation trigger */
.counter-number {
    opacity: 0;
    transform: scale(0.9);
    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
}

.counter-number.visible {
    opacity: 1;
    transform: scale(1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statsSection = document.getElementById('stats-counter');
    const counters = document.querySelectorAll('.counter-number');
    let animated = false;

    const animateCounters = () => {
        counters.forEach(counter => {
            counter.classList.add('visible');
            const target = parseInt(counter.getAttribute('data-target'));
            const duration = 2000; // 2 seconds
            const step = Math.ceil(target / (duration / 16)); // ~60fps
            
            let current = 0;
            const update = () => {
                current += step;
                if (current < target) {
                    counter.innerText = current.toLocaleString();
                    requestAnimationFrame(update);
                } else {
                    counter.innerText = target.toLocaleString() + '+';
                }
            };
            update();
        });
    };

    // Intersection Observer to start animation when visible
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !animated) {
            animateCounters();
            animated = true;
        }
    }, { threshold: 0.5 });

    observer.observe(statsSection);
});
</script>
