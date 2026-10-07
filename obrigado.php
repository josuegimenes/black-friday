<?php
$name = $_GET['name'] ?? 'Lojista visionário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva confirmada | Vésteme</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<section class="success-screen">
    <div class="success-card">
        <p class="badge">89% completo</p>
        <h1>Reserva confirmada, <?= htmlspecialchars($name) ?>!</h1>
        <p>
            Obrigado por responder e travar sua posição na maior black da Vésteme. Carla acabou de liberar
            seu acesso ao grupo com os bastidores da negociação e os links da virada do dia 28/11.
        </p>
        <p>Entre agora no grupo exclusivo:</p>
        <a href="https://t.me/" target="_blank" rel="noopener">Acessar o grupo VIP</a>
        <p style="margin-top:24px; color:#9ea1b7;">Se precisar editar seu carrinho, basta retornar à categoria e reenviar o formulário.</p>
    </div>
</section>
<script src="assets/js/main.js"></script>
</body>
</html>
