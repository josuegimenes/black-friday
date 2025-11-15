<?php
$name = $_GET['name'] ?? 'Lojista visionario';
$progress = 89;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva confirmada | Vésteme</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/jpeg" href="favicon.ico">
</head>
<body>
<section class="success-screen">
    <div class="success-card">
        <div class="progress-shell" role="img" aria-label="Etapa <?= $progress ?> por cento concluida">
            <span class="progress-label"><?= $progress ?>% concluido - finalize entrando no grupo VIP e aguarde nosso aviso com o link de pagamento.</span>
            <div class="progress-track">
                <div class="progress-fill" style="--progress: <?= $progress ?>%;"></div>
            </div>
        </div>
        <h1>Reserva confirmada, <br><?= htmlspecialchars($name) ?>!</h1>
        <p>
            Obrigado por travar sua posição na maior Black da Vésteme. Estamos organizando os pedidos por ordem de cadastro e você recebera todas as atualizações no WhatsApp ou por e-mail.
        </p>
        <p>Entre agora no grupo exclusivo:</p>
        <a href="https://chat.whatsapp.com/CrFlwHsXKoOKTkX9kqvdJM" target="_blank" rel="noopener">Acessar o grupo VIP no WhatsApp</a>
        <p style="margin-top:24px; color:#9ea1b7;">Para editar o carrinho, retorne a categoria desejada e envie o formulario novamente.</p>
    </div>
</section>
<script src="assets/js/main.js"></script>
</body>
</html>
