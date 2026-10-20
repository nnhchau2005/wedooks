/* =========================================================
   js/common.js – Hàm dùng chung cho mọi trang.
   Nạp TRƯỚC file JS riêng của từng trang:
     <script src="js/common.js" defer></script>
     <script src="js/index.js" defer></script>
   ========================================================= */

/** 650000 → "650.000 đ" */
const fmt = (n) => Number(n).toLocaleString('vi-VN') + ' đ';

/** Chống chèn mã HTML khi đưa dữ liệu (tên sản phẩm…) vào innerHTML */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
  { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

/**
 * Gửi JSON tới 1 file PHP, trả về JSON server trả lời (luôn có trường ok).
 * Header X-Requested-With: fetch → cart_api.php / order_api.php dùng để chặn request giả.
 */
async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
    body: JSON.stringify(body)
  });
  const data = await res.json().catch(() => ({ ok: false, error: 'Phản hồi không hợp lệ từ máy chủ.' }));
  if (!res.ok) data.ok = false;
  return data;
}

/** Gọi API giỏ hàng (bảng CartItems). Lỗi thì ném Error để nơi gọi hiện thông báo. */
async function cartApi(body) {
  const data = await postJson('cart_api.php', body);
  if (!data.ok) throw new Error(data.error || 'Có lỗi xảy ra.');
  return data;
}

/** Lấy giỏ hiện tại (GET cart_api.php) */
async function fetchCart() {
  const res = await fetch('cart_api.php', { cache: 'no-store' });
  return res.json();
}

/** Cập nhật số trên icon giỏ hàng ở header */
function updateBadge(count) {
  const badge = document.getElementById('cart-badge');
  if (badge) badge.textContent = count;
}
