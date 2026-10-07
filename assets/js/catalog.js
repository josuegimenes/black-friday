const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const catalogProducts = window.catalogProducts || [];
const cartStore = new Map();
const CART_STORAGE_KEY = 'vesteme_cart_v1';

const getProductMeta = (productId) => {
    if (!productId) return null;

    if (window.catalogById && Object.prototype.hasOwnProperty.call(window.catalogById, productId)) {
        return window.catalogById[productId];
    }

    if (Array.isArray(catalogProducts)) {
        return catalogProducts.find((p) => p && p.id === productId) || null;
    }

    return null;
};

const cartItemsWrapper = document.querySelector('.cart-items');
const summaryItems = document.getElementById('summaryItems');
const summarySavings = document.getElementById('summarySavings');
const summaryValue = document.getElementById('summaryValue');
const cartInvest = document.getElementById('cartInvest');
const cartSavingsValue = document.getElementById('cartSavingsValue');
const summaryItemsSecondary = document.getElementById('summaryItemsSecondary');
const checkoutOverlay = document.getElementById('checkoutOverlay');
const cartSidebar = document.getElementById('cartSidebar');
const cartSidebarOverlay = document.getElementById('cartSidebarOverlay');
const cartToggle = document.getElementById('cartToggle');
const cartBadge = document.getElementById('cartBadge');
const closeCartSidebarBtn = document.getElementById('closeCartSidebar');
const checkoutList = document.getElementById('checkoutList');
const checkoutTotals = document.getElementById('checkoutTotals');
const cartPayloadInput = document.getElementById('cartPayload');
const checkoutForm = document.getElementById('checkoutForm');
const checkoutSubmitBtn =
    checkoutForm?.querySelector('.checkout-actions .solid') ||
    checkoutForm?.querySelector('input[type="submit"]');
const checkoutSubmitOriginalLabel =
    checkoutSubmitBtn?.tagName?.toLowerCase() === 'input'
        ? checkoutSubmitBtn?.value
        : checkoutSubmitBtn?.textContent;
const emailInput = document.querySelector('input[name="email"]');
const whatsappInput = document.querySelector('input[name="whatsapp"]');
const mergePreviousInput = document.getElementById('mergePrevious');
const mergeOverlay = document.getElementById('mergeOverlay');
const mergeEmailLabel = document.getElementById('mergeEmail');
const mergeInfoText = document.getElementById('mergeInfo');
const mergeAppendBtn = document.getElementById('mergeAppend');
const mergeReplaceBtn = document.getElementById('mergeReplace');
const mergeCloseBtn = document.getElementById('closeMergeModal');

let existingLeadInfo = null;
let existingLeadCheckedEmail = '';
let mergeChoice = null;
let pendingSubmit = null;
let skipNextSubmitValidation = false;
let isSubmitting = false;
let submitResetTimer = null;

const getCartIcon = () =>
    document.getElementById('cartToggle') ||
    document.querySelector('[data-cart-toggle]') ||
    document.querySelector('.cart-toggle');

// ALERT MODAL (custom, para evitar bloqueio de alert nativo em mobile)
function ensureAlertModal() {
    let overlay = document.getElementById('alertOverlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'alertOverlay';
    overlay.className = 'alert-overlay';
    overlay.innerHTML = `
        <div class="alert-box">
            <p class="alert-message"></p>
            <button type="button" class="alert-close">OK</button>
        </div>
    `;
    document.body.appendChild(overlay);

    const close = () => overlay.classList.remove('is-open');
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close();
    });
    overlay.querySelector('.alert-close')?.addEventListener('click', close);

    return overlay;
}

function showAlert(message) {
    const overlay = ensureAlertModal();
    const msgEl = overlay.querySelector('.alert-message');
    if (msgEl) {
        msgEl.textContent = message || '';
    }
    overlay.classList.add('is-open');
}

/**
 * Anima a imagem do produto + um chip de quantidade (+N) até o ícone do carrinho
 * @param {HTMLElement} sourceEl - botão "Adicionar ao carrinho"
 * @param {number} qtyAdded - quantidade adicionada neste clique
 */
