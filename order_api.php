<?php
/**
 * order_api.php – Nhận đơn từ checkout.php và lưu vào SQL.
 *
 *   POST { name, phone, email, province, district, ward, street, note, payment }
 *   → { ok:true, order_id, subtotal, shipping, total, payment_method, payment_status }
 *   → { ok:false, error, fields? }   (fields = danh sách ô nhập bị sai)
 *
 * Số tiền KHÔNG lấy từ trình duyệt – server tự tính lại từ CartItems + Products.
 */
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Chỉ nhận POST.'], 405);
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    json_out(['ok' => false, 'error' => 'Yêu cầu không hợp lệ.'], 403);
}

$in  = json_decode(file_get_contents('php://input'), true) ?: [];
$get = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

$d = [
    'name'     => $get('name', 100),
    'phone'    => preg_replace('/[\s.\-]/', '', $get('phone', 20)),
    'email'    => $get('email', 150),
    'province' => $get('province', 100),
    'district' => $get('district', 100),
    'ward'     => $get('ward', 100),
    'street'   => $get('street', 200),
    'note'     => $get('note', 500),
    'payment'  => strtoupper($get('payment', 20)),
];

// ---- Kiểm tra dữ liệu ----
$errors = [];
if (mb_strlen($d['name']) < 2)                                   $errors['name']     = 'Vui lòng nhập họ và tên.';
if (!preg_match('/^0\d{9}$/', $d['phone']))                       $errors['phone']    = 'Số điện thoại phải gồm 10 số, bắt đầu bằng 0.';
if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email không hợp lệ.';
if ($d['province'] === '')                                        $errors['province'] = 'Vui lòng chọn Tỉnh/Thành.';
if ($d['district'] === '')                                        $errors['district'] = 'Vui lòng chọn Quận/Huyện.';
if ($d['ward'] === '')                                            $errors['ward']     = 'Vui lòng chọn Phường/Xã.';
if ($d['street'] === '')                                          $errors['street']   = 'Vui lòng nhập số nhà, tên đường.';
if (!in_array($d['payment'], PAYMENT_METHODS, true))              $errors['payment']  = 'Phương thức thanh toán không hợp lệ.';

if ($errors) {
    json_out(['ok' => false, 'error' => reset($errors), 'fields' => $errors], 422);
}

// ---- Lưu đơn ----
try {
    $order = create_order([
        'name'    => $d['name'],
        'phone'   => $d['phone'],
        'email'   => $d['email'],
        'address' => "{$d['street']}, {$d['ward']}, {$d['district']}, {$d['province']}",
        'note'    => $d['note'],
    ], $d['payment']);

    json_out(['ok' => true] + $order);

} catch (InvalidArgumentException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 400);
} catch (Throwable $ex) {
    error_log('[order_api] ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Không lưu được đơn hàng. Vui lòng thử lại.'], 500);
}
