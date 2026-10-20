<?php
/**
 * checkout.php – Trang thanh toán (thay cho checkout.html).
 *
 * PHP đọc giỏ từ SQL (CartItems JOIN Products) và in sẵn ra trang:
 *   - giỏ trống  → chuyển về cart.php ngay, không cần đợi JavaScript
 *   - tổng tiền, phí ship do server tính (cart_summary trong db.php)
 *   - chưa đăng nhập → chuyển sang login.php, đăng nhập xong quay lại đây
 * JavaScript chỉ còn lo gửi form → order_api.php, rồi:
 *   - COD / VNPAY : hiện màn hình "Đặt hàng thành công"
 *   - MOMO        : chuyển sang momo_pay.php?order_id=...
 */
require __DIR__ . '/db.php';

$user = require_login('checkout.php');   // bắt buộc đăng nhập bằng email mới được thanh toán

$dbError = null;
$cart = ['items' => [], 'count' => 0, 'subtotal' => 0, 'shipping' => 0, 'total' => 0];
try {
    $cart = cart_summary();
} catch (Throwable $ex) {
    $dbError = page_error($ex, 'checkout', 'giỏ hàng');
}

// Giỏ trống → quay lại trang giỏ hàng
if (!$dbError && !$cart['items']) {
    header('Location: cart.php');
    exit;
}

// Danh sách địa chỉ (bản demo – thực tế sẽ lấy từ API tỉnh/thành)
$provinces = ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Cần Thơ'];
$districts = ['Quận 1', 'Quận 3', 'Quận Đống Đa', 'Quận Hải Châu'];
$wards     = ['Phường Bến Nghé', 'Phường Đa Kao', 'Phường Ô Chợ Dừa'];

// Phương thức thanh toán: mã => [tiêu đề, mô tả, nhãn bên phải]
$methods = [
    'COD'   => ['Thanh toán khi nhận hàng', 'Thanh toán bằng tiền mặt (COD)', null],
    'VNPAY' => ['Thẻ ATM / VNPAY / Trả góp', 'Thanh toán an toàn qua cổng VNPAY', 'vnpay'],
    'MOMO'  => ['Ví điện tử MoMo', 'Chuyển sang cổng MoMo để quét QR / nhập thẻ', 'momo'],
];
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thanh toán - wedooks.</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="js/common.js" defer></script>
  <script src="js/checkout.js" defer></script>
