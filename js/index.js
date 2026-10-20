/* =========================================================
   js/index.js – Trang chủ (index.php)
   - Nút "Khám phá ngay" cuộn xuống phần sản phẩm
   - Nút "Thêm vào giỏ" (data-add-to-cart="<mã SP>") → cart_api.php
   - Toast thông báo góc màn hình
   Cần nạp js/common.js trước.
   ========================================================= */

// Nút "Khám phá ngay" → cuộn xuống phần sản phẩm
document.getElementById('hero-btn').addEventListener('click', () => {
  document.getElementById('products-section').scrollIntoView({ behavior: 'smooth' });
});

// Mọi nút có data-add-to-cart trong lưới sản phẩm (1 listener cho tất cả)
document.getElementById('products-grid').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-add-to-cart]');
  if (btn) addToCart(Number(btn.dataset.addToCart), btn);
});

// Thêm vào giỏ → INSERT/UPDATE CartItems → cập nhật số trên icon giỏ
async function addToCart(productId, btn) {
  btn.disabled = true;
  try {
    const data = await cartApi({ action: 'add', product_id: productId, size: 'M', quantity: 1 }); // mặc định size M
    updateBadge(data.count);
    showToast(`Đã thêm "${data.added}" vào giỏ`);
  } catch (err) {
    showToast(err.message, true);
  } finally {
    btn.disabled = false;
  }
}

// Toast: bật/tắt class is-visible, is-error (định nghĩa trong style.css)
let toastTimer;
function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-message').textContent = message;
  toast.classList.toggle('is-error', isError);
  toast.classList.add('is-visible');

  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2500);
}

// Quay lại trang bằng nút Back (trình duyệt dùng bản cache) → lấy lại số lượng giỏ từ SQL
window.addEventListener('pageshow', (e) => {
  if (e.persisted) fetchCart().then(d => d.ok && updateBadge(d.count));
});