function animateToCart(sourceEl, qtyAdded = 1) {
    const cartIcon = getCartIcon();

    // tenta achar o card e a imagem principal
    const card    = sourceEl.closest('[data-product-card]');
    const mainImg = card?.querySelector('[data-gallery-main] img');
    const origin  = sourceEl; // origem da animação é o botão clicado

    let startRect  = origin.getBoundingClientRect();
    // fallback: se por algum motivo a origem estiver sem dimensões, usa o próprio botão
    if ((!startRect.width && !startRect.height) && sourceEl !== origin) {
        startRect = sourceEl.getBoundingClientRect();
    }
    const iconRect = cartIcon ? cartIcon.getBoundingClientRect() : null;

    // destino: direção do ícone; se fora da viewport, ainda usamos o centro horizontal e um topo seguro
    const endX = iconRect ? iconRect.left + iconRect.width / 2 : startRect.left + startRect.width / 2;
    const endY = Math.max(16, iconRect ? iconRect.top + iconRect.height / 2 : 16);

    // --- FLYER (chip de quantidade) ---
    const flyer = document.createElement('div');
    flyer.classList.add('cart-flyer');
    flyer.textContent = `+${qtyAdded}`;

    document.body.appendChild(flyer);

    // posição inicial (centro da imagem/botão)
    const startX = startRect.left + startRect.width / 2;
    const startY = startRect.top + startRect.height / 2;

    flyer.style.left      = `${startX}px`;
    flyer.style.top       = `${startY}px`;
    flyer.style.opacity   = '1';
    flyer.style.transform = 'translate(-50%, -50%) scale(1)';

    const runAnimation = () => {
        flyer.style.left      = `${endX}px`;
        flyer.style.top       = `${endY}px`;
        flyer.style.transform = 'translate(-50%, -50%) scale(0.6)';
    };

    // força reflow para registrar o estado inicial e garante o próximo frame para transições
    void flyer.offsetWidth;
    requestAnimationFrame(runAnimation);

    // anima o ícone do carrinho (se visível)
    cartIcon?.classList.add('cart-toggle--bump');

    const teardown = () => {
        flyer.remove();
        cartIcon?.classList.remove('cart-toggle--bump');
    };

    flyer.addEventListener('transitionend', teardown, { once: true });
    // fallback para garantir limpeza (sincronizado com duração mais longa)
    setTimeout(teardown, 2600);
}

const calcTotals = () => {
    let totalItems = 0;
    let totalValue = 0;
    let totalSavings = 0;
    let totalOriginal = 0;

    cartStore.forEach((item) => {
        totalItems += item.quantity;
        totalValue += item.quantity * item.salePrice;
        totalOriginal += item.quantity * item.originalPrice;
        totalSavings += item.quantity * (item.originalPrice - item.salePrice);
    });

    return { totalItems, totalValue, totalSavings, totalOriginal };
};

const getCartSnapshot = () => ({
    items: Array.from(cartStore.values()),
    totals: calcTotals(),
});

// LOGS
const debugCartSnapshot = (context, snapshot = null) => {
    try {
        const snap = snapshot || getCartSnapshot();

        console.group(`[BF_DEBUG] Cart snapshot - ${context}`);
        console.log('Totais:', snap.totals);

        snap.items.forEach((item, index) => {
            console.log(`#${index}`, {
                key: item.key,
                productId: item.productId,
                name: item.name,
                size: item.size,
                color: item.color,
                quantity: item.quantity,
                thumb: item.thumb,
            });
        });

        console.groupEnd();
    } catch (err) {
        console.warn('[BF_DEBUG] Erro ao inspecionar carrinho:', err);
    }
};
// FIM LOGS

const renderCart = () => {
    if (!cartItemsWrapper) return;

    cartItemsWrapper.innerHTML = '';

    if (cartStore.size === 0) {
        cartItemsWrapper.innerHTML = '<p class="empty">Selecione as pecas para visualizar aqui.</p>';
    } else {
        cartStore.forEach((item) => {
            const div = document.createElement('div');
            div.className = 'cart-item';
            const thumbMarkup = item.thumb
                ? `<div class="cart-thumb"><img src="${item.thumb}" alt="${item.name}"></div>`
                : '<div class="cart-thumb placeholder"></div>';
            const paymentText = item.paymentLabel ? `<span class="cart-payment">${item.paymentLabel}</span>` : '';
            const colorDisplay = item.colorLabel || item.color;
            div.innerHTML = `
                ${thumbMarkup}
                <div class="cart-meta">
                    <strong>${item.name}</strong>
                    <span>${item.size} &middot; ${colorDisplay} &middot; ${item.quantity} un</span>
                    ${paymentText}
                </div>
                <div class="cart-actions">
                    <div class="qty-control" data-qty="${item.key}">
                        <button type="button" class="qty-btn" data-qty-dec="${item.key}" aria-label="Diminuir quantidade">-</button>
                        <input type="number" min="1" value="${item.quantity}" data-qty-input="${item.key}" aria-label="Quantidade">
                        <button type="button" class="qty-btn" data-qty-inc="${item.key}" aria-label="Aumentar quantidade">+</button>
                    </div>
                    <div class="cart-price">
                        <b>${currency.format(item.quantity * item.salePrice)}</b>
                        <button data-remove="${item.key}" title="Remover">&times;</button>
                    </div>
                </div>
            `;
            cartItemsWrapper.appendChild(div);
        });

        // Totais resumidos ao final da lista, em formato de tabela
        const totals = calcTotals();
        const footer = document.createElement('div');
        footer.className = 'cart-summary-footer';
        footer.innerHTML = `
            <table class="cart-summary-table" aria-label="Resumo do carrinho">
                <tr>
                    <td class="label">Itens no carrinho</td>
                    <td class="value">${totals.totalItems}</td>
                </tr>
                <tr>
                    <td class="label">Preço normal</td>
                    <td class="value">${currency.format(totals.totalOriginal)}</td>
                </tr>
                <tr>
                    <td class="label">Desconto aplicado</td>
                    <td class="value highlight">${currency.format(totals.totalSavings)}</td>
                </tr>
                <tr>
                    <td class="label">Total com descontos</td>
                    <td class="value">${currency.format(totals.totalValue)}</td>
                </tr>
            </table>
        `;
        cartItemsWrapper.appendChild(footer);
    }

    // eventos de remover
    cartItemsWrapper.querySelectorAll('button[data-remove]').forEach((btn) => {
        btn.addEventListener('click', () => {
            cartStore.delete(btn.dataset.remove);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
            updateCartBadge();
        });
    });

    // diminuir quantidade
    cartItemsWrapper.querySelectorAll('[data-qty-dec]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.qtyDec;
            const item = cartStore.get(key);
            if (!item) return;
            item.quantity = Math.max(1, item.quantity - 1);
            cartStore.set(key, item);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
            updateCartBadge();
        });
    });

    // aumentar quantidade
    cartItemsWrapper.querySelectorAll('[data-qty-inc]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.qtyInc;
            const item = cartStore.get(key);
            if (!item) return;
            item.quantity += 1;
            cartStore.set(key, item);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
            updateCartBadge();
        });
    });

    // edição direta do input de quantidade
    cartItemsWrapper.querySelectorAll('[data-qty-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const key = input.dataset.qtyInput;
            const item = cartStore.get(key);
            if (!item) return;
            const val = Number(input.value) || 1;
            item.quantity = Math.max(1, val);
            cartStore.set(key, item);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
            updateCartBadge();
        });
    });
};

