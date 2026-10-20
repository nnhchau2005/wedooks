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

try {
    $extra = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_fetch();
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int)($in['cart_id'] ?? 0);

        switch ($in['action'] ?? '') {
            case 'add':
                $extra['added'] = cart_add((int)($in['product_id'] ?? 0), (string)($in['size'] ?? 'M'), (int)($in['quantity'] ?? 1));
                break;
            case 'update': cart_change($id, (int)($in['delta'] ?? 0)); break;
            case 'remove': cart_remove($id); break;
            default: throw new InvalidArgumentException('Hành động không hợp lệ.');
        }
    }

    json_out(['ok' => true] + $extra + cart_summary());

} catch (InvalidArgumentException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 400);
} catch (Throwable $ex) {
    error_log('[cart_api] ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Lỗi kết nối cơ sở dữ liệu.'], 500);
}
