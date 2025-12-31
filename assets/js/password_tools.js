/**
 * Labour On Demand - Password UX Tools
 * Handles Toggle Visibility and Strength Checking
 */

document.addEventListener('DOMContentLoaded', function () {

    // 1. Password Visibility Toggle
    const toggleButtons = document.querySelectorAll('.toggle-password');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // 2. Password Strength Checker
    const strengthInput = document.querySelector('.password-strength-check');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');

    if (strengthInput && strengthBar && strengthText) {
        strengthInput.addEventListener('input', function () {
            const val = this.value;
            let score = 0;

            // Criteria
            if (val.length >= 8) score++;
            if (val.match(/[A-Z]/)) score++;
            if (val.match(/[0-9]/)) score++;
            if (val.match(/[^a-zA-Z0-9]/)) score++;

            // Update UI
            let width = 0;
            let color = '#dc3545'; // red
            let text = 'Weak';

            switch (score) {
                case 1: width = 25; color = '#dc3545'; text = 'Weak'; break;
                case 2: width = 50; color = '#ffc107'; text = 'Medium'; break;
                case 3: width = 75; color = '#17a2b8'; text = 'Strong'; break;
                case 4: width = 100; color = '#28a745'; text = 'Very Strong'; break;
                default: width = 0; text = '';
            }

            strengthBar.style.width = width + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.innerText = text;
            strengthText.style.color = color;
        });
    }
});
