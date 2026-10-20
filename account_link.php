<?php
/* account_link.php – Icon tài khoản trên header: chưa đăng nhập → login.php, đã đăng nhập → tên + Đăng xuất. */
$acc = current_user();
?>
<?php if ($acc): ?>
  <span class="account-link header-icon--tablet-up" title="<?= e($acc['email']) ?>">
    Chào, <?= e($acc['name']) ?> · <a href="logout.php?t=<?= e(csrf_token()) ?>">Đăng xuất</a>
  </span>
<?php else: ?>
  <a href="login.php?next=<?= e(urlencode(basename($_SERVER['SCRIPT_NAME']))) ?>" class="header-icon header-icon--tablet-up" title="Đăng nhập">
    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
  </a>
<?php endif; ?>
