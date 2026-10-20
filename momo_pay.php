<?php
/**
 * momo_pay.php?order_id=WDK...  – Bắt đầu thanh toán MoMo cho 1 đơn đã lưu.
 *
 * Khác demo giảng viên: số tiền KHÔNG lấy từ form (dễ bị sửa) mà đọc TotalAmount
 * của đơn trong SQL. Gọi MoMo tạo giao dịch → chuyển khách sang payUrl.
 * Dùng lại được để "Thanh toán lại" nếu lần trước khách huỷ.
 */
require __DIR__ . '/momo.php';

$orderId = (string)($_GET['order_id'] ?? '');
$user    = require_login('momo_pay.php?order_id=' . urlencode($orderId));

try {
    if (!preg_match('/^WDK\d{12}$/', $orderId)) {
        throw new InvalidArgumentException('Mã đơn hàng không hợp lệ.');
    }
    $order = get_order($orderId);
    if (!$order || (int)$order['UserID'] !== $user['id']) {
        throw new InvalidArgumentException('Không tìm thấy đơn hàng trong tài khoản của bạn.');
    }
    if ($order['PaymentMethod'] !== 'MOMO') {
        throw new InvalidArgumentException('Đơn này không chọn thanh toán bằng MoMo.');
    }
    if ($order['PaymentStatus'] === 'PAID') {
        momo_page('ok', 'Đơn đã thanh toán', 'Đơn hàng này đã được thanh toán trước đó.',
            ['Mã đơn hàng' => $orderId, 'Số tiền' => money($order['TotalAmount'])],
            [['Tiếp tục mua sắm', 'index.php']]);
        exit;
    }

    $res = momo_create_payment(
        $orderId,
        (int)round((float)$order['TotalAmount']),
        "Thanh toán đơn hàng {$orderId} tại wedooks."
    );

    header('Location: ' . $res['payUrl']);   // sang trang thanh toán của MoMo
    exit;

} catch (Throwable $ex) {
    error_log('[momo_pay] ' . $ex->getMessage());
    $msg = $ex instanceof InvalidArgumentException || $ex instanceof RuntimeException
        ? $ex->getMessage()
        : 'Lỗi hệ thống, vui lòng thử lại.';
    momo_page('fail', 'Chưa chuyển được sang MoMo', $msg,
        $orderId !== '' ? ['Mã đơn hàng' => $orderId] : [],
        [['Thử lại', 'momo_pay.php?order_id=' . urlencode($orderId)], ['Về trang chủ', 'index.php']]);
}
