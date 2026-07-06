<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $redirect = match($_SESSION['role'] ?? '') {
        'cashier' => 'sale/pos.php',
        default => 'dashboard/index.php'
    };
    header("Location: $redirect");
    exit;
}

require_once 'config/database.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Email not found';
        } elseif ($user['status'] !== 'Active') {
            $error = 'Account is inactive. Contact administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            // Check if password is plain text
            if ($user['password'] === $password) {
                // Migrate plain text to hash
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed, $user['id']);
                $update->execute();
                $update->close();
            } else {
                $error = 'Wrong password';
            }
        }

        if (empty($error)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($remember) {
                setcookie('remember_email', $email, time() + 86400 * 30, '/');
            } else {
                setcookie('remember_email', '', time() - 3600, '/');
            }

            $redirect = match($user['role']) {
                'cashier' => 'sale/pos.php',
                default => 'dashboard/index.php'
            };
            header("Location: $redirect");
            exit;
        }
    }
}

$remembered_email = $_COOKIE['remember_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%); }
        .login-card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.97); }
        .input-field:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .toggle-pw { cursor: pointer; user-select: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="login-card rounded-2xl shadow-2xl p-8 md:p-10">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-indigo-200">
                    <i class="fas fa-cubes text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Smart Inventory</h1>
                <p class="text-gray-500 mt-1">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5" autocomplete="off">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-gray-400 mr-1"></i> Email
                    </label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($email ?: $remembered_email) ?>"
                        class="input-field w-full px-4 py-3 rounded-xl border border-gray-300 text-gray-900 placeholder-gray-400 transition-all"
                        placeholder="example@gmail.com">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-gray-400 mr-1"></i> Password
                    </label>
                    <div class="relative">
                        <input type="password" name="password" required id="password"
                            class="input-field w-full px-4 py-3 pr-12 rounded-xl border border-gray-300 text-gray-900 placeholder-gray-400 transition-all"
                            placeholder="Enter your password">
                        <button type="button" onclick="togglePassword()"
                            class="toggle-pw absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" value="1"
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            <?= $remembered_email ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-lg shadow-indigo-200">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400">
                    Default: admin@smartinventory.com / admin123
                </p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pw.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>
