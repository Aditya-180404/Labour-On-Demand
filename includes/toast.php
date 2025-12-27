<?php
/**
 * Toast Notification Component
 * Displays session-based alerts as modern Bootstrap 5 toasts
 */
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

$toast_success = $_SESSION['toast_success'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$toast_info = $_SESSION['toast_info'] ?? null;

// Clear session messages after fetching
unset($_SESSION['toast_success'], $_SESSION['toast_error'], $_SESSION['toast_info']);
?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <!-- Success Toast -->
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successToastMsg"><?php echo htmlspecialchars($toast_success ?? ''); ?></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <!-- Error Toast -->
    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="errorToastMsg"><?php echo htmlspecialchars($toast_error ?? ''); ?></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <!-- Info Toast -->
    <div id="infoToast" class="toast align-items-center text-white bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-info-circle me-2"></i>
                <span id="infoToastMsg"><?php echo htmlspecialchars($toast_info ?? ''); ?></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = "<?php echo $toast_success ? addslashes($toast_success) : ''; ?>";
    const errorMsg = "<?php echo $toast_error ? addslashes($toast_error) : ''; ?>";
    const infoMsg = "<?php echo $toast_info ? addslashes($toast_info) : ''; ?>";

    if (successMsg) {
        const toast = new bootstrap.Toast(document.getElementById('successToast'));
        toast.show();
    }
    if (errorMsg) {
        const toast = new bootstrap.Toast(document.getElementById('errorToast'));
        toast.show();
    }
    if (infoMsg) {
        const toast = new bootstrap.Toast(document.getElementById('infoToast'));
        toast.show();
    }
});

/**
 * JS Helper to trigger toasts manually
 */
function showToast(message, type = 'success') {
    const toastId = type + 'Toast';
    const msgId = type + 'ToastMsg';
    const toastEl = document.getElementById(toastId);
    if (!toastEl) return;
    
    document.getElementById(msgId).textContent = message;
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}
</script>
