<?php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $pin_code = trim($_POST['pin_code']);
    $address_details = trim($_POST['address_details']);
    $location = trim($_POST['location']);

    if (empty($name) || empty($email) || empty($password) || empty($pin_code) || empty($address_details)) {
        echo "Please fill all required fields.";
        exit;
    }

    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        echo "Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.";
        exit;
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        echo "Phone number must be exactly 10 digits.";
        exit;
    } elseif (!preg_match('/^\d{6}$/', $pin_code)) {
        echo "PIN code must be exactly 6 digits.";
        exit;
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        echo "Email already exists.";
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name, email, password, phone, pin_code, address_details, location) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$name, $email, $hashed_password, $phone, $pin_code, $address_details, $location])) {
        // Auto login
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        header("Location: ../index.php");
        exit;
    } else {
        echo "Something went wrong. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/register.css">
    <script src="../assets/js/theme.js"></script>
    <style>
        .password-container { position: relative; }
        .toggle-password { position: absolute; right: 10px; top: 38px; cursor: pointer; color: #6c757d; }
        
        .password-requirements { list-style: none; padding: 0; margin-bottom: 0; font-size: 0.85rem; }
        .password-requirements li { margin-bottom: 3px; transition: color 0.3s ease; }
        .password-requirements li.invalid { color: #dc3545; } /* Red */
        .password-requirements li.valid { color: #198754; font-weight: bold; } /* Green */
        .password-requirements li i { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container py-4 px-3">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8 col-sm-11">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3>Customer Registration</h3>
                    </div>
                    <div class="card-body">
                        <form action="register.php" method="POST" id="registerForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="text" class="form-control" id="phone" name="phone" required placeholder="For OTP verification" pattern="\d{10}" maxlength="10" title="Phone number must be exactly 10 digits">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pin_code" class="form-label">PIN Code *</label>
                                    <input type="text" class="form-control" id="pin_code" name="pin_code" required maxlength="6" pattern="\d{6}" title="PIN code must be exactly 6 digits">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Area / Location</label>
                                    <input type="text" class="form-control" id="location" name="location">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address_details" class="form-label">Detailed Address *</label>
                                <textarea class="form-control" id="address_details" name="address_details" rows="2" required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3 password-container">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                    <ul class="password-requirements mt-2">
                                        <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                        <li id="req-lower" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                        <li id="req-upper" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                        <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                        <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> At least one special character</li>
                                    </ul>
                                </div>
                                <div class="col-md-6 mb-3 password-container">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                                    <div id="passwordMatchMsg" class="small mt-2"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small>Already have an account? <a href="login.php">Login here</a></small> <br>
                        <small>Are you a worker? <a href="../worker/register.php">Register as Worker</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const passwordMatchMsg = document.getElementById('passwordMatchMsg');

        // Toggle Password Visibility
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Password Match Checker
        function checkMatch() {
            if (confirmPasswordInput.value === "") {
                passwordMatchMsg.innerHTML = "";
            } else if (passwordInput.value === confirmPasswordInput.value) {
                passwordMatchMsg.innerHTML = "<i class='fas fa-check-circle text-success'></i> Passwords match";
                passwordMatchMsg.className = "small mt-2 text-success";
            } else {
                passwordMatchMsg.innerHTML = "<i class='fas fa-times-circle text-danger'></i> Passwords do not match";
                passwordMatchMsg.className = "small mt-2 text-danger";
            }
        }

        passwordInput.addEventListener('input', checkMatch);
        confirmPasswordInput.addEventListener('input', checkMatch);

        // Password Requirement Checker
        passwordInput.addEventListener('input', function () {
            const val = passwordInput.value;
            
            const requirements = [
                { id: 'req-length', valid: val.length >= 8 },
                { id: 'req-lower', valid: /[a-z]/.test(val) },
                { id: 'req-upper', valid: /[A-Z]/.test(val) },
                { id: 'req-number', valid: /\d/.test(val) },
                { id: 'req-special', valid: /[@$!%*?&]/.test(val) } // You can expand this regex if needed
            ];

            requirements.forEach(req => {
                const el = document.getElementById(req.id);
                if (req.valid) {
                    el.classList.remove('invalid');
                    el.classList.add('valid');
                    el.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    el.classList.remove('valid');
                    el.classList.add('invalid');
                    el.querySelector('i').className = 'fas fa-times-circle';
                }
            });
        });
    </script>
    <script>
        // Real-time Validation Helper
        function setupValidation(input, validateFn, errorMsg) {
            if (!input) return;

            // Check if error message div already exists
            let errorDiv = input.parentNode.querySelector('.invalid-feedback-custom');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback invalid-feedback-custom';
                errorDiv.style.display = 'none';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '0.875em';
                errorDiv.style.marginTop = '0.25rem';
                input.parentNode.appendChild(errorDiv);
            }

            const validate = () => {
                const isValid = validateFn(input.value);
                if (!isValid && input.value !== '') {
                    input.classList.add('is-invalid');
                    errorDiv.innerText = errorMsg;
                    errorDiv.style.display = 'block';
                } else {
                    input.classList.remove('is-invalid');
                    errorDiv.style.display = 'none';
                }
            };

            input.addEventListener('input', validate);
            input.addEventListener('blur', validate);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Phone Validation
            const phoneInput = document.getElementById('phone');
            setupValidation(phoneInput, (val) => /^\d{10}$/.test(val), 'Phone number must be exactly 10 digits.');
            // Restrict phone input to numbers only
            if(phoneInput) {
                phoneInput.addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); });
            }

            // 2. Pin Code Validation
            const pinInput = document.getElementById('pin_code');
            setupValidation(pinInput, (val) => /^\d{6}$/.test(val), 'PIN code must be exactly 6 digits.');
            // Restrict pin input to numbers only
            if(pinInput) {
                pinInput.addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); });
            }
        });
    </script>
</body>
</html>
