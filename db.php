<?php
/**
 * db.php – Kết nối CSDL + các hàm Sản phẩm / Giỏ hàng / Đơn hàng / Tài khoản.
 * MỌI câu SQL nằm ở file này. Mọi trang PHP đều require nó.
 *
 * Giỏ hàng lưu trong bảng CartItems, nhận diện khách bằng cookie "wedooks_cart"
 * (chuỗi ngẫu nhiên 32 ký tự, sống 30 ngày) → tắt trình duyệt mở lại vẫn còn giỏ.
 * Giỏ chỉ lưu ProductID + Size + Quantity; GIÁ LUÔN LẤY TỪ BẢNG Products.
 */

// ===================== 1. KẾT NỐI =====================
// Nếu dự án đã có config/database.php (định nghĩa hàm db()) thì dùng lại luôn.
if (is_file(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            // --- SQL Server (mặc định) ---
            $dsn  = 'sqlsrv:Server=LAPTOP-NNHC\SQLEXPRESS;Database=WedooksDB;TrustServerCertificate=1';
            // --- MySQL (XAMPP) – nếu dùng thì bỏ // ở dòng dưới ---
            // $dsn = 'mysql:host=localhost;dbname=wedooks;charset=utf8mb4';
            $user = '';   // Windows Authentication: để trống | SQL Auth: 'sa' | MySQL: 'root'
            $pass = '';

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return $pdo;
    }
}

// ===================== 2. TIỆN ÍCH =====================
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($n): string
{
    return number_format((float)$n, 0, ',', '.') . ' đ';
}

/** Trả JSON rồi dừng (dùng cho cart_api.php, order_api.php). */
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Chỉ nhận request gửi từ js/common.js (header tự đặt → chống CSRF đơn giản). */
function require_fetch(): void
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
        json_out(['ok' => false, 'error' => 'Yêu cầu không hợp lệ.'], 403);
    }
}

