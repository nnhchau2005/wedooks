/* =========================================================
   js/cart.js – Trang giỏ hàng (cart.php)
   - Đọc giỏ PHP in sẵn trong <script id="cart-data" type="application/json">
   - Vẽ danh sách sản phẩm, nút + / − / Xoá → cart_api.php
   Cần nạp js/common.js trước.
   ========================================================= */

// Dữ liệu giỏ lần đầu do PHP đọc từ SQL (CartItems JOIN Products)
let CART = JSON.parse(document.getElementById('cart-data').textContent);
let busy = false;

// Gửi thay đổi lên server, nhận lại giỏ mới rồi vẽ lại
async function send(body) {
  if (busy) return;
  busy = true;
  document.body.classList.add('is-busy');
  const errBox = document.getElementById('cart-error');
  try {
    CART = await cartApi(body);
    errBox.classList.add('is-hidden');
    renderCart();
  } catch (err) {
    errBox.textContent = err.message;
    errBox.classList.remove('is-hidden');
  } finally {
    busy = false;
    document.body.classList.remove('is-busy');
  }
}

// 1 listener cho mọi nút trong danh sách: data-action = dec | inc | remove, data-id = CartItemID
document.getElementById('cart-items').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const cartId = Number(btn.dataset.id);
  if (btn.dataset.action === 'inc')    send({ action: 'update', cart_id: cartId, delta: 1 });
  if (btn.dataset.action === 'dec')    send({ action: 'update', cart_id: cartId, delta: -1 });
  if (btn.dataset.action === 'remove') send({ action: 'remove', cart_id: cartId });
});

function itemHtml(item) {
  const id = Number(item.cart_id);
  return `
    <div class="cart-item">
      <div class="cart-item__info">
        <a href="index.php" class="cart-item__thumb">
          <img src="${esc(item.image)}" alt="${esc(item.name)}">
        </a>
        <div class="cart-item__text">
          <a href="index.php" class="cart-item__name">${esc(item.name)}</a>
          <p class="cart-item__size">Kích cỡ: <strong>${esc(item.size || 'M')}</strong></p>
          <p class="cart-item__price-mobile">${fmt(item.price)}</p>
          <button type="button" class="cart-item__remove" data-action="remove" data-id="${id}">
            <svg class="icon icon--xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Xóa
          </button>
        </div>
      </div>
      <div class="cart-item__controls">
        <div class="cart-item__qty-wrap">
          <div class="qty">
            <button type="button" class="qty__btn" data-action="dec" data-id="${id}">-</button>
            <span class="qty__value">${Number(item.quantity)}</span>
            <button type="button" class="qty__btn" data-action="inc" data-id="${id}">+</button>
          </div>
        </div>
        <div class="cart-item__total">${fmt(item.line_total)}</div>
      </div>
    </div>`;
}

function renderCart() {
  const items = CART.items || [];

  updateBadge(CART.count);
  document.getElementById('cart-count-text').textContent = `${CART.count} Sản phẩm`;

  const empty = items.length === 0;
  document.getElementById('cart-content').classList.toggle('is-hidden', empty);
  document.getElementById('cart-empty').classList.toggle('is-hidden', !empty);
  if (empty) return;

  document.getElementById('cart-items').innerHTML = items.map(itemHtml).join('');
  document.getElementById('cart-subtotal').textContent = fmt(CART.subtotal);
  document.getElementById('cart-total').textContent = fmt(CART.subtotal);
}

// Vẽ ngay khi mở trang
renderCart();

// Quay lại bằng nút Back → lấy lại giỏ mới nhất từ SQL
window.addEventListener('pageshow', (e) => {
  if (e.persisted) fetchCart().then(d => { if (d.ok) { CART = d; renderCart(); } });
});