const refreshSummary = () => {
    const { totalItems, totalValue, totalSavings } = calcTotals();
    if (summaryItems) summaryItems.textContent = totalItems;
    if (summaryItemsSecondary) summaryItemsSecondary.textContent = totalItems;
    if (summaryValue) summaryValue.textContent = currency.format(totalValue);
    if (summarySavings) summarySavings.textContent = currency.format(totalSavings);
    if (cartInvest) cartInvest.textContent = currency.format(totalValue);
    if (cartSavingsValue) cartSavingsValue.textContent = currency.format(totalSavings);
    updateCartBadge();
};

const createKey = (id, size, color) => `${id}__${size}__${color}`;

const closeCheckout = () => checkoutOverlay?.classList.remove('active');

const setSubmittingState = (flag) => {
    isSubmitting = flag;
    if (!checkoutSubmitBtn) return;

    if (flag) {
        if (checkoutSubmitBtn.tagName.toLowerCase() === 'input') {
            checkoutSubmitBtn.value = 'Enviando...';
        } else {
            checkoutSubmitBtn.textContent = 'Enviando...';
        }
        checkoutSubmitBtn.disabled = true;

        // fallback: se ficar na página por qualquer motivo, reabilita após 10s
        clearTimeout(submitResetTimer);
        submitResetTimer = setTimeout(() => {
            if (isSubmitting) {
                setSubmittingState(false);
            }
        }, 10000);
    } else {
        checkoutSubmitBtn.disabled = false;
        if (checkoutSubmitBtn.tagName.toLowerCase() === 'input') {
            checkoutSubmitBtn.value = checkoutSubmitOriginalLabel || 'Confirmar interesse';
        } else {
            checkoutSubmitBtn.textContent = checkoutSubmitOriginalLabel || 'Confirmar interesse';
        }
        clearTimeout(submitResetTimer);
    }
};

const updateCheckoutModal = () => {
    if (!checkoutOverlay) return;

    const snapshot = getCartSnapshot();

    debugCartSnapshot(
        checkoutOverlay.classList.contains('active')
            ? 'checkout ABERTO (updateCheckoutModal)'
            : 'checkout FECHADO (updateCheckoutModal)',
        snapshot,
    );

    if (!checkoutOverlay.classList.contains('active')) {
        if (cartPayloadInput) {
            cartPayloadInput.value = JSON.stringify(snapshot);
        }
        return;
    }

    if (cartStore.size === 0) {
        closeCheckout();
        return;
    }

    if (checkoutList) {
        checkoutList.innerHTML = snapshot.items
            .map((item) => {
                const price = currency.format(item.quantity * item.salePrice);
                const thumbMarkup = item.thumb
                    ? `<div class="checkout-thumb"><img src="${item.thumb}" alt="${item.name}"></div>`
                    : '<div class="checkout-thumb placeholder"></div>';
                const colorDisplay = item.colorLabel || item.color;

                return `
                    <li>
                        ${thumbMarkup}
                        <div class="checkout-info">
                            <strong>${item.name}</strong>
                            <span>${item.size} · ${colorDisplay} · ${item.quantity} un${item.paymentLabel ? ` • ${item.paymentLabel}` : ''}</span>
                        </div>
                        <strong class="checkout-price">${price}</strong>
                    </li>
                `;
            })
            .join('');
    }

    if (checkoutTotals) {
        checkoutTotals.textContent = `${snapshot.totals.totalItems} pecas | Investimento ${currency.format(
            snapshot.totals.totalValue,
        )} | Economia ${currency.format(snapshot.totals.totalSavings)}`;
    }

    if (cartPayloadInput) {
        cartPayloadInput.value = JSON.stringify(snapshot);
    }
};const persistCart = () => {
    try {
        const payload = { items: Array.from(cartStore.values()) };
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(payload));
    } catch (error) {
        console.warn('Nao foi possivel salvar o carrinho no armazenamento local.', error);
    }
};

