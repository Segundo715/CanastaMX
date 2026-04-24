<?php
session_start();

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset();
    session_destroy();
    session_start();
}

if (isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === 'admin' && $password === '447jesus**') {
        $_SESSION['admin'] = 'admin';
        header('Location: index.php');
        exit;
    }

    $login_error = 'Usuario o contraseña incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin - CanastaMX</title>
<link rel="stylesheet" href="assets/style.css">
<style>
body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.login-box {
    width: 360px;
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}
.login-box h2 {
    margin: 0 0 0.8rem;
    font-size: 1.25rem;
    color: var(--text);
    text-align: center;
}
.login-info {
    text-align: center;
    margin-bottom: 1rem;
    color: var(--text2);
    font-size: 0.85rem;
    line-height: 1.4;
}
.input-group { margin-bottom: 0.9rem; }
.input-group input {
    width: 100%;
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.62rem;
    border-radius: 7px;
    outline: none;
    font-size: 0.96rem;
}
.input-group input:focus { border-color: var(--green); }
button {
    width: 100%;
    padding: 0.72rem;
    border: 1px solid var(--green);
    border-radius: 7px;
    background: var(--gd);
    color: var(--bg);
    font-weight: 600;
    cursor: pointer;
    letter-spacing: 0.02em;
}
button:hover { background: var(--green); color: #0d1117; }
.error {
    margin-bottom: 0.85rem;
    padding: 0.58rem;
    color: var(--red);
    border: 1px solid var(--red);
    border-radius: 7px;
    background: rgba(248,81,73,.12);
    text-align: center;
    font-size: 0.86rem;
}
</style>
</head>
<body>
<div class="login-box">
    <h2>Ingreso Admin CanastaMX</h2>
    <?php if ($login_error): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <div class="input-group">
            <input type="text" name="usuario" placeholder="Usuario" required autofocus>
        </div>
        <div class="input-group">
            <input type="password" name="password" placeholder="Contraseña" required>
        </div>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>
