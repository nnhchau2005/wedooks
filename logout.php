<?php
/* logout.php?t=<csrf> – Đăng xuất rồi về trang chủ. Giỏ hàng (cookie) vẫn giữ nguyên. */
require __DIR__ . '/db.php';

if (hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) user_logout();
header('Location: index.php');