const hydrateCart = (rawValue = null) => {
    try {
        const source = typeof rawValue === 'string' ? rawValue : localStorage.getItem(CART_STORAGE_KEY);
        if (!source) return;
        const payload = JSON.parse(source);
        if (!Array.isArray(payload.items)) return;
        cartStore.clear();
        payload.items.forEach((item) => {
            if (!item.key) {
                item.key = createKey(item.productId, item.size, item.color);
            }
            cartStore.set(item.key, item);
        });
    } catch (error) {
        console.warn('Nao foi possivel carregar o carrinho salvo.', error);
    }
};

const toAbsoluteUrl = (url) => {
    if (!url) return null;
    try {
        return new URL(url, window.location.origin).toString();
    } catch (error) {
        console.warn('[BF_DEBUG] URL inválida para thumb:', url, error);
        return url;
    }
};

const mountItem = (product, size, color, quantity, thumb = null, payment = {}, colorLabel = null) => {
    const rawThumb = thumb ?? product.thumb ?? null;
    const paymentTotal = payment.total ?? payment.paymentTotal ?? null;
    const item = {
        key: createKey(product.id, size, color),
        productId: product.id,
        name: product.name,
        size,
        color,
        colorLabel: colorLabel || color,
        quantity,
        originalPrice: Number(product.original_price ?? product.originalPrice ?? 0),
        salePrice: Number(paymentTotal ?? product.sale_price ?? product.salePrice ?? 0),
        thumb: rawThumb ? toAbsoluteUrl(rawThumb) : null,
        payment: payment.key ?? null,
        paymentLabel: payment.label ?? null,
    };

    console.log('[BF_DEBUG] mountItem criado:', item);

    return item;
};

const ensureStage = (mainWrapper) => {
    let stage = mainWrapper.querySelector('.media-stage');
    if (!stage) {
        stage = document.createElement('div');
        stage.className = 'media-stage';
        mainWrapper.insertBefore(stage, mainWrapper.firstChild);
    }
    return stage;
};

const setMainMedia = (card, type, src, alt = '') => {
    const mainWrapper = card.querySelector('[data-gallery-main]');
    if (!mainWrapper || !src) return;
    const stage = ensureStage(mainWrapper);
    mainWrapper.dataset.mediaType = type;
    stage.innerHTML = '';
    stage.appendChild(createMediaNode(type, src, alt));
};

const createMediaNode = (type, src, alt = '') => {
    if (type === 'video') {
        const video = document.createElement('video');
        video.playsInline = true;
        video.autoplay = true;
        video.loop = true;
        video.controls = true;
        video.src = src;
        return video;
    }
    const img = document.createElement('img');
    img.src = src;
    img.alt = alt;
    return img;
};

const slideToThumb = (card, thumb, direction = 0) => {
    if (!thumb) return;
    const title = card.querySelector('h3')?.textContent || '';
    const mainWrapper = card.querySelector('[data-gallery-main]');
    if (!mainWrapper) return;
    const stage = ensureStage(mainWrapper);

    const type = thumb.dataset.mediaType || 'image';
    const src = thumb.dataset.src || thumb.dataset.image;
    const current = stage.firstElementChild;

    // sem direção = troca direta (primeiro load/rebuild)
    if (!direction || !current) {
        stage.innerHTML = '';
        stage.appendChild(createMediaNode(type, src, title));
        return;
    }

    const outgoing = current;
    const incoming = createMediaNode(type, src, title);
    incoming.classList.add('slide-anim');
    outgoing.classList.add('slide-anim');

    const dir = direction > 0 ? 1 : -1;
    incoming.style.transform = `translateX(${dir * 100}%)`;
    incoming.style.opacity = '0';
    outgoing.style.transform = 'translateX(0)';
    outgoing.style.opacity = '1';

    stage.appendChild(incoming);

    requestAnimationFrame(() => {
        incoming.style.transform = 'translateX(0)';
        incoming.style.opacity = '1';
        outgoing.style.transform = `translateX(${-dir * 100}%)`;
        outgoing.style.opacity = '0';
    });

    const handleEnd = () => {
        incoming.classList.remove('slide-anim');
        incoming.style.transform = '';
        incoming.style.opacity = '';
        stage.innerHTML = '';
        stage.appendChild(incoming);
        outgoing.removeEventListener('transitionend', handleEnd);
    };

    outgoing.addEventListener('transitionend', handleEnd, { once: true });
    // fallback cleanup
    setTimeout(handleEnd, 320);
};

const activateThumb = (card, thumb, direction = 0) => {
    if (!thumb) return;
    const thumbs = card.querySelectorAll('[data-gallery-thumb]');
    thumbs.forEach((btn) => btn.classList.remove('is-active'));
    thumb.classList.add('is-active');
    slideToThumb(card, thumb, direction);
};

const changeThumbByStep = (card, step) => {
    const thumbs = Array.from(card.querySelectorAll('[data-gallery-thumb]'));
    if (!thumbs.length) return;
    const currentIndex = Math.max(0, thumbs.findIndex((t) => t.classList.contains('is-active')));
    const nextIndex = (currentIndex + step + thumbs.length) % thumbs.length;
    const direction = step >= 0 ? 1 : -1;
    activateThumb(card, thumbs[nextIndex], direction);
};

