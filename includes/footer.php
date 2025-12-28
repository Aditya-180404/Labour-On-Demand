<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

// Determine the base path for links - relying on parent script to define $path_prefix
$base_path = isset($path_prefix) ? $path_prefix : '';

// Get user/worker data for pre-filling
$pre_name = '';
$pre_email = '';
$user_type = 'Guest';

if (isset($_SESSION['user_id'])) {
    $pre_name = $_SESSION['user_name'] ?? '';
    $pre_email = $_SESSION['user_email'] ?? '';
    $user_type = 'Customer';
} elseif (isset($_SESSION['worker_id'])) {
    $pre_name = $_SESSION['worker_name'] ?? '';
    $pre_email = $_SESSION['worker_email'] ?? '';
    $user_type = 'Worker';
}
?>
<footer class="mt-5 py-5 bg-body-tertiary border-top">
    <div class="container">
        <div class="row g-4">
            <!-- Company Info -->
            <div class="col-lg-4 col-md-6">
                <h5 class="fw-bold mb-4 text-primary">Labour On Demand</h5>
                <p class="text-muted">Connecting you with reliable local workers for all your home and business needs. Quality service, at your doorstep.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="text-muted"><i class="fab fa-facebook-f fa-lg"></i></a>
                    <a href="#" class="text-muted"><i class="fab fa-twitter fa-lg"></i></a>
                    <a href="#" class="text-muted"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" class="text-muted"><i class="fab fa-linkedin-in fa-lg"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold mb-4">Quick Links</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo $base_path; ?>index.php" class="text-muted text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="<?php echo $base_path; ?>customer/services.php" class="text-muted text-decoration-none">Services</a></li>
                    <li class="mb-2"><a href="<?php echo $base_path; ?>customer/workers.php" class="text-muted text-decoration-none">Find Workers</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">About Us</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold mb-4">Support</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">FAQ</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Contact Us</a></li>
                </ul>
            </div>

            <!-- Feedback Section -->
            <div class="col-lg-4 col-md-6">
                <h6 class="fw-bold mb-4">Send us Feedback</h6>
                <?php if($user_type === 'Guest'): ?>
                    <div class="card border-0 shadow-sm rounded-4 bg-body-tertiary">
                        <div class="card-body p-4 text-center">
                            <i class="fas fa-lock fa-2x text-muted mb-3"></i>
                            <h6 class="fw-bold mb-2">Want to share feedback?</h6>
                            <p class="small text-muted mb-4">Only logged-in customers can send us their thoughts.</p>
                            <a href="<?php echo $base_path; ?>customer/login.php" class="btn btn-primary btn-sm rounded-pill px-4">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <form id="feedbackForm" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="sender_type" value="customer">
                        <div class="col-6">
                            <input type="text" name="name" class="form-control form-control-sm bg-body" placeholder="Your Name" value="<?php echo htmlspecialchars($pre_name); ?>" required>
                        </div>
                        <div class="col-6">
                            <input type="email" name="email" class="form-control form-control-sm bg-body" placeholder="Your Email" value="<?php echo htmlspecialchars($pre_email); ?>" required>
                        </div>
                        <div class="col-12">
                            <input type="text" name="subject" class="form-control form-control-sm bg-body" placeholder="Subject" required>
                        </div>
                        <div class="col-12">
                            <textarea name="message" class="form-control form-control-sm bg-body" rows="3" placeholder="Your Feedback" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill">
                                <i class="fas fa-paper-plane me-2"></i>Send Feedback
                            </button>
                        </div>
                        <div class="col-12 text-center mt-2">
                            <small class="text-muted" style="font-size: 0.75rem;">
                                Sending as <span class="text-primary fw-bold"><?php echo $user_type; ?></span>
                            </small>
                        </div>
                        <div class="col-12">
                             <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                        </div>
                        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                    </form>
                    <div id="feedbackStatus" class="mt-2 small"></div>
                <?php endif; ?>
            </div>
        </div>
        <hr class="my-5 opacity-10">
        <div class="text-center text-muted small">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Labour On Demand. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const status = document.getElementById('feedbackStatus');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    
    const formData = new FormData(form);
    formData.append('g-recaptcha-response', grecaptcha.getResponse());
    
    fetch('<?php echo $base_path; ?>process_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> ' + data.message + '</span>';
            form.reset();
        } else {
            status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + data.message + '</span>';
        }
    })
    .catch(error => {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Error submitting feedback.</span>';
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Feedback';
        setTimeout(() => { if(status.innerHTML.includes('Success')) status.innerHTML = ''; }, 5000);
    });
});
</script>

<?php include __DIR__ . '/lightbox.php'; ?>
<?php include __DIR__ . '/toast.php'; ?>
