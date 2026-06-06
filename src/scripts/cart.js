const CART_KEY      = 'dcien_cart';
const DISCOUNTS_KEY = 'dcien_cart_discounts'; // array de descuentos
const DISCOUNT_KEY  = 'dcien_cart_discount';  // legacy: QR bono / flujo antiguo

export function getCart() {
  try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
  catch { return []; }
}

/** Devuelve array de descuentos activos (stackables + posible QR bono) */
export function getDiscounts() {
  try {
    const arr = localStorage.getItem(DISCOUNTS_KEY);
    if (arr) {
      let parsed = JSON.parse(arr);
      // Sanitizar descuentos corruptos o antiguos
      parsed = parsed.filter(d => d && d.type && typeof d.value !== 'undefined' && !isNaN(d.value));
      return parsed;
    }
    // fallback: migrar legacy si existe
    const legacy = localStorage.getItem(DISCOUNT_KEY);
    if (legacy) {
      const parsedLegacy = JSON.parse(legacy);
      if (parsedLegacy && parsedLegacy.type && typeof parsedLegacy.value !== 'undefined' && !isNaN(parsedLegacy.value)) {
        return [parsedLegacy];
      }
    }
    return [];
  } catch { return []; }
}

/** Compat. con código antiguo que espera un único objeto */
export function getDiscount() {
  const list = getDiscounts();
  return list.length > 0 ? list[0] : null;
}

/**
 * @param {object} item
 * @param {object|object[]|null} discounts  objeto único o array
 */
export function addToCart(item, discounts = null) {
  const cart = getCart();
  const isDuplicate = cart.some(i => i.seriesSlug === item.seriesSlug && i.number === item.number);
  if (isDuplicate) return null;
  cart.push(item);
  localStorage.setItem(CART_KEY, JSON.stringify(cart));

  if (discounts && getDiscounts().length === 0) {
    const list = Array.isArray(discounts) ? discounts : [discounts];
    localStorage.setItem(DISCOUNTS_KEY, JSON.stringify(list));
  }
  _notify();
  return cart;
}

export function setDiscounts(discounts) {
  if (!discounts || discounts.length === 0) {
    localStorage.removeItem(DISCOUNTS_KEY);
    localStorage.removeItem(DISCOUNT_KEY);
  } else {
    localStorage.setItem(DISCOUNTS_KEY, JSON.stringify(discounts));
  }
  _notify();
}

export function removeFromCart(index) {
  const cart = getCart();
  cart.splice(index, 1);
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  if (cart.length === 0) {
    localStorage.removeItem(DISCOUNTS_KEY);
    localStorage.removeItem(DISCOUNT_KEY);
  }
  _notify();
  return cart;
}

export function clearCart() {
  localStorage.removeItem(CART_KEY);
  localStorage.removeItem(DISCOUNTS_KEY);
  localStorage.removeItem(DISCOUNT_KEY);
  _notify();
}

export function getCartCount() {
  return getCart().length;
}

export function getCartSubtotal(cart) {
  return cart.reduce((s, i) => s + Number(i.price), 0);
}

function _notify() {
  window.dispatchEvent(new CustomEvent('cart:updated', {
    detail: { cart: getCart(), discounts: getDiscounts() }
  }));
}
