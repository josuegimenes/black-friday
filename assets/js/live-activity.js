(function() {
    const container = document.getElementById('liveActivity');
    if (!container) return;

    let items = [];
    let queue = [];
    let timer = null;
    const MAX_VISIBLE = 3;
    const SHOW_MS = 5000;
    const MIN_DELAY = 20000;
    const MAX_DELAY = 40000;

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
        const msg1 = `${name} reservou seu acesso.`;
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
