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
const checkoutList = document.getElementById('checkoutList');
const checkoutTotals = document.getElementById('checkoutTotals');
const cartPayloadInput = document.getElementById('cartPayload');
const checkoutForm = document.getElementById('checkoutForm');
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

const calcTotals = () => {
    let totalItems = 0;
    let totalValue = 0;
    let totalSavings = 0;

    cartStore.forEach((item) => {
        totalItems += item.quantity;
        totalValue += item.quantity * item.salePrice;
        totalSavings += item.quantity * (item.originalPrice - item.salePrice);
    });

    return { totalItems, totalValue, totalSavings };
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
            div.innerHTML = `
                ${thumbMarkup}
                <div class="cart-meta">
                    <strong>${item.name}</strong>
                    <span>${item.size} | ${item.color} | ${item.quantity} un</span>
                </div>
                <div class="cart-price">
                    <b>${currency.format(item.quantity * item.salePrice)}</b>
                    <button data-remove="${item.key}" title="Remover">&times;</button>
                </div>
            `;
            cartItemsWrapper.appendChild(div);
        });
    }

    cartItemsWrapper.querySelectorAll('button[data-remove]').forEach((btn) => {
        btn.addEventListener('click', () => {
            cartStore.delete(btn.dataset.remove);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
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
};

const createKey = (id, size, color) => `${id}__${size}__${color}`;

const closeCheckout = () => checkoutOverlay?.classList.remove('active');

const updateCheckoutModal = () => {
    if (!checkoutOverlay) return;

    const snapshot = getCartSnapshot();

    // 🔍 DEBUG: sempre que o modal/checkout for atualizado
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

                return `
                    <li>
                        ${thumbMarkup}
                        <div class="checkout-info">
                            <strong>${item.name}</strong>
                            <span>${item.size} · ${item.color} · ${item.quantity} un</span>
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
};

const persistCart = () => {
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

const mountItem = (product, size, color, quantity, thumb = null) => {
    const item = {
        key: createKey(product.id, size, color),
        productId: product.id,
        name: product.name,
        size,
        color,
        quantity,
        originalPrice: Number(product.original_price ?? product.originalPrice ?? 0),
        salePrice: Number(product.sale_price ?? product.salePrice ?? 0),
        thumb: thumb ?? product.thumb ?? null,
    };

    // 🔍 DEBUG: ver item exatamente como vai para o carrinho
    console.log('[BF_DEBUG] mountItem criado:', item);

    return item;
};

const attachGalleryControls = () => {
    document.querySelectorAll('[data-product-card]').forEach((card) => {
        const mainWrapper = card.querySelector('[data-gallery-main]');
        const mainImage = mainWrapper?.querySelector('img');
        if (!mainImage) return;

        const thumbs = card.querySelectorAll('[data-gallery-thumb]');
        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                const target = thumb.dataset.image;
                if (!target || mainImage.src.endsWith(target)) return;

                mainImage.src = target;
                thumbs.forEach((btn) => btn.classList.remove('is-active'));
                thumb.classList.add('is-active');
            });
        });
    });
};

const attachCardEvents = () => {
    document.querySelectorAll('[data-product-card]').forEach((card) => {
        const productId = card.dataset.productId;
        const product = getProductMeta(productId);
        if (!product) return;

        const sizeSelect = card.querySelector('select[name="size"]');
        const colorSelect = card.querySelector('select[name="color"]');
        const quantityInput = card.querySelector('input[name="quantity"]');
        const addButton = card.querySelector('[data-add-to-cart]');

        addButton?.addEventListener('click', () => {
            const size = sizeSelect?.value;
            const color = colorSelect?.value;
            const quantity = Number(quantityInput?.value || 0);

            if (!size) {
                sizeSelect?.classList.add('invalid');
                sizeSelect?.focus();
                return;
            }
            sizeSelect?.classList.remove('invalid');

            if (!color) {
                colorSelect?.classList.add('invalid');
                colorSelect?.focus();
                return;
            }
            colorSelect?.classList.remove('invalid');

            if (!quantity || quantity < 1) {
                quantityInput.value = 1;
            }

            let thumb = null;
            if (product && product.thumb) {
                thumb = product.thumb;
            } else {
                const mainImage = card.querySelector('[data-gallery-main] img');
                if (mainImage?.src) {
                    thumb = mainImage.src;
                }
            }

            const item = mountItem(product, size, color, Math.max(1, quantity), thumb);
            const existing = cartStore.get(item.key);
            if (existing) {
                item.quantity += existing.quantity;
            }
            cartStore.set(item.key, item);
            persistCart();
            renderCart();
            refreshSummary();
            updateCheckoutModal();
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

checkoutForm?.addEventListener('submit', async (event) => {
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
    debugCartSnapshot('ANTES DO SUBMIT checkoutForm');

    updateCheckoutModal();
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
