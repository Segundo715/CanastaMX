<?php
session_start();

require_once __DIR__ . '/db/conexion.php';
$pdo = getDB();

if (isset($_SESSION['user_id'])) {
    header('Location: user_index.php');
    exit;
}

$isRegister = !isset($_GET['login']) || (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['mode'] ?? '') === 'register');
$login_error = '';
$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($mode === 'register') {
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$usuario || !$password || !$confirmPassword || !$email) {
            $register_error = 'Completa todos los campos para registrarte.';
        } elseif ($password !== $confirmPassword) {
            $register_error = 'Las contraseñas no coinciden.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = 'Ingresa un correo válido.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ?');
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                $register_error = 'El usuario ya existe. Elige otro nombre.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $register_error = 'Este correo ya está registrado.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO usuarios (usuario, email, password, rol) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$usuario, $email, password_hash($password, PASSWORD_DEFAULT), 'user']);
                    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                    $_SESSION['user_name'] = $usuario;
                    header('Location: user_index.php');
                    exit;
                }
            }
        }
    } else {
        if ($usuario && $password) {
            $stmt = $pdo->prepare('SELECT id, password FROM usuarios WHERE usuario = ? AND rol = ?');
            $stmt->execute([$usuario, 'user']);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $usuario;
                header('Location: user_index.php');
                exit;
            }
        }
        $login_error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Usuario - CanastaMX</title>
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
.input-group { 
    position: relative;
    margin-bottom: 0.9rem; 
}
.input-group input {
    width: 100%;
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.62rem 2.5rem 0.62rem 0.62rem;
    border-radius: 7px;
    outline: none;
    font-size: 0.96rem;
}
.input-group input:focus { border-color: var(--green); }
.eye-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 1.2rem;
    color: var(--text2);
}
.eye-icon:hover { color: var(--text); }
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
.login-link {
    display: block;
    text-align: center;
    margin-top: 1rem;
    color: var(--text2);
    text-decoration: none;
}
.login-link:hover { color: var(--text); }
</style>
</head>
<body>
<div class="login-box">
    <h2><?= $isRegister ? 'Registro Usuario CanastaMX' : 'Ingreso Usuario CanastaMX' ?></h2>
    <div class="login-info">
        <?= $isRegister ? 'Crea tu cuenta de consumidor para recibir alertas y monitorear precios.' : 'Ingresa con tu usuario de consumidor.' ?>
    </div>

    <?php if ($login_error): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <?php if ($register_error): ?>
        <div class="error"><?= htmlspecialchars($register_error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="mode" value="<?= $isRegister ? 'register' : 'login' ?>">
        <div class="input-group">
            <input type="text" name="usuario" placeholder="Usuario" required autofocus>
        </div>
        <div class="input-group">
            <input type="password" name="password" placeholder="Contraseña" required>
            <span class="eye-icon">👁️</span>
        </div>
        <?php if ($isRegister): ?>
            <div class="input-group">
                <input type="email" name="email" placeholder="Correo electrónico" required>
            </div>
            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Repite la contraseña" required>
                <span class="eye-icon">👁️</span>
            </div>
        <?php endif; ?>
        <button type="submit"><?= $isRegister ? 'Crear cuenta' : 'Entrar' ?></button>
    </form>

    <a class="login-link" href="<?= $isRegister ? 'user_login.php?login=1' : 'user_login.php' ?>">
        <?= $isRegister ? '¿Ya tienes cuenta? Inicia sesión aquí' : '¿Eres nuevo? Crea tu usuario aquí' ?>
    </a>
    <a class="login-link" href="login.php">¿Eres admin? Inicia sesión aquí</a>
</div>
<script>
document.querySelectorAll('.eye-icon').forEach(icon => {
    icon.addEventListener('click', () => {
        const input = icon.previousElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = '🙈';
        } else {
            input.type = 'password';
            icon.textContent = '👁️';
        }
    });
});
</script>
</body>
</html>
