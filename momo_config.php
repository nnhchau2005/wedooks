<?php
/**
 * momo_config.php – Thông tin kết nối cổng MoMo (môi trường TEST / sandbox).
 * Chỉ sửa file này khi đổi tài khoản MoMo hoặc đưa web lên host thật.
 */

// ---- Địa chỉ API MoMo (test) ----
const MOMO_ENDPOINT_CREATE = 'https://test-payment.momo.vn/v2/gateway/api/create';
const MOMO_ENDPOINT_QUERY  = 'https://test-payment.momo.vn/v2/gateway/api/query';

// ---- Khoá tích hợp (lấy từ demo của giảng viên) ----
const MOMO_PARTNER_CODE = 'MOMO4MUD20240115_TEST';
const MOMO_ACCESS_KEY   = 'Ekj9og2VnRfOuIys';
const MOMO_SECRET_KEY   = 'PseUbm2s8QVJEbexsh8H3Jz2qa9tDqoa';
/* Bộ khoá test công khai khác của MoMo (dùng nếu bộ trên báo lỗi):
const MOMO_PARTNER_CODE = 'MOMOBKUN20180529';
const MOMO_ACCESS_KEY   = 'klm05TvNBzhg7h7j';
const MOMO_SECRET_KEY   = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
*/

// ---- Cách thanh toán ----
//  'captureWallet' : trang MoMo hiện mã QR → quét bằng app MoMo TEST trên điện thoại
//  'payWithATM'    : nhập thẻ ATM nội địa test (không cần điện thoại) – dễ demo trên lớp
const MOMO_REQUEST_TYPE = 'payWithATM';

// ---- Địa chỉ web của bạn ----
// Để '' = tự lấy theo trình duyệt đang mở (vd http://localhost/wedooks).
// Khi dùng ngrok để test IPN thì điền vd 'https://abcd-1234.ngrok-free.app/wedooks'.
const MOMO_PUBLIC_URL = '';

function momo_base_url(): string
{
    if (MOMO_PUBLIC_URL !== '') return rtrim(MOMO_PUBLIC_URL, '/');
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/wedooks/x.php')), '/');
    return "{$scheme}://{$host}{$dir}";
}

// MoMo chuyển trình duyệt khách về trang này sau khi thanh toán
function momo_redirect_url(): string { return momo_base_url() . '/momo_return.php'; }
// MoMo gọi ngầm (server → server) vào trang này để báo kết quả
function momo_ipn_url(): string      { return momo_base_url() . '/momo_ipn.php'; }
