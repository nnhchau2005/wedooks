<?php
require __DIR__ . '/db.php';

$dbError = null;
$cart = ['items' => [], 'count' => 0, 'subtotal' => 0];
try {
    $cart = cart_summary();   // CartItems JOIN Products – giá lấy từ SQL
} catch (Throwable $ex) {
    $dbError = page_error($ex, 'cart', 'giỏ hàng');
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giỏ hàng - wedooks.</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="js/common.js" defer></script>
  <script src="js/cart.js" defer></script>
</head>
<body class="page">

  <!-- Header (đồng bộ với Trang chủ) -->
  <header class="site-header">
    <div class="container header-bar">
      <div class="header-side header-side--mobile">
        <button class="menu-btn" aria-label="Menu"><svg class="icon icon--md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      </div>
      <div class="header-side header-side--desktop">
        <a href="index.php" class="back-link">← Tiếp tục mua sắm</a>
      </div>
      <a href="index.php" class="logo">wedooks.</a>
      <div class="header-side header-side--right">
        <?php include __DIR__ . '/account_link.php'; ?>
        <a href="cart.php" class="cart-link cart-link--active" title="Giỏ hàng">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119.993z"/></svg>
          <span id="cart-badge" class="cart-badge"><?= (int)$cart['count'] ?></span>
        </a>
      </div>
    </div>
  </header>

  <main class="site-main container cart-main">
    <div class="page-head">
      <h1 class="page-title">Giỏ hàng</h1>
      <span id="cart-count-text" class="page-head__meta"><?= (int)$cart['count'] ?> Sản phẩm</span>
    </div>

    <?php if ($dbError): ?>
      <p class="alert"><?= e($dbError) ?></p>
    <?php endif; ?>
    <p id="cart-error" class="alert is-hidden"></p>

    <!-- Khi giỏ CÓ sản phẩm (2 cột) -->
    <div id="cart-content" class="cart-layout is-hidden">

      <!-- Cột trái: danh sách sản phẩm -->
      <div class="cart-list">
        <div class="cart-list__head">
          <div class="cart-col--product">Sản phẩm</div>
          <div class="cart-col--qty">Số lượng</div>
          <div class="cart-col--total">Tổng tiền</div>
        </div>
        <div id="cart-items" class="cart-items">
          <!-- JavaScript render vào đây -->
        </div>
      </div>

      <!-- Cột phải: tóm tắt -->
      <div class="summary">
        <h2 class="summary__title">Tóm tắt đơn hàng</h2>

        <div class="summary__rows">
          <div class="summary__row">
            <span>Tạm tính</span>
            <span id="cart-subtotal" class="summary__value">0 đ</span>
          </div>
          <div class="summary__row">
            <span>Phí giao hàng</span>
            <span class="summary__note">Tính ở bước sau</span>
          </div>
        </div>

        <div class="summary__total">
          <span class="summary__total-label">Tổng thanh toán</span>
          <span id="cart-total" class="summary__total-value">0 đ</span>
        </div>

        <a href="checkout.php" class="btn btn--dark btn--block btn--shadow">Tiến hành thanh toán</a>
        <?php if (!current_user()): ?>
          <p class="summary__login-note">Bạn cần <a href="login.php?next=checkout.php">đăng nhập bằng email</a> để thanh toán.</p>
        <?php endif; ?>

        <div class="secure-note">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
          <span>Thanh toán an toàn 100%</span>
        </div>
      </div>
    </div>

    <!-- Khi giỏ TRỐNG -->
    <div id="cart-empty" class="cart-empty is-hidden">
      <div class="circle-icon circle-icon--muted">
        <svg class="icon icon--lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119.993z"/></svg>
      </div>
      <h2 class="cart-empty__title">Giỏ hàng trống</h2>
      <p class="cart-empty__text">Bạn chưa thêm bất kỳ sản phẩm nào vào giỏ hàng. Hãy khám phá các bộ sưu tập mới nhất của chúng tôi.</p>
      <a href="index.php" class="btn btn--dark">Tiếp tục mua sắm</a>
    </div>
  </main>

  <!-- Footer (rút gọn) -->
  <footer class="site-footer site-footer--simple">
    <div class="container footer-bottom">
      <p>&copy; 2026 WEDOOKS VIETNAM. Tất cả các quyền được bảo lưu.</p>
    </div>
  </footer>

  <!-- Dữ liệu giỏ do PHP đọc từ SQL – js/cart.js đọc khối này (không phải code) -->
  <script id="cart-data" type="application/json"><?= json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</body>
</html>
