<?php
$catalog = require __DIR__ . '/data/catalog.php';

$categoryIllustrations = [
    'vestidos-longos' => 'vestidos-longos-white.png',
    'vestidos-curtos' => 'vestidos-curtos-white.png',
    'conjuntos' => 'conjuntos-white.png',
    'blusas' => 'blusas-white.png',
    'saias' => 'saias-white.png',
    'calcas' => 'calcas-white.png',
    'shorts' => 'shorts-white.png',
    'macaquinhos' => 'macaquinhos-white.png',
];

$faqItems = [
    [
        'question' => 'O que é a Maior Black da Vésteme?',
        'answer' => 'É o acesso oficial ao nosso estoque de atacado liberado para o varejo com descontos de até 80% OFF enquanto houver disponibilidade e por ordem de cadastro.',
    ],
    [
        'question' => 'Quando acontece a Black?',
        'answer' => 'A campanha segue até 28/11 ou até acabarem os lotes. Quanto antes você monta o pedido, maior a prioridade na liberação.',
    ],
    [
        'question' => 'Como funciona o processo?',
        'answer' => 'Monte o carrinho sem pagamento, valide o interesse no grupo oficial e aguarde o link de pagamento conforme a fila de cadastro.',
    ],
    [
        'question' => 'Quais peças participam?',
        'answer' => 'Selecionamos as oito categorias líderes do atacado Vésteme: vestidos longos e curtos, conjuntos, blusas, saias, calças, shorts e macaquinhos.',
    ],
    [
        'question' => 'Tem frete grátis?',
        'answer' => 'Oferecemos fretes promocionais e combos com frete grátis para pedidos acima do valor mínimo informado na hora da liberação.',
    ],
    [
        'question' => 'Quais formas de pagamento aceitas?',
        'answer' => 'Pix, cartão de crédito com parcelamento e boleto faturado para clientes aprovados. Tudo é enviado após a validação do cadastro.',
    ],
    [
        'question' => 'Existem cupons extras?',
        'answer' => 'Não. O desconto já é o mais agressivo do ano. Garantimos prioridade somente para quem finalizar o cadastro no período indicado.',
    ],
    [
        'question' => 'Quais as vantagens de comprar agora?',
        'answer' => 'Além do preço de atacado liberado, você garante prioridade nos estoques mais desejados e recebe atualizações exclusivas em primeira mão.',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A maior Black da Vésteme</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/jpeg" href="favicon.ico">
</head>
<body>
    <main class="landing-shell">
        <section class="hero hero-template" id="inicio">
            <div class="logo-stage">
                <div class="logo-glow">
                    <div class="logo-type" role="img" aria-label="Logo A maior Black da Vésteme"></div>
                </div>
            </div>

            <div class="hero-pill">
                <p class="pill-headline">Toda a loja a preço de <strong>Atacado</strong></p>
                <p class="pill-subline">Descontos de até 80% OFF (por ordem de cadastro e enquanto durar o estoque).</p>
            </div>

            <div class="countdown ticker">
                <div class="brick">
                    <span class="value" data-countdown="days">--</span>
                    <span class="label">Dias</span>
                </div>
                <div class="brick">
                    <span class="value" data-countdown="hours">--</span>
                    <span class="label">Horas</span>
                </div>
                <div class="brick">
                    <span class="value" data-countdown="minutes">--</span>
                    <span class="label">Minutos</span>
                </div>
                <div class="brick">
                    <span class="value" data-countdown="seconds">--</span>
                    <span class="label">Segundos</span>
                </div>
            </div>

            <p class="hero-note">A promoção vai até 28/11 ou até o estoque acabar.<br> Selecione seus itens agora para ter prioridade na liberação.</p>
            <a class="cta-primary" href="#categorias">MONTE MEU PEDIDO AGORA</a>
        </section>

        <section class="steps-section" id="como-funciona">
            <header>
                <p class="section-kicker">Como vai funcionar?</p>
                <h2>Em 3 passos simples</h2>
                <p class="section-subtitle">Acesse, valide e receba a ordem de liberação seguindo o fluxo oficial da Black.</p>
            </header>
            <div class="steps-grid">
                <article class="step-card">
                    <div class="step-head">
                        <h3>1º PASSO - Monte seu pedido (sem pagamento agora)</h3>
                    </div>
                    <div class="step-body">
                        <img src="assets/img/passo1-black.png" alt="Ícone sacola com coração representando montagem do pedido">
                        <p>Escolha as peças nas categorias, adicione ao carrinho e conclua com seus dados.</p>
                    </div>
                </article>
                <article class="step-card">
                    <div class="step-head">
                        <h3>2º PASSO - Valide seu interesse</h3>
                    </div>
                    <div class="step-body">
                        <img src="assets/img/passo2-black.png" alt="Ícone do WhatsApp representando validação do interesse">
                        <p>Entre no grupo oficial do WhatsApp para ser avisado sobre a sua liberação.</p>
                    </div>
                </article>
                <article class="step-card">
                    <div class="step-head">
                        <h3>3º PASSO - Liberação por ordem de cadastro</h3>
                    </div>
                    <div class="step-body">
                        <img src="assets/img/passo3-black.png" alt="Ícone de checklist indicando liberação por fila">
                        <p>Seguimos rigorosamente a ordem de chegada. Enviaremos o link de pagamento antes do estoque acabar.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="order-section" id="categorias">
            <header>
                <p class="section-kicker">Monte seu pedido</p>
                <h2>Escolha uma categoria para começar</h2>
                <p>Acesse cada coleção para ver opções, tamanhos e valores liberados com preço de atacado.</p>
            </header>
            <div class="category-showcase">
                <?php foreach ($categoryIllustrations as $slug => $image): ?>
                    <?php if (!isset($catalog[$slug])) continue; ?>
                    <article class="category-card">
                        <div class="icon-ring">
                            <img src="assets/img/<?= htmlspecialchars($image) ?>" alt="Ilustração da categoria <?= htmlspecialchars($catalog[$slug]['name']) ?>">
                        </div>
                        <strong><?= htmlspecialchars($catalog[$slug]['name']) ?></strong>
                        <a class="mini-cta" href="category.php?slug=<?= urlencode($slug) ?>">Ver mais</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="faq-section" id="faq">
            <header>
                <h2>Dúvidas sobre a Black da Vésteme</h2>
            </header>
            <div class="faq-list">
                <?php foreach ($faqItems as $index => $item): ?>
                    <?php $isOpen = $index === 0; ?>
                    <article class="faq-item <?= $isOpen ? 'is-open' : '' ?>" data-faq-item>
                        <button class="faq-toggle" type="button">
                            <span><?= htmlspecialchars($item['question']) ?></span>
                            <span class="symbol">+</span>
                        </button>
                        <div class="faq-answer">
                            <p><?= htmlspecialchars($item['answer']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        &copy; <?= date('Y') ?> Vésteme Modas - Black Friday atacado liberado com acesso controlado.
    </footer>
    <script src="assets/js/main.js"></script>
    <div id="liveActivity" class="live-activity" aria-live="polite"></div>
    <script>
        (function() {
            const container = document.getElementById('liveActivity');
            if (!container) return;

            let items = [];
            let queue = [];
            let timer = null;
            const MAX_VISIBLE = 3;
            const SHOW_MS = 5000;
            const MIN_DELAY = 5000;
            const MAX_DELAY = 8000;

            const shuffle = (arr) => {
                const a = arr.slice();
                for (let i = a.length - 1; i > 0; i -= 1) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [a[i], a[j]] = [a[j], a[i]];
                }
                return a;
            };

            const scheduleNext = () => {
                const delay = Math.floor(Math.random() * (MAX_DELAY - MIN_DELAY + 1)) + MIN_DELAY;
                timer = setTimeout(spawn, delay);
            };

            const removeCard = (card) => {
                card.classList.add('exiting');
                setTimeout(() => card.remove(), 280);
            };

            const spawn = () => {
                if (!items.length) return;
                if (!queue.length) {
                    queue = shuffle(items);
                }
                const item = queue.shift();
                const name = item.name || 'Cliente';
                const pieces = Math.max(1, parseInt(item.pieces || 1, 10));
                const msg1 = `${name} reservou seu pedido.`;
                const msg2 = `Garantiu ${pieces} peça${pieces > 1 ? 's' : ''}.`;

                const card = document.createElement('div');
                card.className = 'live-activity__chip entering';
                card.innerHTML = `
                    <button class="live-activity__close" aria-label="Fechar aviso">×</button>
                    <div class="live-activity__avatar">${(name || '?').charAt(0).toUpperCase()}</div>
                    <div class="live-activity__info">
                        <strong>${name}</strong>
                        <span>${msg1}</span>
                        <span>${msg2}</span>
                    </div>
                `;

                const existing = Array.from(container.children);
                if (existing.length >= MAX_VISIBLE) {
                    removeCard(existing[0]);
                }

                const closeBtn = card.querySelector('.live-activity__close');
                let hideTimer = setTimeout(() => removeCard(card), SHOW_MS);
                closeBtn?.addEventListener('click', () => {
                    clearTimeout(hideTimer);
                    removeCard(card);
                });

                container.appendChild(card);
                container.classList.add('show');

                card.addEventListener('animationend', () => {
                    card.classList.remove('entering');
                }, { once: true });

                scheduleNext();
            };

            fetch('activity.php')
                .then((res) => res.json())
                .then((data) => {
                    items = Array.isArray(data.items) ? data.items : [];
                    if (!items.length) return;
                    spawn();
                })
                .catch((err) => {
                    console.warn('[BF_DEBUG] liveActivity erro', err);
                });

            window.addEventListener('beforeunload', () => {
                if (timer) clearTimeout(timer);
            });
        })();
    </script>
</body>
</html>