</head>
<body>

  <!-- Header tối giản (giảm xao nhãng) -->
  <header class="site-header site-header--plain">
    <div class="container header-bar">
      <a href="cart.php" class="back-link">
        <svg class="icon icon--sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        <span class="back-link__text--tablet-up">Giỏ hàng</span>
      </a>
      <a href="index.php" class="logo">wedooks.</a>
      <div class="header-side header-side--right">
        <?php include __DIR__ . '/account_link.php'; ?>
      </div>
    </div>
  </header>

  <main class="container checkout-main">

    <!-- ĐẶT HÀNG THÀNH CÔNG (ẩn – hiện sau khi lưu đơn COD/VNPAY) -->
    <section id="order-success" class="result is-hidden">
      <div class="circle-icon circle-icon--ok">
        <svg class="icon icon--lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h1 class="result__title">Đặt hàng thành công</h1>
      <p class="result__text">Cảm ơn bạn đã mua sắm tại wedooks. Đơn hàng đã được ghi nhận.</p>

      <div class="result__box">
        <div class="result__row"><span class="result__label">Mã đơn hàng</span><strong id="ok-order-id" class="result__value"></strong></div>
        <div class="result__row"><span class="result__label">Phương thức</span><span id="ok-method" class="result__value"></span></div>
        <div class="result__row"><span class="result__label">Trạng thái</span><span id="ok-status" class="result__value"></span></div>
        <div class="result__row result__row--total"><span class="result__label">Tổng cộng</span><strong id="ok-total" class="result__value"></strong></div>
      </div>
      <p id="ok-note" class="result__note"></p>

      <a href="index.php" class="btn btn--dark">Tiếp tục mua sắm</a>
    </section>

    <div id="checkout-layout" class="checkout-layout">

      <!-- CỘT PHẢI: Tóm tắt đơn hàng (in sẵn bằng PHP) -->
      <aside class="checkout-summary">
        <h2 class="summary__title">Tóm tắt đơn hàng</h2>

        <?php if ($dbError): ?>
          <p class="alert"><?= e($dbError) ?></p>
        <?php endif; ?>

        <div class="checkout-items">
          <?php foreach ($cart['items'] as $item): ?>
          <div class="checkout-item">
            <div class="checkout-item__thumb">
              <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
              <span class="checkout-item__qty"><?= (int)$item['quantity'] ?></span>
            </div>
            <div class="checkout-item__info">
              <h3 class="checkout-item__name"><?= e($item['name']) ?></h3>
              <p class="checkout-item__size">Size: <?= e($item['size'] ?: 'M') ?></p>
            </div>
            <div class="checkout-item__price"><?= money($item['line_total']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="checkout-money">
          <div class="summary__row">
            <span>Tạm tính</span>
            <span class="summary__value"><?= money($cart['subtotal']) ?></span>
          </div>
          <div class="summary__row">
            <span>Phí vận chuyển</span>
            <span class="summary__value"><?= $cart['shipping'] === 0 ? 'Miễn phí' : money($cart['shipping']) ?></span>
          </div>
        </div>

        <div class="checkout-total">
          <span class="checkout-total__label">Tổng cộng</span>
          <span class="summary__total-value"><?= money($cart['total']) ?></span>
        </div>
      </aside>

      <!-- CỘT TRÁI: Form thông tin -->
      <section class="checkout-form-col">
        <form id="checkoutForm" novalidate>

          <!-- 1. Thông tin liên hệ -->
          <div class="form-section">
            <h2 class="form-section__title">1. Thông tin liên hệ</h2>
            <div class="form-grid form-grid--2">
              <div class="field--full">
                <label class="field__label" for="fullname">Họ và tên *</label>
                <input type="text" id="fullname" required maxlength="100" placeholder="Nhập họ và tên của bạn" class="field__input" value="<?= e($user['name']) ?>">
              </div>
              <div>
                <label class="field__label" for="phone">Số điện thoại *</label>
                <input type="tel" id="phone" required maxlength="15" placeholder="0901234567" class="field__input">
              </div>
              <div>
                <label class="field__label" for="email">Email (tài khoản đăng nhập)</label>
                <input type="email" id="email" class="field__input" value="<?= e($user['email']) ?>" readonly>
              </div>
            </div>
          </div>

          <!-- 2. Địa chỉ giao hàng -->
          <div class="form-section">
            <h2 class="form-section__title">2. Địa chỉ giao hàng</h2>
            <div class="form-grid form-grid--3">
              <div>
                <label class="field__label" for="province">Tỉnh / Thành phố *</label>
                <select id="province" required class="field__input">
                  <option value="" disabled selected>Chọn Tỉnh/Thành</option>
                  <?php foreach ($provinces as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="field__label" for="district">Quận / Huyện *</label>
                <select id="district" required class="field__input">
                  <option value="" disabled selected>Chọn Quận/Huyện</option>
                  <?php foreach ($districts as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="field__label" for="ward">Phường / Xã *</label>
                <select id="ward" required class="field__input">
                  <option value="" disabled selected>Chọn Phường/Xã</option>
                  <?php foreach ($wards as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="field--full">
                <label class="field__label" for="street">Số nhà, Tên đường *</label>
                <input type="text" id="street" required maxlength="200" placeholder="VD: 123 Đường Nguyễn Huệ" class="field__input">
              </div>
              <div class="field--full field--gap-top">
                <label class="field__label" for="note">Ghi chú đơn hàng (Tuỳ chọn)</label>
                <textarea id="note" rows="2" maxlength="500" placeholder="Ghi chú thêm về thời gian giao hàng..." class="field__input"></textarea>
              </div>
            </div>
          </div>

          <!-- 3. Phương thức thanh toán -->
          <div class="form-section">
            <h2 class="form-section__title">3. Phương thức thanh toán</h2>
            <div class="pay-options">
              <?php $first = true; foreach ($methods as $code => [$title, $desc, $badge]): ?>
              <label class="pay-option">
                <span class="pay-option__main">
                  <input type="radio" name="payment" value="<?= e($code) ?>" class="pay-option__radio" <?= $first ? 'checked' : '' ?>>
                  <span>
                    <span class="pay-option__title"><?= e($title) ?></span>
                    <span class="pay-option__desc"><?= e($desc) ?></span>
                  </span>
                </span>
                <?php if ($badge): ?>
                  <span class="pay-badge pay-badge--<?= e($badge) ?>"><?= e($code) ?></span>
                <?php else: ?>
                  <svg class="icon icon--md pay-option__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                <?php endif; ?>
              </label>
              <?php $first = false; endforeach; ?>
            </div>
          </div>

          <!-- Thông báo lỗi -->
          <p id="checkout-error" class="alert alert--tight is-hidden"></p>

          <!-- Nút xác nhận -->
          <button type="submit" id="submit-btn" class="btn btn--dark btn--block btn--large" <?= $dbError ? 'disabled' : '' ?>>
            <span id="submit-text">Hoàn tất đặt hàng</span>
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>

          <p class="terms">Bằng việc đặt hàng, bạn đồng ý với <a href="#">Điều khoản sử dụng</a> của chúng tôi.</p>
        </form>
      </section>

    </div>
  </main>

</body>
</html>
