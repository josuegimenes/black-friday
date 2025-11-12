const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const catalogProducts = window.catalogProducts || [];
const cartStore = new Map();
const CART_STORAGE_KEY = 'vesteme_cart_v1';

const cartItemsWrapper = document.querySelector('.cart-items');
const summaryItems = document.getElementById('summaryItems');
const summarySavings = document.getElementById('summarySavings');
const summaryValue = document.getElementById('summaryValue');
const cartInvest = document.getElementById('cartInvest');
const cartSavings = document.getElementById('cartSavings');
const checkoutOverlay = document.getElementById('checkoutOverlay');
const checkoutList = document.getElementById('checkoutList');
const checkoutTotals = document.getElementById('checkoutTotals');
const cartPayloadInput = document.getElementById('cartPayload');
const checkoutForm = document.getElementById('checkoutForm');

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

const renderCart = () => {
    if (!cartItemsWrapper) return;

    cartItemsWrapper.innerHTML = '';

    if (cartStore.size === 0) {
        cartItemsWrapper.innerHTML = '<p class="empty">Selecione as pecas para visualizar aqui.</p>';
    } else {
        cartStore.forEach((item) => {
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <div>
                    <strong>${item.name}</strong>
                    <span>${item.size} | ${item.color} | ${item.quantity} un</span>
                </div>
                <div>
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
    if (summaryValue) summaryValue.textContent = currency.format(totalValue);
    if (summarySavings) summarySavings.textContent = currency.format(totalSavings);
    if (cartInvest) cartInvest.textContent = currency.format(totalValue);
    if (cartSavings) cartSavings.textContent = `Economia: ${currency.format(totalSavings)}`;
};

const createKey = (id, size, color) => `${id}__${size}__${color}`;

const closeCheckout = () => checkoutOverlay?.classList.remove('active');

const updateCheckoutModal = () => {
    if (!checkoutOverlay) return;

    if (!checkoutOverlay.classList.contains('active')) {
        if (cartPayloadInput) {
            cartPayloadInput.value = JSON.stringify(getCartSnapshot());
        }
        return;
    }

    if (cartStore.size === 0) {
        closeCheckout();
        return;
    }

    const snapshot = getCartSnapshot();
    if (checkoutList) {
        checkoutList.innerHTML = snapshot.items
            .map(
                (item) =>
                    `<li><span>${item.quantity}x ${item.name} (${item.size}/${item.color})</span><strong>${currency.format(
                        item.quantity * item.salePrice,
                    )}</strong></li>`,
            )
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
        const payload = {
            version: 1,
            updatedAt: Date.now(),
            items: Array.from(cartStore.values()),
        };
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

const mountItem = (product, size, color, quantity) => ({
    key: createKey(product.id, size, color),
    productId: product.id,
    name: product.name,
    size,
    color,
    quantity,
    originalPrice: product.original_price,
    salePrice: product.sale_price,
});

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
        const product = catalogProducts.find((item) => item.id === productId);
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

            const item = mountItem(product, size, color, Math.max(1, quantity));
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

checkoutForm?.addEventListener('submit', (event) => {
    if (cartStore.size === 0) {
        event.preventDefault();
        alert('Adicione produtos antes de enviar sua reserva.');
        return;
    }
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
