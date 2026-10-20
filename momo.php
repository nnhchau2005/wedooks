<?php
/**
 * momo.php – Các hàm làm việc với cổng MoMo.
 *   momo_create_payment() : gửi yêu cầu tạo giao dịch, nhận payUrl
 *   momo_signature_ok()   : kiểm tra chữ ký MoMo gửi về (return + IPN)
 *   momo_apply_result()   : cập nhật trạng thái đơn trong SQL theo kết quả MoMo
 *   momo_query()          : hỏi lại MoMo trạng thái 1 giao dịch (tuỳ chọn)
 *   momo_page()           : khung HTML chung cho các trang momo_*.php
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/momo_config.php';

// ---------- 1. Gửi JSON tới MoMo (giống execPostRequest của giảng viên, có bắt lỗi) ----------
function momo_post(string $url, array $data): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP chưa bật cURL. Mở php.ini, bỏ dấu ; trước dòng extension=curl rồi khởi động lại Apache.');
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($json)],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,   // MoMo khuyên chờ tối thiểu 30 giây
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Không kết nối được MoMo: ' . $err);
    }
    $res = json_decode($body, true);
    if (!is_array($res)) {
        throw new RuntimeException('MoMo trả về dữ liệu không đọc được: ' . mb_substr((string)$body, 0, 200));
    }
    return $res;
}

function momo_sign(string $raw): string
{
    return hash_hmac('sha256', $raw, MOMO_SECRET_KEY);
}

// ---------- 2. Mã giao dịch gửi MoMo ----------
// MoMo không nhận trùng orderId (lỗi 41). Mỗi lần bấm thanh toán (kể cả thanh toán lại)
// dùng mã mới: <mã đơn wedooks>_<thời gian>. Khi MoMo trả về thì cắt lại phần trước dấu _.
function momo_order_id(string $orderId): string { return $orderId . '_' . time(); }
function momo_split_order_id(string $momoOrderId): string { return explode('_', $momoOrderId)[0]; }

// ---------- 3. Tạo giao dịch → trả về mảng có payUrl ----------
function momo_create_payment(string $orderId, int $amount, string $orderInfo): array
{
    $p = [
        'accessKey'   => MOMO_ACCESS_KEY,
        'amount'      => (string)$amount,
        'extraData'   => '',
        'ipnUrl'      => momo_ipn_url(),
        'orderId'     => momo_order_id($orderId),
        'orderInfo'   => $orderInfo,
        'partnerCode' => MOMO_PARTNER_CODE,
        'redirectUrl' => momo_redirect_url(),
        'requestId'   => $orderId . '_' . bin2hex(random_bytes(4)),
        'requestType' => MOMO_REQUEST_TYPE,
    ];

    // Chuỗi ký: các trường xếp theo thứ tự a→z (đúng như code giảng viên)
    $raw = "accessKey={$p['accessKey']}&amount={$p['amount']}&extraData={$p['extraData']}"
         . "&ipnUrl={$p['ipnUrl']}&orderId={$p['orderId']}&orderInfo={$p['orderInfo']}"
         . "&partnerCode={$p['partnerCode']}&redirectUrl={$p['redirectUrl']}"
         . "&requestId={$p['requestId']}&requestType={$p['requestType']}";

    $res = momo_post(MOMO_ENDPOINT_CREATE, [
        'partnerCode' => $p['partnerCode'],
        'partnerName' => 'wedooks.',
        'storeId'     => 'WedooksStore',
        'requestId'   => $p['requestId'],
        'amount'      => $amount,
        'orderId'     => $p['orderId'],
        'orderInfo'   => $p['orderInfo'],
        'redirectUrl' => $p['redirectUrl'],
        'ipnUrl'      => $p['ipnUrl'],
        'lang'        => 'vi',
        'extraData'   => $p['extraData'],
        'requestType' => $p['requestType'],
        'signature'   => momo_sign($raw),
    ]);

    // Code giảng viên bỏ qua bước này: nếu MoMo báo lỗi thì không có payUrl
    if ((int)($res['resultCode'] ?? -1) !== 0 || empty($res['payUrl'])) {
        throw new RuntimeException('MoMo từ chối tạo giao dịch (mã ' . ($res['resultCode'] ?? '?') . '): '
            . ($res['message'] ?? 'không rõ lý do'));
    }
    return $res;
}

// ---------- 4. Kiểm tra chữ ký kết quả MoMo gửi về ----------
// Dùng chung cho momo_return.php (tham số trên URL) và momo_ipn.php (JSON).
function momo_signature_ok(array $d): bool
{
    $f = fn($k) => (string)($d[$k] ?? '');
    $raw = 'accessKey=' . MOMO_ACCESS_KEY
         . '&amount=' . $f('amount') . '&extraData=' . $f('extraData')
         . '&message=' . $f('message') . '&orderId=' . $f('orderId')
         . '&orderInfo=' . $f('orderInfo') . '&orderType=' . $f('orderType')
         . '&partnerCode=' . $f('partnerCode') . '&payType=' . $f('payType')
         . '&requestId=' . $f('requestId') . '&responseTime=' . $f('responseTime')
         . '&resultCode=' . $f('resultCode') . '&transId=' . $f('transId');
    return hash_equals(momo_sign($raw), $f('signature'));
}

// ---------- 5. Ghi kết quả vào SQL ----------
// Trả về ['order' => dòng Orders sau cập nhật, 'resultCode' => int, 'message' => string]
function momo_apply_result(array $d): array
{
    if (!momo_signature_ok($d)) {
        throw new RuntimeException('Chữ ký MoMo không hợp lệ – dữ liệu có thể đã bị sửa.');
    }
    if (($d['partnerCode'] ?? '') !== MOMO_PARTNER_CODE) {
        throw new RuntimeException('Sai partnerCode.');
    }

    $orderId = momo_split_order_id((string)$d['orderId']);
    $order   = get_order($orderId);
    if (!$order) {
        throw new RuntimeException("Không tìm thấy đơn {$orderId}.");
    }
    // Số tiền MoMo báo phải đúng bằng số tiền đơn trong SQL
    if ((int)$d['amount'] !== (int)round((float)$order['TotalAmount'])) {
        throw new RuntimeException('Số tiền MoMo báo về không khớp với đơn hàng.');
    }

    $code = (int)$d['resultCode'];
    $msg  = (string)($d['message'] ?? '');

    if ($code === 0) {
        mark_order_paid($orderId, (string)$d['transId'], 'MoMo: ' . $msg);
    } elseif (!in_array($code, [1000, 7000, 7002, 9000], true)) {
        // 1000/7000/7002/9000 = đang xử lý → giữ PENDING. Còn lại = thất bại / khách huỷ.
        mark_order_failed($orderId, "MoMo {$code}: {$msg}");
    }

    return ['order' => get_order($orderId), 'resultCode' => $code, 'message' => $msg];
}

// ---------- 6. (Tuỳ chọn) Hỏi lại MoMo trạng thái 1 giao dịch ----------
function momo_query(string $momoOrderId): array
{
    $requestId = 'Q' . time() . bin2hex(random_bytes(3));
    $raw = 'accessKey=' . MOMO_ACCESS_KEY . "&orderId={$momoOrderId}"
         . '&partnerCode=' . MOMO_PARTNER_CODE . "&requestId={$requestId}";
    return momo_post(MOMO_ENDPOINT_QUERY, [
        'partnerCode' => MOMO_PARTNER_CODE,
        'requestId'   => $requestId,
        'orderId'     => $momoOrderId,
        'lang'        => 'vi',
        'signature'   => momo_sign($raw),
    ]);
}

// ---------- 7. Khung trang HTML (giao diện nằm trong style.css) ----------
function momo_page(string $state, string $title, string $text, array $rows = [], array $buttons = []): void
{
    $path = [
        'ok'   => 'M5 13l4 4L19 7',
        'fail' => 'M6 18L18 6M6 6l12 12',
        'wait' => 'M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z',
    ][$state] ?? '';
    ?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - wedooks.</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="site-header site-header--plain">
    <div class="container header-bar header-bar--center">
      <a href="index.php" class="logo">wedooks.</a>
    </div>
  </header>
  <main class="result">
    <div class="circle-icon circle-icon--<?= e($state) ?>">
      <svg class="icon icon--lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/></svg>
    </div>
    <h1 class="result__title"><?= e($title) ?></h1>
    <p class="result__text"><?= e($text) ?></p>
    <?php if ($rows): ?>
    <div class="result__box">
      <?php foreach ($rows as $label => $value): ?>
      <div class="result__row"><span class="result__label"><?= e($label) ?></span><strong class="result__value"><?= e($value) ?></strong></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="result__actions">
      <?php foreach ($buttons as $i => [$label, $href]): ?>
      <a href="<?= e($href) ?>" class="btn btn--small <?= $i === 0 ? 'btn--dark' : 'btn--outline' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
<?php
}
