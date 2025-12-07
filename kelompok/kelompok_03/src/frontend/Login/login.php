<?php
session_start();
if ($_POST) {
    include 'includes/db.php';
    $stmt = $pdo->prepare("
        SELECT id, username, password, 'admin' as role FROM admin WHERE username = ?
        UNION
        SELECT id, username, password, 'anggota' as role FROM anggota WHERE username = ?
    ");
    $stmt->execute([$_POST['username'], $_POST['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'anggota/profil.php'));
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>body{font-family:sans-serif;background:#f1f5f9;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}form{background:white;padding:30px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.1);width:320px}h2{text-align:center}input{width:100%;padding:10px;margin:8px 0;border:1px solid #ccc;border-radius:6px}button{width:100%;padding:10px;background:#4F46E5;color:white;border:none;border-radius:6px;cursor:pointer}button:hover{opacity:0.9}.error{color:red;text-align:center}</style>
</head>
<body>
    <form method="POST">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Masuk</button>
    </form>
</body>
</html>