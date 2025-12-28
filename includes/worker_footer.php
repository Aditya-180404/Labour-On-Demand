<?php
// Use $path_prefix if defined, otherwise default to '../' for worker context
$base_path = $path_prefix ?? '../';

// Get worker data for pre-filling
$pre_name = '';
$pre_email = '';
$user_type = 'Worker';

if (isset($_SESSION['worker_id'])) {
    $pre_name = $_SESSION['worker_name'] ?? '';
    $pre_email = $_SESSION['worker_email'] ?? '';
}
?>
<?php if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.'); ?>
<footer class="mt-5 py-5 bg-dark text-white border-top border-secondary">
    <div class="container">
        <div class="row g-4">
            <!-- Company Info -->
            <div class="col-lg-4 col-md-6">
                <h5 class="fw-bold mb-4 text-primary d-flex align-items-center">
                    <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                        <i class="fas fa-briefcase fa-sm"></i>
                    </div>
                    LabourPro <span class="ms-1 small opacity-50 fw-normal" style="font-size: 0.6em;">WORKER PANEL</span>
                </h5>
                <p class="opacity-75">Empowering professional service providers to manage their business, track schedules, and grow their local reputation.</p>
                <div class="d-flex gap-3 mt-4 text-white-50">
                    <a href="#" class="text-reset"><i class="fab fa-facebook-f fa-lg"></i></a>
                    <a href="#" class="text-reset"><i class="fab fa-twitter fa-lg"></i></a>
                    <a href="#" class="text-reset"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" class="text-reset"><i class="fab fa-linkedin-in fa-lg"></i></a>
                </div>
            </div>

            <!-- Professional Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold mb-4 text-white">Dashboard</h6>
                <ul class="list-unstyled opacity-75">
                    <li class="mb-2"><a href="<?php echo $base_path; ?>worker/dashboard.php" class="text-reset text-decoration-none">Command Center</a></li>
                    <li class="mb-2"><a href="<?php echo $base_path; ?>worker/my_jobs.php" class="text-reset text-decoration-none">Job Panel</a></li>
                    <li class="mb-2"><a href="<?php echo $base_path; ?>worker/edit_profile.php" class="text-reset text-decoration-none">Profile Settings</a></li>
                    <li class="mb-2"><a href="<?php echo $base_path; ?>logout.php" class="text-reset text-decoration-none text-danger">Logout</a></li>
                </ul>
            </div>

            <!-- Worker Support -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold mb-4 text-white">Resources</h6>
                <ul class="list-unstyled opacity-75">
                    <li class="mb-2"><a href="#" class="text-reset text-decoration-none">Worker FAQ</a></li>
                    <li class="mb-2"><a href="#" class="text-reset text-decoration-none">Success Guide</a></li>
                    <li class="mb-2"><a href="#" class="text-reset text-decoration-none">Privacy & Safety</a></li>
                    <li class="mb-2"><a href="#" class="text-reset text-decoration-none text-info">Contact Support</a></li>
                </ul>
            </div>

            <!-- Feedback Section -->
            <div class="col-lg-4 col-md-6">
                <h6 class="fw-bold mb-4 text-white">Provider Feedback</h6>
                <?php if(!isset($_SESSION['worker_id'])): ?>
                    <div class="card border-0 rounded-4" style="background: rgba(255,255,255,0.05);">
                        <div class="card-body p-4 text-center">
                            <i class="fas fa-lock fa-2x text-white-50 mb-3"></i>
                            <h6 class="text-white fw-bold mb-2">Login Required</h6>
                            <p class="small text-white-50 mb-4">Please login to your provider account to share feedback.</p>
                            <a href="<?php echo $base_path; ?>worker/login.php" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold">Login Now</a>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="small opacity-75 mb-3 text-white-50">Your input helps us improve the platform for all workers.</p>
                    <form id="workerFeedbackForm" class="row g-2">
                        <input type="hidden" name="sender_type" value="worker">
                        <div class="col-6">
                            <input type="text" name="name" class="form-control form-control-sm border-0 bg-white bg-opacity-10 text-white" placeholder="Name" value="<?php echo htmlspecialchars($pre_name); ?>" required>
                        </div>
                        <div class="col-6">
                            <input type="email" name="email" class="form-control form-control-sm border-0 bg-white bg-opacity-10 text-white" placeholder="Email" value="<?php echo htmlspecialchars($pre_email); ?>" required>
                        </div>
                        <div class="col-12">
                            <input type="text" name="subject" class="form-control form-control-sm border-0 bg-white bg-opacity-10 text-white" placeholder="Subject (e.g. App Issue, Suggestions)" required>
                        </div>
                        <div class="col-12">
                            <textarea name="message" class="form-control form-control-sm border-0 bg-white bg-opacity-10 text-white" rows="3" placeholder="Tell us what's on your mind..." required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning btn-sm w-100 rounded-pill fw-bold">
                                <i class="fas fa-paper-plane me-2"></i>Send Feedback
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                             <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                        </div>
                        <div class="col-12 text-center mt-2">
                            <small class="text-white-50" style="font-size: 0.75rem;">
                                Messaging as <span class="text-warning fw-bold">Pro Provider</span>
                            </small>
                        </div>
                    </form>
                    <div id="workerFeedbackStatus" class="mt-2 small"></div>
                <?php endif; ?>
            </div>
        </div>
        <hr class="my-5 opacity-10">
        <div class="text-center text-white-50 small">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Labour Pro. Service Provider Division.</p>
        </div>
    </div>
</footer>

<script>
document.getElementById('workerFeedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const status = document.getElementById('workerFeedbackStatus');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    
    const formData = new FormData(form);
    formData.append('g-recaptcha-response', grecaptcha.getResponse());
    
    fetch('<?php echo $base_path; ?>process_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            status.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Sent successfully!</span>';
            form.reset();
        } else {
            status.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> ' + data.message + '</span>';
        }
    })
    .catch(error => {
        status.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Connection error.</span>';
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Feedback';
        setTimeout(() => { status.innerHTML = ''; }, 5000);
    });
});
</script>

<?php include __DIR__ . '/lightbox.php'; ?>
<?php include __DIR__ . '/toast.php'; ?>