/** Ghi log lỗi và trả về câu thông báo thân thiện để in ra trang. */
function page_error(Throwable $ex, string $tag, string $what = 'dữ liệu'): string
{
    error_log("[{$tag}] " . $ex->getMessage());
    return $ex instanceof ProductColumnsException
        ? $ex->getMessage()
        : "Không tải được {$what} từ cơ sở dữ liệu. Kiểm tra lại kết nối SQL.";
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Phí ship: 30.000đ, miễn phí cho đơn từ 1.000.000đ (theo thanh thông báo ở trang chủ)
const SHIPPING_FEE   = 30000;
const FREE_SHIP_FROM = 1000000;

function shipping_fee(int $subtotal): int
{
    return ($subtotal <= 0 || $subtotal >= FREE_SHIP_FROM) ? 0 : SHIPPING_FEE;
}

// ===================== 3. MÃ GIỎ HÀNG (cookie) =====================
const CART_COOKIE = 'wedooks_cart';
const CART_SIZES  = ['S', 'M', 'L', 'XL'];
const CART_MAX_QTY = 99;

function cart_token(): string
{
    static $token = null;
    if ($token !== null) return $token;

    $t = $_COOKIE[CART_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
        $t = bin2hex(random_bytes(16));
        setcookie(CART_COOKIE, $t, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $token = $t;
}

// Gọi ngay khi require để cookie được gửi TRƯỚC khi in HTML.
cart_token();
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}

// ===================== 4. SẢN PHẨM =====================
/*
 * Tên cột THẬT trong bảng Products của bạn.
 * Để null = code tự dò theo danh sách PRODUCT_COLUMN_CANDIDATES bên dưới.
 * Nếu tự dò không ra, trang chủ sẽ báo lỗi kèm danh sách cột hiện có → điền tay vào đây.
 *   VD bảng của bạn dùng ProductName, UnitPrice:  'name' => 'ProductName', 'price' => 'UnitPrice'
 */
const PRODUCT_COLUMNS = [
    'id'    => null,   // mã sản phẩm (khoá chính)
    'name'  => null,   // tên sản phẩm
    'price' => null,   // giá bán
    'image' => null,   // link ảnh (không bắt buộc)
];

const PRODUCT_COLUMN_CANDIDATES = [
    'id'    => ['ProductID', 'ProductId', 'Id', 'MaSP', 'MaSanPham'],
    'name'  => ['Name', 'ProductName', 'Title', 'TenSP', 'TenSanPham', 'Ten'],
    'price' => ['Price', 'UnitPrice', 'SalePrice', 'Gia', 'GiaBan', 'DonGia'],
    'image' => ['ImageUrl', 'Image', 'ImagePath', 'Img', 'Thumbnail', 'HinhAnh', 'AnhSP'],
];

class ProductColumnsException extends RuntimeException {}

/** Trả về tên cột thật: ['id' => 'ProductID', 'name' => 'ProductName', ...] */
function product_cols(): array
{
    static $cols = null;
    if ($cols !== null) return $cols;

    // Đọc danh sách cột của bảng Products (không lấy dòng nào)
    $st = db()->query('SELECT * FROM Products WHERE 1 = 0');
    $actual = [];
    for ($i = 0; $i < $st->columnCount(); $i++) {
        $meta = $st->getColumnMeta($i);
        $actual[strtolower($meta['name'])] = $meta['name'];
    }

    $cols = [];
    foreach (PRODUCT_COLUMN_CANDIDATES as $key => $candidates) {
        $wanted = PRODUCT_COLUMNS[$key] ? [PRODUCT_COLUMNS[$key]] : $candidates;
        $cols[$key] = null;
        foreach ($wanted as $c) {
            if (isset($actual[strtolower($c)])) { $cols[$key] = $actual[strtolower($c)]; break; }
        }
        if ($cols[$key] !== null && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $cols[$key])) {
            throw new ProductColumnsException("Tên cột \"{$cols[$key]}\" có ký tự đặc biệt, hãy đổi tên cột trong SQL.");
        }
    }

    $labels  = ['id' => 'mã sản phẩm', 'name' => 'tên sản phẩm', 'price' => 'giá'];
    $missing = array_keys(array_filter(array_intersect_key($cols, $labels), fn($v) => $v === null));
    if ($missing) {
        throw new ProductColumnsException(
            'Bảng Products không tìm thấy cột ' . implode(', ', array_map(fn($k) => $labels[$k], $missing))
            . '. Các cột hiện có: ' . implode(', ', $actual)
            . '. → Mở db.php, điền tên cột đúng vào PRODUCT_COLUMNS.'
        );
    }
    return $cols;
}

/** Phần SELECT sản phẩm, đổi tên cột thật về tên chuẩn ProductID, Name, Price, ImageUrl. */
function product_select(string $alias = ''): string
{
    $c = product_cols();
    $a = $alias !== '' ? $alias . '.' : '';
    return "{$a}{$c['id']} AS ProductID, {$a}{$c['name']} AS Name, {$a}{$c['price']} AS Price, "
         . ($c['image'] ? "{$a}{$c['image']} AS ImageUrl" : 'NULL AS ImageUrl');
}

function map_product(array $r): array
{
    return [
        'id'    => (int)$r['ProductID'],
        'name'  => (string)$r['Name'],
        'price' => (int)round((float)$r['Price']),
        'image' => (string)($r['ImageUrl'] ?? ''),
    ];
}

function get_products(): array
{
    $id = product_cols()['id'];
    $rows = db()->query('SELECT ' . product_select() . " FROM Products ORDER BY {$id}")
                ->fetchAll(PDO::FETCH_ASSOC);
    return array_map('map_product', $rows);
}

function get_product(int $id): ?array
{
    $idCol = product_cols()['id'];
    $st = db()->prepare('SELECT ' . product_select() . " FROM Products WHERE {$idCol} = :id");
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? map_product($row) : null;
}

// ===================== 5. GIỎ HÀNG =====================

/** Thêm sản phẩm vào giỏ. Trả về tên sản phẩm (để hiện thông báo). */
function cart_add(int $productId, string $size = 'M', int $qty = 1): string
{
    $size = strtoupper(trim($size));
    if (!in_array($size, CART_SIZES, true)) $size = 'M';
    $qty = max(1, min($qty, CART_MAX_QTY));

    $product = get_product($productId);
    if (!$product) {
        throw new InvalidArgumentException('Sản phẩm không tồn tại.');
    }

    $pdo = db();
    $st = $pdo->prepare('SELECT CartItemID, Quantity FROM CartItems
                         WHERE CartToken = :t AND ProductID = :p AND Size = :s');
    $st->execute([':t' => cart_token(), ':p' => $productId, ':s' => $size]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $newQty = min((int)$row['Quantity'] + $qty, CART_MAX_QTY);
        $pdo->prepare('UPDATE CartItems SET Quantity = :q, UpdatedAt = CURRENT_TIMESTAMP
                       WHERE CartItemID = :id')
            ->execute([':q' => $newQty, ':id' => (int)$row['CartItemID']]);
    } else {
        $pdo->prepare('INSERT INTO CartItems (CartToken, ProductID, Size, Quantity)
                       VALUES (:t, :p, :s, :q)')
            ->execute([':t' => cart_token(), ':p' => $productId, ':s' => $size, ':q' => $qty]);
    }
    return $product['name'];
}

/** Tăng/giảm số lượng 1 dòng giỏ (tối đa CART_MAX_QTY). Về 0 thì xoá dòng. */
function cart_change(int $cartItemId, int $delta): void
{
    $st = db()->prepare('UPDATE CartItems SET Quantity = Quantity + :d, UpdatedAt = CURRENT_TIMESTAMP
                         WHERE CartItemID = :id AND CartToken = :t
                           AND Quantity + :d2 BETWEEN 1 AND ' . CART_MAX_QTY);
    $st->execute([':d' => $delta, ':d2' => $delta, ':id' => $cartItemId, ':t' => cart_token()]);
    if ($st->rowCount() === 0 && $delta < 0) cart_remove($cartItemId);
}

function cart_remove(int $cartItemId): void
{
    db()->prepare('DELETE FROM CartItems WHERE CartItemID = :id AND CartToken = :t')
        ->execute([':id' => $cartItemId, ':t' => cart_token()]);
}

function cart_clear(): void
{
    db()->prepare('DELETE FROM CartItems WHERE CartToken = :t')->execute([':t' => cart_token()]);
}

/** Tổng số lượng (hiện trên icon giỏ). */
function cart_count(): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(Quantity), 0) AS n FROM CartItems WHERE CartToken = :t');
    $st->execute([':t' => cart_token()]);
    return (int)$st->fetchColumn();
}

/** Toàn bộ giỏ: danh sách + tổng số lượng + tạm tính (giá lấy từ Products). */
function cart_summary(): array
{
    $idCol = product_cols()['id'];
    $st = db()->prepare('SELECT c.CartItemID, c.Size, c.Quantity, ' . product_select('p') . "
                         FROM CartItems c
                         JOIN Products p ON p.{$idCol} = c.ProductID
                         WHERE c.CartToken = :t
                         ORDER BY c.CartItemID");
    $st->execute([':t' => cart_token()]);

    $items = [];
    $count = 0;
    $subtotal = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $item = map_product($r) + [
            'cart_id'  => (int)$r['CartItemID'],
            'size'     => (string)$r['Size'],
            'quantity' => (int)$r['Quantity'],
        ];
        $item['line_total'] = $item['price'] * $item['quantity'];
        $count    += $item['quantity'];
        $subtotal += $item['line_total'];
        $items[] = $item;
    }
    $shipping = shipping_fee($subtotal);
    return compact('items', 'count', 'subtotal', 'shipping') + ['total' => $subtotal + $shipping];
}

// ===================== 6. ĐƠN HÀNG =====================
const PAYMENT_METHODS = ['COD', 'VNPAY', 'MOMO'];

/** Sinh mã đơn: WDK + yymmdd + 6 số ngẫu nhiên (VD WDK260930482913), không trùng. */
function new_order_id(): string
{
    $st = db()->prepare('SELECT COUNT(*) FROM Orders WHERE OrderID = :id');
    do {
        $id = 'WDK' . date('ymd') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $st->execute([':id' => $id]);
    } while ((int)$st->fetchColumn() > 0);
    return $id;
}

/**
 * Tạo đơn từ giỏ hiện tại (trong 1 transaction):
 *   INSERT Orders → INSERT OrderItems → DELETE CartItems.
 * Lỗi giữa chừng thì rollBack, không có đơn "nửa vời", giỏ vẫn còn.
 * $c: user_id, name, phone, email, address, note  (đã kiểm tra ở order_api.php)
 */
function create_order(array $c, string $method): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cart = cart_summary();   // giá lấy lại từ Products, không tin số tiền trình duyệt gửi lên
        if (!$cart['items']) {
            throw new InvalidArgumentException('Giỏ hàng đang trống.');
        }

        $orderId = new_order_id();

        $pdo->prepare('INSERT INTO Orders
                (OrderID, UserID, CustomerName, Phone, Email, Address, Note,
                 Subtotal, ShippingFee, TotalAmount, PaymentMethod, PaymentStatus)
             VALUES (:id, :uid, :name, :phone, :email, :address, :note,
                     :subtotal, :shipping, :total, :method, :status)')
            ->execute([
                ':id'       => $orderId,
                ':uid'      => $c['user_id'],
                ':name'     => $c['name'],
                ':phone'    => $c['phone'],
                ':email'    => $c['email'],
                ':address'  => $c['address'],
                ':note'     => $c['note'],
                ':subtotal' => $cart['subtotal'],
                ':shipping' => $cart['shipping'],
                ':total'    => $cart['total'],
                ':method'   => $method,
                ':status'   => 'PENDING',
            ]);

        $insItem = $pdo->prepare('INSERT INTO OrderItems
                (OrderID, ProductID, ProductName, Size, UnitPrice, Quantity, LineTotal)
             VALUES (:oid, :pid, :pname, :size, :price, :qty, :line)');
        foreach ($cart['items'] as $it) {
            $insItem->execute([
                ':oid'   => $orderId,
                ':pid'   => $it['id'],
                ':pname' => $it['name'],
                ':size'  => $it['size'],
                ':price' => $it['price'],
                ':qty'   => $it['quantity'],
                ':line'  => $it['line_total'],
            ]);
        }

        cart_clear();
        $pdo->commit();

        return [
            'order_id'       => $orderId,
            'subtotal'       => $cart['subtotal'],
            'shipping'       => $cart['shipping'],
            'total'          => $cart['total'],
            'payment_method' => $method,
            'payment_status' => 'PENDING',
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

// ===================== 7. THANH TOÁN ONLINE (MoMo) =====================
// Cần chạy momo_schema.sql trước (thêm cột MomoTransId, PaidAt, PaymentNote vào Orders).

/** Lấy 1 đơn hàng theo mã (null nếu không có). */
function get_order(string $orderId): ?array
{
    $st = db()->prepare('SELECT * FROM Orders WHERE OrderID = :id');
    $st->execute([':id' => $orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Đánh dấu đơn ĐÃ THANH TOÁN. Chỉ cập nhật khi đơn chưa PAID
 * → momo_return.php và momo_ipn.php cùng báo về cũng không ghi 2 lần.
 */
function mark_order_paid(string $orderId, string $transId, string $note = ''): bool
{
    $st = db()->prepare("UPDATE Orders
                         SET PaymentStatus = 'PAID', MomoTransId = :t, PaidAt = CURRENT_TIMESTAMP, PaymentNote = :n
                         WHERE OrderID = :id AND PaymentStatus <> 'PAID'");
    $st->execute([':t' => $transId, ':n' => mb_substr($note, 0, 255), ':id' => $orderId]);
    return $st->rowCount() > 0;
}

/** Đánh dấu thanh toán THẤT BẠI / khách huỷ. Không bao giờ hạ đơn đã PAID xuống. */
function mark_order_failed(string $orderId, string $note = ''): bool
{
    $st = db()->prepare("UPDATE Orders
                         SET PaymentStatus = 'FAILED', PaymentNote = :n
                         WHERE OrderID = :id AND PaymentStatus <> 'PAID'");
    $st->execute([':n' => mb_substr($note, 0, 255), ':id' => $orderId]);
    return $st->rowCount() > 0;
}

// ===================== 8. TÀI KHOẢN (đăng nhập bằng email) =====================
// Cần chạy user_schema.sql trước (tạo bảng Users + thêm cột UserID vào Orders).
// Đăng nhập lưu trong $_SESSION['user'] = ['id', 'email', 'name'].

/** Người đang đăng nhập (null nếu chưa). */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Trang bắt buộc đăng nhập: chưa đăng nhập thì chuyển sang login.php rồi quay lại $back. */
function require_login(string $back): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php?next=' . urlencode($back));
        exit;
    }
    return $u;
}

/** Chỉ cho quay về trang nội bộ dạng "xxx.php?a=b" (chống chuyển hướng sang web lạ). */
function safe_next(?string $next): string
{
    return preg_match('/^[a-z_]+\.php(\?[\w=&%.-]*)?$/', (string)$next) ? $next : 'index.php';
}

function login_session(array $row): void
{
    session_regenerate_id(true);   // chống chiếm phiên (session fixation)
    $_SESSION['user'] = [
        'id'    => (int)$row['UserID'],
        'email' => (string)$row['Email'],
        'name'  => (string)$row['FullName'],
    ];
}

/** Đăng ký tài khoản mới rồi đăng nhập luôn. */
function user_register(string $email, string $password, string $name): void
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email không hợp lệ.');
    if (mb_strlen($name) < 2)                       throw new InvalidArgumentException('Vui lòng nhập họ và tên.');
    if (strlen($password) < 6)                      throw new InvalidArgumentException('Mật khẩu phải có ít nhất 6 ký tự.');

    $st = db()->prepare('SELECT COUNT(*) FROM Users WHERE Email = :e');
    $st->execute([':e' => $email]);
    if ((int)$st->fetchColumn() > 0) throw new InvalidArgumentException('Email này đã được đăng ký.');

    db()->prepare('INSERT INTO Users (Email, PasswordHash, FullName) VALUES (:e, :h, :n)')
        ->execute([':e' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT), ':n' => $name]);
    user_login($email, $password);
}

/** Đăng nhập bằng email + mật khẩu. */
function user_login(string $email, string $password): void
{
    $st = db()->prepare('SELECT UserID, Email, FullName, PasswordHash FROM Users WHERE Email = :e');
    $st->execute([':e' => strtolower(trim($email))]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($password, $row['PasswordHash'])) {
        throw new InvalidArgumentException('Email hoặc mật khẩu không đúng.');
    }
    login_session($row);
}

function user_logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

/** Mã chống CSRF cho form POST thường (login.php). */
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
