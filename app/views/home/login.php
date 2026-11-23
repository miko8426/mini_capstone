<?php
use App\Helpers\Session;

Session::start();
$csrfToken = Session::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Eco Metrics - Carlos Hilado Memorial State University</title>
    <link rel="icon" type="image/jpeg" href="/assets/img/chmsu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --forest-green: #0c2d18;
            --emerald-green: #198754;
            --light-emerald: #50c878;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(6, 26, 13, 0.90) 0%, rgba(12, 45, 23, 0.88) 50%, rgba(8, 30, 16, 0.94) 100%),
                        url('/assets/img/chmsu-campus.jpg') center center / cover no-repeat fixed;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle ambient glow behind the floating card */
        .ambient-glow {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(80, 200, 120, 0.22) 0%, rgba(12, 45, 23, 0) 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 1;
        }

        /* Top Home Bar */
        .top-home-link {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 10;
        }

        .btn-home-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(14, 45, 28, 0.75);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(80, 200, 120, 0.3);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.15rem;
            border-radius: 50rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-home-pill:hover {
            background: rgba(25, 75, 45, 0.9);
            color: #ffffff;
            border-color: rgba(80, 200, 120, 0.6);
            transform: translateY(-2px);
        }

        /* Floating Login Card */
        .login-card-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 430px;
            padding: 1rem;
        }

        .login-floating-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 1.6rem;
            padding: 2.5rem 2.25rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45), 0 0 40px rgba(80, 200, 120, 0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: floatIn 0.5s ease-out;
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .brand-seal {
            width: 78px;
            height: 78px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid rgba(25, 135, 84, 0.2);
            padding: 3px;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            transition: transform 0.25s ease;
        }

        .brand-seal:hover {
            transform: scale(1.05) rotate(3deg);
        }

        .login-title {
            color: var(--forest-green);
            font-weight: 800;
            font-size: 1.45rem;
            letter-spacing: -0.01em;
            margin-top: 0.75rem;
            margin-bottom: 0.15rem;
        }

        .login-subtitle {
            color: #6b7280;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .input-group-text {
            background-color: #f9fafb;
            border-color: #e5e7eb;
            color: #6b7280;
            padding-left: 1rem;
            padding-right: 0.85rem;
            border-top-left-radius: 0.75rem;
            border-bottom-left-radius: 0.75rem;
        }

        .form-control {
            border-color: #e5e7eb;
            padding: 0.75rem 1rem;
            font-size: 0.94rem;
            border-radius: 0.75rem;
            background-color: #f9fafb;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--light-emerald);
            box-shadow: 0 0 0 3px rgba(80, 200, 120, 0.2);
        }

        .input-group .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .input-group .toggle-password {
            border-top-right-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
            border-color: #e5e7eb;
            background-color: #f9fafb;
            color: #6b7280;
            cursor: pointer;
        }

        .btn-signin {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            padding: 0.85rem 1.5rem;
            border-radius: 0.75rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.35);
        }

        .btn-signin:hover {
            background: linear-gradient(135deg, #157347 0%, #0e4c2f 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(25, 135, 84, 0.45);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .back-home-link {
            color: #4b5563;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: color 0.15s ease;
        }

        .back-home-link:hover {
            color: #198754;
        }

        .card-footer-note {
            font-size: 0.73rem;
            color: #9ca3af;
            text-align: center;
            margin-top: 1.25rem;
        }
    </style>
</head>
<body>

    <!-- Back to Home Floating Pill -->
    <div class="top-home-link">
        <a href="/" class="btn-home-pill">
            <i class="bi bi-arrow-left"></i>
            <span>Return to Homepage</span>
        </a>
    </div>

    <!-- Ambient Glow Effect -->
    <div class="ambient-glow"></div>

    <!-- Floating Login Card Screen -->
    <div class="login-card-container">
        <div class="login-floating-card">
            <!-- Header with Seal & Title -->
            <div class="text-center mb-4">
                <a href="/">
                    <img src="/assets/img/chmsu-logo.jpg" alt="CHMSU Logo" class="brand-seal">
                </a>
                <h3 class="login-title">Eco Metrics</h3>
                <div class="login-subtitle">Carlos Hilado Memorial State University</div>
                <div class="badge rounded-pill text-bg-light border px-3 py-1 mt-2 text-muted fw-semibold" style="font-size: 0.72rem;">
                    Energy &amp; Carbon Portal Sign In
                </div>
            </div>

            <!-- Sign In Form -->
            <form id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="emailInput" class="form-control" placeholder="name@chmsu.edu.ph" required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary toggle-password" type="button" id="togglePassword" title="Show password">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" id="submitBtn" class="btn btn-signin">
                        <span id="btnText"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
                        <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="text-center mt-3 pt-2">
                    <a href="/" class="back-home-link">
                        <i class="bi bi-house"></i>
                        <span>Back to Homepage</span>
                    </a>
                </div>

                <div class="card-footer-note">
                    Authorized institutional access only &bull; Protected by RBAC
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('toggleIcon');

        toggleBtn?.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye', !isPassword);
            toggleIcon.classList.toggle('bi-eye-slash', isPassword);
        });

        // Async AJAX Login
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');

            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');

            try {
                const formData = new FormData(e.target);
                const response = await fetch('/login', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.status === 'success') {
                    window.location.href = result.redirect;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sign In Failed',
                        text: result.message || 'Invalid email or password.',
                        confirmButtonColor: '#198754'
                    });
                    submitBtn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnSpinner.classList.add('d-none');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to reach the server. Please try again.',
                    confirmButtonColor: '#198754'
                });
                submitBtn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
            }
        });
    </script>
</body>
</html>
