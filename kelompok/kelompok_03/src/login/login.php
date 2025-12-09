<?php
session_start();

// Use central DB helper
require_once __DIR__ . '/../config/db.php';

if ($_POST) {
    try {
        $dbh = get_db();
        $stmt = $dbh->prepare(
            "SELECT id, username, password, 'admin' as role FROM admin WHERE username = :u " .
            "UNION SELECT id, username, password, 'anggota' as role FROM anggota WHERE username = :u2"
        );
        // execute with named params
        $stmt->execute(['u' => $_POST['username'], 'u2' => $_POST['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $inputPwd = (string)($_POST['password'] ?? '');
        $storedPwd = isset($user['password']) ? (string)$user['password'] : '';

        $isValid = false;
        if ($user && $storedPwd !== '') {
            // Accept either a bcrypt/hashed password or plain-text password (legacy)
            if (function_exists('password_verify') && @password_verify($inputPwd, $storedPwd)) {
                $isValid = true;
            } elseif ($inputPwd === $storedPwd) {
                $isValid = true;
            }
        }

        if ($isValid) {
            // store minimal user info in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // keep a compact user array for convenience
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            header('Location: ' . ($user['role'] === 'admin' ? '../admin/anggota.php' : 'anggota/profil.php'));
            exit;
        } else {
            $error = "Username atau password salah.";
        }
    } catch (Exception $ex) {
        $error = 'Gagal melakukan login: ' . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Tics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: { primary: '#0466c8', canvas: '#F4F7FE', dark: '#2B3674', muted: '#A3AED0' }
                }
            }
        }
    </script>
    <style>body { background: #F4F7FE; font-family: Poppins, sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center text-xl shadow-lg">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-dark">Tics<span class="text-primary">Org</span></h1>
                    <p class="text-sm text-muted">Masuk untuk mengelola sistem</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-muted mb-1">Username</label>
                    <input name="username" type="text" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Masukkan username">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-muted mb-1">Password</label>
                    <input name="password" type="password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Masukkan password">
                </div>
                <div>
                    <button type="submit" class="w-full bg-primary text-white font-semibold py-3 rounded-xl shadow">Masuk</button>
                </div>
            </form>

            <p class="mt-4 text-xs text-muted text-center">Belum punya akun? Hubungi administrator.</p>
        </div>
    </div>
</body>
</html>