<?php
/**
 * momo_ipn.php – MoMo gọi NGẦM vào đây (server → server, POST JSON) để báo kết quả (ipnUrl).
 * Đây là kênh đáng tin nhất vì không đi qua trình duyệt của khách
 * (khách tắt tab trước khi quay về web thì đơn vẫn được cập nhật).
 *
 * Lưu ý: MoMo nằm ngoài Internet nên KHÔNG gọi được http://localhost.
 * Muốn thử IPN trên máy mình: chạy ngrok, rồi điền MOMO_PUBLIC_URL trong momo_config.php.
 */
require __DIR__ . '/momo.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $r = momo_apply_result($data);
    error_log("[momo_ipn] {$r['order']['OrderID']} → {$r['order']['PaymentStatus']} (resultCode {$r['resultCode']})");
    http_response_code(204);   // báo MoMo: đã nhận, không cần gửi lại
} catch (Throwable $ex) {
    error_log('[momo_ipn] ' . $ex->getMessage());
    http_response_code(400);
}
