const CART_KEY     = 'dcien_cart';
const DISCOUNT_KEY = 'dcien_cart_discount';

export function getCart() {
  try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
  catch { return []; }
}

export function getDiscount() {
  try {
    const d = localStorage.getItem(DISCOUNT_KEY);
    return d ? JSON.parse(d) : null;
  } catch { return null; }
}

export function addToCart(item, discount = null) {
  const cart = getCart();
  const isDuplicate = cart.some(i => i.seriesSlug === item.seriesSlug && i.number === item.number);
  if (isDuplicate) return null;
  cart.push(item);
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  if (discount && !getDiscount()) {
    localStorage.setItem(DISCOUNT_KEY, JSON.stringify(discount));
  }
  _notify();
  return cart;
}

export function removeFromCart(index) {
  const cart = getCart();
  cart.splice(index, 1);
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  if (cart.length === 0) localStorage.removeItem(DISCOUNT_KEY);
  _notify();
  return cart;
}

export function clearCart() {
  localStorage.removeItem(CART_KEY);
  localStorage.removeItem(DISCOUNT_KEY);
  _notify();
}

export function getCartCount() {
  return getCart().length;
}

export function getCartSubtotal(cart) {
  return cart.reduce((s, i) => s + i.price, 0);
}

function _notify() {
  window.dispatchEvent(new CustomEvent('cart:updated', {
    detail: { cart: getCart(), discount: getDiscount() }
  }));
}