const initGallery = (card) => {
    const mainWrapper = card.querySelector('[data-gallery-main]');
    const thumbs = card.querySelectorAll('[data-gallery-thumb]');
    if (!mainWrapper || thumbs.length === 0) return;

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', () => {
            const currentIndex = Math.max(0, Array.from(thumbs).findIndex((t) => t.classList.contains('is-active')));
            const direction = index > currentIndex ? 1 : -1;
            activateThumb(card, thumb, direction);
        });
    });
};

const bindGalleryNavigation = (card) => {
    const mainWrapper = card.querySelector('[data-gallery-main]');
    if (!mainWrapper) return;
    if (mainWrapper.dataset.navBound === 'true') return;
    mainWrapper.dataset.navBound = 'true';

    const isNavButton = (target) => !!(target && target.closest && target.closest('[data-gallery-prev],[data-gallery-next]'));

    let lastNavTs = 0;
    const maybeNavigate = (step) => {
        const now = Date.now();
        if (now - lastNavTs < 180) return; // evita duplo acionamento no mesmo gesto
        lastNavTs = now;
        changeThumbByStep(card, step);
    };

    let startX = 0;
    let startY = 0;
    let startTime = 0;
    let pointerId = null;

    mainWrapper.addEventListener('pointerdown', (event) => {
        if (isNavButton(event.target)) return;
        pointerId = event.pointerId;
        startX = event.clientX;
        startY = event.clientY;
        startTime = Date.now();
        try {
            mainWrapper.setPointerCapture(pointerId);
        } catch (err) {
            // ignore capture errors (non-primary pointers)
        }
    });

    const handleMove = (event) => {
        if (isNavButton(event.target)) return;
        if (pointerId === null || event.pointerId !== pointerId) return;
        // prevent the page from hijacking the swipe while the user is dragging horizontally
        const dx = Math.abs(event.clientX - startX);
        const dy = Math.abs(event.clientY - startY);
        if (dx > dy && dx > 10) {
            event.preventDefault();
        }
    };

    mainWrapper.addEventListener('pointermove', handleMove, { passive: false });

    mainWrapper.addEventListener('pointerup', (event) => {
        if (isNavButton(event.target)) {
            pointerId = null;
            return;
        }
        if (pointerId !== null && event.pointerId !== pointerId) return;
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        const elapsed = Date.now() - startTime;
        const isHorizontal = Math.abs(dx) > Math.abs(dy);
        const isSwipe = elapsed < 800 && Math.abs(dx) > 30 && isHorizontal;
        if (isSwipe) {
            maybeNavigate(dx < 0 ? 1 : -1);
        }
        pointerId = null;
        try {
            mainWrapper.releasePointerCapture(event.pointerId);
        } catch (err) {
            // ignore
        }
    });

    mainWrapper.addEventListener(
        'wheel',
        (event) => {
            if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                event.preventDefault();
                maybeNavigate(event.deltaX > 0 ? 1 : -1);
            }
        },
        { passive: false },
    );

    const prevBtn = card.querySelector('[data-gallery-prev]');
    const nextBtn = card.querySelector('[data-gallery-next]');
    prevBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        maybeNavigate(-1);
    });
    nextBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        maybeNavigate(1);
    });
};

const attachGalleryControls = () => {
    document.querySelectorAll('[data-product-card]').forEach((card) => {
        initGallery(card);
        bindGalleryNavigation(card);
    });
};

