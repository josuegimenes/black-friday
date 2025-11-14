<?php
session_start();

$adminConfigPath = __DIR__ . '/../config/admin.php';
if (!is_file($adminConfigPath)) {
    http_response_code(500);
    echo 'Arquivo de configuração de administrador não encontrado.';
    exit;
}

$adminConfig = require $adminConfigPath;

if (!empty($_SESSION['admin_authenticated'])) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($user === ($adminConfig['user'] ?? '') && $password === ($adminConfig['password'] ?? '')) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Credenciais inválidas. Tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Admin | Login</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="icon" type="image/jpeg" href="../favicon.ico">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <p class="badge">Área restrita</p>
            <h1>Painel Administrativo</h1>

            <?php if ($error): ?>
                <p style="color:#ff6b6b; margin-bottom: 18px;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-field">
                    <label for="user">Usuário</label>
                    <input type="text" id="user" name="user" required autofocus>
                </div>

                <div class="form-field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
