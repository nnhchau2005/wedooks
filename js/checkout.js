/* =========================================================
   js/checkout.js – Trang thanh toán (checkout.php)
   - Gửi form → order_api.php (INSERT Orders + OrderItems, xoá CartItems)
   - COD / VNPAY : hiện màn hình "Đặt hàng thành công"
   - MOMO        : chuyển sang momo_pay.php?order_id=...
   Cần nạp js/common.js trước.
   ========================================================= */

const METHOD_LABEL = { COD: 'Thanh toán khi nhận hàng (COD)', VNPAY: 'VNPAY', MOMO: 'Ví MoMo' };

// Tên trường server trả về khi báo lỗi ↔ id ô nhập trên form
const FIELD_IDS = {
  name: 'fullname', phone: 'phone', email: 'email', province: 'province',
  district: 'district', ward: 'ward', street: 'street'
};

const $ = (id) => document.getElementById(id);

// Tô đỏ ô sai (class is-invalid trong style.css) + hiện hộp lỗi
function showError(message, fields = {}) {
  Object.values(FIELD_IDS).forEach(id => $(id).classList.remove('is-invalid'));
  Object.keys(fields).forEach(k => FIELD_IDS[k] && $(FIELD_IDS[k]).classList.add('is-invalid'));

  const box = $('checkout-error');
  box.textContent = message;
  box.classList.toggle('is-hidden', !message);

  const first = Object.keys(fields).find(k => FIELD_IDS[k]);
  if (first) $(FIELD_IDS[first]).focus();
}

// Đọc dữ liệu form (KHÔNG gửi giá/tổng tiền – server tự tính lại từ SQL)
function readForm() {
  const val = (id) => $(id).value.trim();
  return {
    name:     val('fullname'),
    phone:    val('phone'),
    email:    val('email'),
    province: $('province').value,
    district: $('district').value,
    ward:     $('ward').value,
    street:   val('street'),
    note:     val('note'),
    payment:  document.querySelector('input[name="payment"]:checked').value
  };
}

async function submitOrder(e) {
  e.preventDefault();
  const btn = $('submit-btn');
  let redirecting = false;

  btn.disabled = true;
  $('submit-text').textContent = 'Đang xử lý…';
  showError('');

  try {
    const result = await postJson('order_api.php', readForm());

    if (!result.ok) {
      showError(result.error || 'Không đặt được hàng.', result.fields || {});
      return;
    }

    // MoMo: đơn đã lưu (PENDING) → sang momo_pay.php để tạo giao dịch MoMo
    if (result.payment_method === 'MOMO') {
      redirecting = true;
      $('submit-text').textContent = 'Đang chuyển sang MoMo…';
      window.location.href = 'momo_pay.php?order_id=' + encodeURIComponent(result.order_id);
      return;
    }

    showSuccess(result);
  } catch (err) {
    showError('Không kết nối được máy chủ. Kiểm tra PHP server đang chạy.');
  } finally {
    if (!redirecting) {
      btn.disabled = false;
      $('submit-text').textContent = 'Hoàn tất đặt hàng';
    }
  }
}

// Màn hình "Đặt hàng thành công" (COD, VNPAY)
function showSuccess(order) {
  $('ok-order-id').textContent = order.order_id;
  $('ok-method').textContent   = METHOD_LABEL[order.payment_method] || order.payment_method;
  $('ok-status').textContent   = order.payment_method === 'COD' ? 'Chờ giao hàng' : 'Chờ thanh toán';
  $('ok-total').textContent    = fmt(order.total);
  $('ok-note').textContent = order.payment_method === 'COD'
    ? 'Bạn sẽ thanh toán khi nhận hàng.'
    : 'Đơn đã được lưu với trạng thái chờ thanh toán. (Cổng ' + order.payment_method + ' chưa được kết nối.)';

  $('checkout-layout').classList.add('is-hidden');
  $('order-success').classList.remove('is-hidden');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

$('checkoutForm').addEventListener('submit', submitOrder);

// Bấm Back từ trang MoMo về: trình duyệt có thể hiện bản cũ (giỏ đã bị xoá sau khi đặt)
// → tải lại để PHP kiểm tra giỏ, giỏ trống thì tự chuyển về cart.php
window.addEventListener('pageshow', (e) => { if (e.persisted) location.reload(); });
