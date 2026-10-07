const countdownBlocks = document.querySelectorAll('[data-countdown]');

if (countdownBlocks.length) {
    const releaseDate = new Date('2025-11-28T00:00:00-03:00');

    const pad = (value) => String(value).padStart(2, '0');

    const syncCountdown = () => {
        const now = new Date();
        let diff = releaseDate.getTime() - now.getTime();
        if (diff < 0) diff = 0;

        const seconds = Math.floor(diff / 1000) % 60;
        const minutes = Math.floor(diff / (1000 * 60)) % 60;
        const hours = Math.floor(diff / (1000 * 60 * 60)) % 24;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        document.querySelector('[data-countdown="days"]').textContent = pad(days);
        document.querySelector('[data-countdown="hours"]').textContent = pad(hours);
        document.querySelector('[data-countdown="minutes"]').textContent = pad(minutes);
        document.querySelector('[data-countdown="seconds"]').textContent = pad(seconds);
    };

    syncCountdown();
    setInterval(syncCountdown, 1000);
}

const faqItems = document.querySelectorAll('[data-faq-item]');

if (faqItems.length) {
    faqItems.forEach((item) => {
        const trigger = item.querySelector('.faq-toggle');
        if (!trigger) return;

        trigger.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            faqItems.forEach((entry) => entry.classList.remove('is-open'));
            if (!isOpen) {
                item.classList.add('is-open');
            }
        });
    });
}
