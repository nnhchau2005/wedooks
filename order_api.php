<?php
/**
 * order_api.php – Nhận đơn từ checkout.php và lưu vào SQL. BẮT BUỘC đã đăng nhập.
 *
 *   POST { name, phone, province, district, ward, street, note, payment }
 *   → { ok:true, order_id, subtotal, shipping, total, payment_method, payment_status }
 *   → { ok:false, error, fields?, login? }   (fields = ô nhập bị sai, login = cần đăng nhập)
 *
 * Email lấy từ tài khoản đang đăng nhập. Số tiền KHÔNG lấy từ trình duyệt –
 * server tự tính lại từ CartItems + Products.
 */
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Chỉ nhận POST.'], 405);
require_fetch();

$user = current_user();
if (!$user) json_out(['ok' => false, 'error' => 'Vui lòng đăng nhập để thanh toán.', 'login' => true], 401);

$in  = json_decode(file_get_contents('php://input'), true) ?: [];
$get = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

$d = [
    'name'     => $get('name', 100),
    'phone'    => preg_replace('/[\s.\-]/', '', $get('phone', 20)),
    'province' => $get('province', 100),
    'district' => $get('district', 100),
    'ward'     => $get('ward', 100),
    'street'   => $get('street', 200),
    'note'     => $get('note', 500),
    'payment'  => strtoupper($get('payment', 20)),
];

// ---- Kiểm tra dữ liệu ----
$rules = [
    'name'     => [mb_strlen($d['name']) >= 2,                       'Vui lòng nhập họ và tên.'],
    'phone'    => [preg_match('/^0\d{9}$/', $d['phone']) === 1,      'Số điện thoại phải gồm 10 số, bắt đầu bằng 0.'],
    'province' => [$d['province'] !== '',                            'Vui lòng chọn Tỉnh/Thành.'],
    'district' => [$d['district'] !== '',                            'Vui lòng chọn Quận/Huyện.'],
    'ward'     => [$d['ward'] !== '',                                'Vui lòng chọn Phường/Xã.'],
    'street'   => [$d['street'] !== '',                              'Vui lòng nhập số nhà, tên đường.'],
    'payment'  => [in_array($d['payment'], PAYMENT_METHODS, true),   'Phương thức thanh toán không hợp lệ.'],
];
$errors = array_map(fn($r) => $r[1], array_filter($rules, fn($r) => !$r[0]));
if ($errors) json_out(['ok' => false, 'error' => reset($errors), 'fields' => $errors], 422);

// ---- Lưu đơn ----
try {
    $order = create_order([
        'user_id' => $user['id'],
        'name'    => $d['name'],
        'phone'   => $d['phone'],
        'email'   => $user['email'],
        'address' => "{$d['street']}, {$d['ward']}, {$d['district']}, {$d['province']}",
        'note'    => $d['note'] !== '' ? $d['note'] : null,
    ], $d['payment']);

    json_out(['ok' => true] + $order);

} catch (InvalidArgumentException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 400);
} catch (Throwable $ex) {
    error_log('[order_api] ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Không lưu được đơn hàng. Vui lòng thử lại.'], 500);
}
