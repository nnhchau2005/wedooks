<?php
/**
 * login.php?next=checkout.php – Đăng nhập / Đăng ký bằng email.
 *   ?mode=register → hiện form đăng ký. Xong thì quay về trang "next".
 */
require __DIR__ . '/db.php';

$next     = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$register = ($_GET['mode'] ?? $_POST['mode'] ?? '') === 'register';
$error    = null;
$email    = '';
$name     = '';

if (current_user()) {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $name  = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $pass  = (string)($_POST['password'] ?? '');
    try {
        if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Phiên làm việc đã hết hạn, vui lòng thử lại.');
        }
        $register ? user_register($email, $pass, $name) : user_login($email, $pass);
        header('Location: ' . $next);
        exit;
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = page_error($ex, 'login', 'tài khoản');
    }
}

$switch = 'login.php?' . http_build_query(['next' => $next] + ($register ? [] : ['mode' => 'register']));
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $register ? 'Đăng ký' : 'Đăng nhập' ?> - wedooks.</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="site-header site-header--plain">
    <div class="container header-bar header-bar--center">
      <a href="index.php" class="logo">wedooks.</a>
    </div>
  </header>

  <main class="auth">
    <h1 class="result__title"><?= $register ? 'Tạo tài khoản' : 'Đăng nhập' ?></h1>
    <p class="result__text">
      <?= $next === 'checkout.php' ? 'Vui lòng đăng nhập bằng email để thanh toán giỏ hàng.' : 'Đăng nhập bằng email của bạn.' ?>
    </p>

    <?php if ($error): ?><p class="alert alert--tight"><?= e($error) ?></p><?php endif; ?>

    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <input type="hidden" name="mode" value="<?= $register ? 'register' : 'login' ?>">

      <?php if ($register): ?>
      <div>
        <label class="field__label" for="name">Họ và tên *</label>
        <input type="text" id="name" name="name" required maxlength="100" class="field__input" value="<?= e($name) ?>">
      </div>
      <?php endif; ?>
      <div>
        <label class="field__label" for="email">Email *</label>
        <input type="email" id="email" name="email" required maxlength="150" placeholder="email@example.com" class="field__input" value="<?= e($email) ?>" autofocus>
      </div>
      <div>
        <label class="field__label" for="password">Mật khẩu *</label>
        <input type="password" id="password" name="password" required minlength="6" class="field__input"
               autocomplete="<?= $register ? 'new-password' : 'current-password' ?>">
      </div>
      <button type="submit" class="btn btn--dark btn--block"><?= $register ? 'Đăng ký' : 'Đăng nhập' ?></button>
    </form>

    <p class="terms">
      <?= $register ? 'Đã có tài khoản?' : 'Chưa có tài khoản?' ?>
      <a href="<?= e($switch) ?>"><?= $register ? 'Đăng nhập' : 'Đăng ký ngay' ?></a>
    </p>
  </main>
</body>
</html>