const attachCardEvents = () => {
    document.querySelectorAll('[data-product-card]').forEach((card) => {
        const productId = card.dataset.productId;
        const product = getProductMeta(productId);
        if (!product) return;

        const colorDataScript = card.querySelector('.color-media-data');
        let colorMedia = {};
        if (colorDataScript) {
            try {
                colorMedia = JSON.parse(colorDataScript.textContent || '{}');
            } catch (error) {
                console.warn('[BF_DEBUG] Erro ao ler media de cores', error);
            }
        }

        const colorSelect = card.querySelector('select[name="color"]');
        const sizeSelect = card.querySelector('select[name="size"]');
        const quantityInput = card.querySelector('input[name="quantity"]');
        const addButton = card.querySelector('[data-add-to-cart]');
        const openCartButtons = card.querySelectorAll('[data-open-cart]');
        const pillsColorGroup = card.querySelector('[data-color-pills]');
        const pillsSizeGroup = card.querySelector('[data-size-pills]');
        const qtyDecBtn = card.querySelector('.qty-wrapper [data-qty-dec]');
        const qtyIncBtn = card.querySelector('.qty-wrapper [data-qty-inc]');

        let activeColor = card.dataset.activeColor || Object.keys(colorMedia)[0] || '';

        const setSelectValue = (selectEl, value) => {
            if (!selectEl) return;
            Array.from(selectEl.options).forEach((opt) => {
                opt.selected = opt.value === value;
            });
        };

        // validação com modal custom (evita bloqueio de alert nativo)
        const clearErrors = () => {
            card.querySelectorAll('.field-error').forEach((el) => el.remove());
        };

        const showError = (message) => {
            showAlert(message);
        };

        const rebuildThumbs = (colorSlug) => {
            const media = colorMedia[colorSlug];
            if (!media) {
                console.warn('[BF_DEBUG] Nenhuma midia para cor', colorSlug, colorMedia);
                return;
            }
            const thumbsRail = card.querySelector('[data-thumbs]');
            if (!thumbsRail) return;
            thumbsRail.innerHTML = '';
            let firstSrc = null;
            let firstType = 'image';

            const pushThumb = (type, src, isActive = false) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `media-thumb${type === 'video' ? ' media-thumb-video' : ''}${isActive ? ' is-active' : ''}`;
                btn.dataset.galleryThumb = '';
                btn.dataset.mediaType = type;
                btn.dataset.src = src;
                if (type === 'video') {
                    btn.innerHTML = '<span class="video-icon">&#9654;</span>';
                } else {
                    btn.innerHTML = `<img src="${src}" alt="miniatura">`;
                }
                thumbsRail.appendChild(btn);
            };

            if (media.primary) {
                firstSrc = media.primary;
                pushThumb('image', media.primary, true);
            }
            (media.gallery || []).forEach((img) => {
                if (!firstSrc) firstSrc = img;
                pushThumb('image', img, false);
            });
            (media.videos || []).forEach((vid) => {
                if (!firstSrc) {
                    firstSrc = vid;
                    firstType = 'video';
                }
                pushThumb('video', vid, false);
            });

            if (firstSrc) {
                setMainMedia(card, firstType, firstSrc, product.name);
            }
            initGallery(card);
            bindGalleryNavigation(card);
        };

        const colorPills = card.querySelectorAll('[data-color-option]');
        colorPills.forEach((pill) => {
            pill.addEventListener('click', () => {
                activeColor = pill.dataset.color || '';
                colorPills.forEach((p) => p.classList.remove('is-active'));
                pill.classList.add('is-active');
                setSelectValue(colorSelect, activeColor);
                card.dataset.activeColor = activeColor;
                rebuildThumbs(activeColor);
            });
        });

        // select de cor (fallback/teclado) também troca galeria
        colorSelect?.addEventListener('change', (event) => {
            const selected = event.target.value || '';
            activeColor = selected;
            colorPills.forEach((p) => {
                if (p.dataset.color === selected) {
                    p.classList.add('is-active');
                } else {
                    p.classList.remove('is-active');
                }
            });
            card.dataset.activeColor = activeColor;
            rebuildThumbs(activeColor);
        });

        const sizePills = card.querySelectorAll('[data-size-option]');
        sizePills.forEach((pill) => {
            pill.addEventListener('click', () => {
                const val = pill.dataset.size || '';
                sizePills.forEach((p) => p.classList.remove('is-active'));
                pill.classList.add('is-active');
                setSelectValue(sizeSelect, val);
            });
        });

        rebuildThumbs(activeColor);

        // controles de quantidade no card
        qtyDecBtn?.addEventListener('click', () => {
            if (!quantityInput) return;
            const val = Math.max(1, Number(quantityInput.value || 1) - 1);
            quantityInput.value = String(val);
            applyPaymentToCard();
        });
        qtyIncBtn?.addEventListener('click', () => {
            if (!quantityInput) return;
            const val = Math.max(1, Number(quantityInput.value || 1)) + 1;
            quantityInput.value = String(val);
            applyPaymentToCard();
        });
        quantityInput?.addEventListener('change', () => {
            if (!quantityInput) return;
            const val = Math.max(1, Number(quantityInput.value || 1));
            quantityInput.value = String(val);
            applyPaymentToCard();
        });

        const applyPaymentToCard = () => {
            const paymentSelect = card.querySelector('[data-payment]');
            const priceNowEl = card.querySelector('.price-now');
            const paymentHint = card.querySelector('.payment-hint');
            const paymentChip = card.querySelector('.payment-chip');
            const savingPill = card.querySelector('.saving-pill');
            const originalPrice = Number(product.original_price ?? product.originalPrice ?? 0);
            const qty = Math.max(1, Number(quantityInput?.value || 1));

            const sel = paymentSelect?.selectedOptions?.[0];
            const paymentTotal = sel ? Number(sel.dataset.total || NaN) : null;
            const paymentLabel = sel?.textContent?.trim() || '';
            const paymentKey = (sel?.value || sel?.dataset?.key || '').toLowerCase();
            const saleValue = !Number.isNaN(paymentTotal) && paymentTotal !== null ? paymentTotal : Number(product.sale_price ?? product.salePrice ?? 0);

            const money = (val) =>
                `R$ ${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(
                    Number(val) || 0,
                )}`;

            if (priceNowEl) {
                if (paymentKey === '3x') {
                    priceNowEl.textContent = `3x de ${money(saleValue / 3)}`;
                } else if (paymentKey === '12x') {
                    priceNowEl.textContent = `12x de ${money(saleValue / 12)}`;
                } else {
                    priceNowEl.textContent = money(saleValue);
                }
            }

            if (paymentHint) {
                paymentHint.textContent = paymentLabel || '';
            }

            if (paymentChip) {
                if (paymentKey === '3x') {
                    paymentChip.textContent = '3x';
                } else if (paymentKey === '12x') {
                    paymentChip.textContent = '12x';
                } else {
                    paymentChip.textContent = 'no Pix';
                }
            }

            if (savingPill) {
                const economyPerUnit = Math.max(0, (originalPrice || saleValue) - saleValue);
                const economyTotal = economyPerUnit * qty;
                savingPill.textContent = `Economize ${currency.format(economyTotal)}`;
            }
        };

        applyPaymentToCard();
        card.querySelector('[data-payment]')?.addEventListener('change', applyPaymentToCard);

        // BOTÃO ADICIONAR AO CARRINHO - usando quantidade deste clique (+N no chip)
        addButton?.addEventListener('click', () => {
            clearErrors();

            const size = sizeSelect?.value;
            const color = colorSelect?.value;
            const colorLabel = colorSelect?.selectedOptions?.[0]?.textContent?.trim() || color || '';
            const quantity = Number(quantityInput?.value || 0);
            const paymentSelect = card.querySelector('[data-payment]');
            const selectedPayment = paymentSelect?.selectedOptions?.[0];
            const paymentTotal = selectedPayment ? Number(selectedPayment.dataset.total || NaN) : null;
            const paymentKey = selectedPayment?.value || null;
            const paymentLabel = selectedPayment?.textContent?.trim() || null;

            let hasError = false;
            if (!color) {
                hasError = true;
                showError('Selecione a cor.');
            }

            if (!size) {
                hasError = true;
                showError('Selecione o tamanho.');
            }

            let addedQty = quantity;
            if (!addedQty || addedQty < 1) {
                hasError = true;
                showError('Informe a quantidade.');
                addedQty = 1;
                if (quantityInput) {
                    quantityInput.value = '1';
                }
            }

            if (hasError) {
                return;
            }

            let thumb = null;
            const mainImage = card.querySelector('[data-gallery-main] img');
            if (mainImage?.src) {
                thumb = mainImage.src;
            } else if (product && product.thumb) {
                thumb = product.thumb;
            }

            // item com a quantidade deste clique
            const item = mountItem(product, size, color, addedQty, thumb, {
                total: Number.isNaN(paymentTotal) ? null : paymentTotal,
                key: paymentKey,
                label: paymentLabel,
            }, colorLabel);
            const existing = cartStore.get(item.key);
            if (existing) {
                item.quantity += existing.quantity;
            }
            cartStore.set(item.key, item);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();

            // chip mostra +N (quantidade adicionada neste clique)
            animateToCart(addButton, addedQty);
        });

        openCartButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                openCartSidebar();
            });
        });
    });
};

