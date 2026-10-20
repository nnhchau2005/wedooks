<?php
/**
 * momo_return.php – MoMo chuyển trình duyệt khách về đây sau khi thanh toán (redirectUrl).
 * URL có dạng: momo_return.php?partnerCode=...&orderId=...&amount=...&resultCode=0&transId=...&signature=...
 *
 * Bước 1: kiểm tra chữ ký (chống khách tự sửa resultCode=0 trên thanh địa chỉ)
 * Bước 2: cập nhật Orders.PaymentStatus = PAID / FAILED
 * Bước 3: hiện kết quả cho khách
 *
 * Chạy trên localhost thì MoMo không gọi được momo_ipn.php, nên trang này là nơi
 * cập nhật trạng thái chính. Khi có IPN, hai trang cập nhật trùng cũng không sao.
 */
require __DIR__ . '/momo.php';

try {
    $r     = momo_apply_result($_GET);
    $order = $r['order'];
    $rows  = [
        'Mã đơn hàng' => $order['OrderID'],
        'Số tiền'     => money($order['TotalAmount']),
        'Mã GD MoMo'  => (string)($_GET['transId'] ?? ''),
    ];

    if ($order['PaymentStatus'] === 'PAID') {
        momo_page('ok', 'Thanh toán thành công',
            'Cảm ơn bạn đã mua sắm tại wedooks. Đơn hàng đang được chuẩn bị.',
            $rows + ['Trạng thái' => 'Đã thanh toán'],
            [['Tiếp tục mua sắm', 'index.php']]);
    } elseif ($order['PaymentStatus'] === 'PENDING') {
        momo_page('wait', 'Đang chờ xác nhận',
            'MoMo đang xử lý giao dịch (' . $r['message'] . '). Vui lòng kiểm tra lại sau ít phút.',
            $rows,
            [['Về trang chủ', 'index.php']]);
    } else {
        momo_page('fail', 'Thanh toán chưa thành công',
            $r['message'] !== '' ? $r['message'] : 'Giao dịch bị huỷ hoặc thất bại.',
            $rows + ['Trạng thái' => 'Chưa thanh toán'],
            [['Thanh toán lại', 'momo_pay.php?order_id=' . urlencode($order['OrderID'])], ['Về trang chủ', 'index.php']]);
    }

} catch (Throwable $ex) {
    error_log('[momo_return] ' . $ex->getMessage());
    momo_page('fail', 'Không xác nhận được giao dịch', $ex->getMessage(), [], [['Về trang chủ', 'index.php']]);
}
