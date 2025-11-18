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
<script>
// Confetes neon ao carregar a página
(function () {
    const colors = ['#ff007f', '#46fcb4', '#ffeb3b', '#40bfff'];
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.className = 'confetti-canvas';
    canvas.style.position = 'fixed';
    canvas.style.inset = 0;
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = 9999;
    document.body.appendChild(canvas);

    let confetti = [];
    let width = window.innerWidth;
    let height = window.innerHeight;

    const resize = () => {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
    };
    resize();
    window.addEventListener('resize', resize);

    const random = (min, max) => Math.random() * (max - min) + min;

    const init = (count = 120) => {
        confetti = Array.from({ length: count }).map(() => ({
            x: random(0, width),
            y: random(-height, 0),
            w: random(6, 10),
            h: random(10, 18),
            r: random(0, Math.PI * 2),
            color: colors[Math.floor(random(0, colors.length))],
            speed: random(2, 4),
            swing: random(0.5, 1.5),
        }));
    };

    const update = () => {
        ctx.clearRect(0, 0, width, height);
        confetti.forEach((c) => {
            c.y += c.speed;
            c.x += Math.sin(c.y * 0.01) * c.swing;
            c.r += 0.02;
            if (c.y > height) {
                c.y = random(-height, 0);
                c.x = random(0, width);
            }
            ctx.save();
            ctx.translate(c.x, c.y);
            ctx.rotate(c.r);
            ctx.fillStyle = c.color;
            ctx.fillRect(-c.w / 2, -c.h / 2, c.w, c.h);
            ctx.restore();
        });
        requestAnimationFrame(update);
    };

    init();
    update();

    // remove apos 6 segundos para nao atrapalhar
    setTimeout(() => {
        canvas.remove();
        window.removeEventListener('resize', resize);
    }, 6000);
})();
</script>
</body>
</html>