const digitsOnly = (value = '') => value.replace(/\D/g, '');

const formatWhatsapp = (value) => {
    const digits = digitsOnly(value).slice(0, 11);
    if (!digits) return '';
    const ddd = digits.slice(0, 2);
    if (digits.length <= 2) {
        return `(${ddd}`;
    }
    if (digits.length <= 6) {
        return `(${ddd}) ${digits.slice(2)}`;
    }
    if (digits.length <= 10) {
        return `(${ddd}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }
    return `(${ddd}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
};

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

const fetchExistingLead = async (email) => {
    const normalized = email.toLowerCase();
    try {
        const response = await fetch(`check_lead.php?email=${encodeURIComponent(normalized)}`, { cache: 'no-store' });
        if (!response.ok) {
            existingLeadInfo = null;
            existingLeadCheckedEmail = normalized;
            return null;
        }
        const data = await response.json();
        existingLeadInfo = data.exists ? data.lead : null;
        existingLeadCheckedEmail = normalized;
        mergeChoice = null;
        if (!existingLeadInfo && mergePreviousInput) {
            mergePreviousInput.value = 'no';
        }
        return existingLeadInfo;
    } catch (error) {
        console.error('Nao foi possivel verificar pedidos anteriores.', error);
        existingLeadInfo = null;
        return null;
    }
};

const openMergeModal = (lead, email) => {
    if (!mergeOverlay) return;
    mergeEmailLabel.textContent = email;
    const createdAt = lead?.created_at_br || lead?.created_at || 'recentemente';
    const total = currency.format(lead?.total_value ?? 0);
    mergeInfoText.textContent = `Seu último pedido foi registrado em ${createdAt} com investimento de ${total}. Escolha como deseja continuar.`;
    mergeOverlay.classList.add('active');
    mergeOverlay.setAttribute('aria-hidden', 'false');
};

const closeMergeModal = () => {
    if (!mergeOverlay) return;
    mergeOverlay.classList.remove('active');
    mergeOverlay.setAttribute('aria-hidden', 'true');
    mergeInfoText.textContent = '';
};

const applyMergeDecision = (shouldMerge) => {
    mergeChoice = shouldMerge ? 'yes' : 'no';
    if (mergePreviousInput) mergePreviousInput.value = mergeChoice;
    closeMergeModal();
    if (pendingSubmit) {
        skipNextSubmitValidation = true;
        pendingSubmit();
        pendingSubmit = null;
    }
};

mergeAppendBtn?.addEventListener('click', () => applyMergeDecision(true));
mergeReplaceBtn?.addEventListener('click', () => applyMergeDecision(false));
mergeCloseBtn?.addEventListener('click', () => {
    closeMergeModal();
    mergeChoice = null;
    pendingSubmit = null;
});

if (whatsappInput) {
    whatsappInput.addEventListener('input', () => {
        const formatted = formatWhatsapp(whatsappInput.value);
        whatsappInput.value = formatted;
        if (digitsOnly(formatted).length >= 10) {
            whatsappInput.classList.remove('invalid');
        }
    });
    whatsappInput.addEventListener('blur', () => {
        if (digitsOnly(whatsappInput.value).length < 10) {
            whatsappInput.classList.add('invalid');
        }
    });
}

if (emailInput) {
    emailInput.addEventListener('input', () => {
        mergeChoice = null;
        existingLeadInfo = null;
        existingLeadCheckedEmail = '';
        if (mergePreviousInput) mergePreviousInput.value = 'no';
        if (isValidEmail(emailInput.value.trim())) {
            emailInput.classList.remove('invalid');
        }
    });

    emailInput.addEventListener('blur', async () => {
        const email = emailInput.value.trim().toLowerCase();
        if (isValidEmail(email)) {
            await fetchExistingLead(email);
        } else {
            existingLeadInfo = null;
            existingLeadCheckedEmail = '';
        }
    });
}

const openCheckout = () => {
    if (cartStore.size === 0) {
        alert('Selecione ao menos um produto antes de fechar o carrinho.');
        return;
    }

    checkoutOverlay?.classList.add('active');
    updateCheckoutModal();
    closeCartSidebar();
};

const openCartSidebar = () => {
    if (cartSidebar) {
        cartSidebar.classList.add('is-open');
        cartSidebar.setAttribute('aria-hidden', 'false');
    }
};

const closeCartSidebar = () => {
    if (cartSidebar) {
        cartSidebar.classList.remove('is-open');
        cartSidebar.setAttribute('aria-hidden', 'true');
    }
};

const updateCartBadge = () => {
    if (!cartBadge) return;
    const count = calcTotals().totalItems;
    cartBadge.textContent = count;
    cartBadge.style.display = count > 0 ? 'flex' : 'none';
};

const triggerButton = document.getElementById('triggerCheckout');
const closeButton = document.getElementById('closeCheckout');

triggerButton?.addEventListener('click', openCheckout);
closeButton?.addEventListener('click', closeCheckout);
checkoutOverlay?.addEventListener('click', (event) => {
    if (event.target === checkoutOverlay) {
        closeCheckout();
    }
});

cartToggle?.addEventListener('click', () => {
    if (cartSidebar?.classList.contains('is-open')) {
        closeCartSidebar();
    } else {
        openCartSidebar();
    }
});

cartSidebarOverlay?.addEventListener('click', closeCartSidebar);
closeCartSidebarBtn?.addEventListener('click', closeCartSidebar);

checkoutForm?.addEventListener('submit', async (event) => {
    if (isSubmitting) {
        event.preventDefault();
        return;
    }

    if (skipNextSubmitValidation) {
        skipNextSubmitValidation = false;
        return;
    }

    if (cartStore.size === 0) {
        event.preventDefault();
        alert('Adicione produtos antes de enviar sua reserva.');
        return;
    }

    const emailRaw = emailInput?.value.trim() ?? '';
    const emailNormalized = emailRaw.toLowerCase();
    if (emailInput && !isValidEmail(emailRaw)) {
        event.preventDefault();
        emailInput.classList.add('invalid');
        emailInput.focus();
        alert('Informe um e-mail valido.');
        return;
    }

    if (emailInput && isValidEmail(emailRaw) && emailNormalized !== existingLeadCheckedEmail) {
        await fetchExistingLead(emailNormalized);
    }

    if (existingLeadInfo && mergeChoice === null) {
        event.preventDefault();
        pendingSubmit = () => checkoutForm.requestSubmit();
        openMergeModal(existingLeadInfo, emailRaw);
        return;
    } else if (!existingLeadInfo && mergePreviousInput) {
        mergePreviousInput.value = 'no';
    }

    const whatsappDigits = digitsOnly(whatsappInput?.value ?? '');
    if (whatsappInput && whatsappDigits.length < 10) {
        event.preventDefault();
        whatsappInput.classList.add('invalid');
        whatsappInput.focus();
        alert('Informe um WhatsApp valido.');
        return;
    }

    // 🔍 DEBUG: snapshot final que será enviado para o backend
    console.log('[BF_DEBUG] Cart snapshot - ANTES DO SUBMIT checkoutForm', getCartSnapshot());
    debugCartSnapshot('ANTES DO SUBMIT checkoutForm');

    updateCheckoutModal();

    setSubmittingState(true);
});

attachGalleryControls();
attachCardEvents();
hydrateCart();
renderCart();
refreshSummary();
updateCheckoutModal();

window.addEventListener('storage', (event) => {
    if (event.key === CART_STORAGE_KEY) {
        hydrateCart(event.newValue);
        renderCart();
        refreshSummary();
        updateCheckoutModal();
    }
});
