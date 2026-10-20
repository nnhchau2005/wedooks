<?php
/**
 * cart_api.php – Cầu nối giữa JavaScript (index.php, cart.php) và SQL.
 *
 *   GET  cart_api.php                                   → trả về giỏ hiện tại
 *   POST {"action":"add","product_id":1,"size":"M"}     → thêm vào giỏ
 *   POST {"action":"update","cart_id":5,"delta":-1}     → tăng/giảm số lượng
 *   POST {"action":"remove","cart_id":5}                → xoá 1 dòng
 *
 * Luôn trả JSON: { ok, items, count, subtotal, added? , error? }
 */
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $extra = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Header tự đặt → trang web lạ không gửi request giả được (chống CSRF đơn giản)
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
            json_out(['ok' => false, 'error' => 'Yêu cầu không hợp lệ.'], 403);
        }

        $in = json_decode(file_get_contents('php://input'), true) ?: [];

        switch ($in['action'] ?? '') {
            case 'add':
                $extra['added'] = cart_add(
                    (int)($in['product_id'] ?? 0),
                    (string)($in['size'] ?? 'M'),
                    (int)($in['quantity'] ?? 1)
                );
                break;
            case 'update':
                cart_change((int)($in['cart_id'] ?? 0), (int)($in['delta'] ?? 0));
                break;
            case 'remove':
                cart_remove((int)($in['cart_id'] ?? 0));
                break;
            default:
                throw new InvalidArgumentException('Hành động không hợp lệ.');
        }
    }

    json_out(['ok' => true] + $extra + cart_summary());

} catch (InvalidArgumentException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 400);
} catch (Throwable $ex) {
    error_log('[cart_api] ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Lỗi kết nối cơ sở dữ liệu.'], 500);
}
