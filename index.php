<?php
require __DIR__ . '/db.php';

$dbError   = null;
$products  = [];
$cartCount = 0;
try {
    $products  = get_products();   // SELECT ... FROM Products
    $cartCount = cart_count();     // SUM(Quantity) FROM CartItems của khách này
} catch (Throwable $ex) {
    error_log('[index] ' . $ex->getMessage());
    $dbError = $ex instanceof ProductColumnsException ? $ex->getMessage() : 'Không tải được sản phẩm từ cơ sở dữ liệu. Kiểm tra lại kết nối SQL.';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>wedooks. - Local Brand Thời Trang Nam</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="js/common.js" defer></script>
  <script src="js/index.js" defer></script>
</head>
<body class="page">

  <!-- Thanh thông báo trên cùng -->
  <div class="announce">Miễn phí vận chuyển cho đơn hàng từ 1.000.000đ</div>

  <!-- Header -->
  <header class="site-header">
    <div class="container header-bar">

      <!-- Nút menu (điện thoại) -->
      <div class="header-side header-side--mobile">
        <button class="menu-btn" aria-label="Menu">
          <svg class="icon icon--md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>

      <!-- Icon tiện ích (máy tính – trái) -->
      <div class="header-side header-side--desktop">
        <button class="header-icon" title="Cửa hàng"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg></button>
        <button class="header-icon" title="Hỗ trợ"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a6.25 6.25 0 10-9.12 0m9.12 0a6.25 6.25 0 11-9.12 0m9.12 0a6.25 6.25 0 10-9.12 0"/></svg></button>
      </div>

      <!-- Logo (giữa) -->
      <a href="index.php" class="logo">wedooks.</a>

      <!-- Tìm kiếm, tài khoản, giỏ hàng (phải) -->
      <div class="header-side header-side--right">
        <button class="header-icon header-icon--tablet-up" title="Tìm kiếm"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg></button>
        <button class="header-icon header-icon--tablet-up" title="Tài khoản"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg></button>
        <a href="cart.php" class="cart-link" title="Giỏ hàng">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119.993z"/></svg>
          <span id="cart-badge" class="cart-badge"><?= (int)$cartCount ?></span>
        </a>
      </div>
    </div>

    <!-- Menu ngang (máy tính) -->
    <nav class="main-nav">
      <a href="index.php" class="is-active">Trang chủ</a>
      <a href="#">Bộ sưu tập mới</a>
      <a href="#">Áo Nam</a>
      <a href="#">Quần Nam</a>
      <a href="#">Phụ kiện</a>
      <a href="#" class="is-sale">Sale up to 50%</a>
    </nav>
  </header>

  <main class="site-main">
    <!-- Banner -->
    <section class="hero">
      <img src="https://images.unsplash.com/photo-1490578474895-699cd4e2cf59?q=80&w=2000&auto=format&fit=crop" alt="Banner" class="hero__img">
      <div class="hero__content">
        <p class="hero__eyebrow">Mùa Thu Đông 2026</p>
        <h1 class="hero__title">MINIMALIST<br>COLLECTION</h1>
        <button type="button" id="hero-btn" class="hero__btn">Khám phá ngay</button>
      </div>
    </section>

    <!-- Sản phẩm -->
    <section id="products-section" class="container products">
      <div class="section-head">
        <h2 class="section-title">Sản phẩm mới</h2>
        <a href="#" class="section-link">Xem tất cả →</a>
      </div>

      <?php if ($dbError): ?>
        <p class="alert"><?= e($dbError) ?></p>
      <?php elseif (!$products): ?>
        <p class="empty-text">Chưa có sản phẩm nào trong bảng Products.</p>
      <?php endif; ?>

      <!-- Sản phẩm lấy từ bảng Products (SQL) – 2 cột điện thoại, 3–4 cột máy tính -->
      <div id="products-grid" class="product-grid">
        <?php foreach ($products as $p): ?>
        <div class="product-card">
          <div class="product-card__media">
            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="product-card__img">

            <!-- Nút hiện khi rê chuột (máy tính) -->
            <div class="product-card__overlay">
              <button type="button" class="product-card__add" data-add-to-cart="<?= (int)$p['id'] ?>">Thêm vào giỏ</button>
            </div>
          </div>

          <h3 class="product-card__name"><?= e($p['name']) ?></h3>
          <p class="product-card__price"><?= money($p['price']) ?></p>

          <!-- Nút luôn hiện (điện thoại) -->
          <button type="button" class="product-card__add-mobile" data-add-to-cart="<?= (int)$p['id'] ?>">Thêm vào giỏ</button>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="view-all-mobile">
        <a href="#" class="btn btn--outline btn--small">Xem tất cả</a>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <span class="footer-brand">WEDOOKS.</span>
          <p class="footer-text">Thương hiệu thời trang nam theo đuổi phong cách tối giản, thanh lịch và chất lượng vượt thời gian.</p>
          <div class="footer-social">
            <a href="#">FB</a>
            <a href="#">IG</a>
            <a href="#">TT</a>
          </div>
        </div>
        <div>
          <h3 class="footer-heading">Về chúng tôi</h3>
          <ul class="footer-links">
            <li><a href="#">Câu chuyện thương hiệu</a></li>
            <li><a href="#">Hệ thống cửa hàng</a></li>
            <li><a href="#">Tuyển dụng</a></li>
          </ul>
        </div>
        <div>
          <h3 class="footer-heading">Hỗ trợ khách hàng</h3>
          <ul class="footer-links">
            <li><a href="#">Chính sách đổi trả</a></li>
            <li><a href="#">Chính sách bảo mật</a></li>
            <li><a href="#">Hướng dẫn chọn size</a></li>
          </ul>
        </div>
        <div>
          <h3 class="footer-heading">Đăng ký nhận tin</h3>
          <p class="footer-text">Nhận thông tin về các bộ sưu tập mới và ưu đãi độc quyền.</p>
          <form class="newsletter">
            <input type="email" placeholder="Email của bạn" class="newsletter__input">
            <button type="submit" class="newsletter__btn">Gửi</button>
          </form>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 WEDOOKS VIETNAM. Tất cả các quyền được bảo lưu.</p>
      </div>
    </div>
  </footer>

  <!-- Toast (thông báo góc màn hình) -->
  <div id="toast" class="toast">
    <svg class="icon toast__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <span id="toast-message">Đã thêm vào giỏ hàng</span>
  </div>

</body>
</html>
