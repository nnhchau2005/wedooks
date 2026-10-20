/* =========================================================
   cart_schema.sql – SQL Server (chạy trong SSMS, bấm F5)
   - Tạo bảng Products (nếu CHƯA có) + 8 sản phẩm mẫu
   - Tạo bảng CartItems (giỏ hàng)
   Chạy lại nhiều lần không sao: bảng đã có thì bỏ qua.
   ========================================================= */
IF DB_ID(N'WedooksDB') IS NULL CREATE DATABASE WedooksDB;
GO
USE WedooksDB;
GO

IF OBJECT_ID(N'dbo.Products', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Products (
        ProductID INT IDENTITY(1,1) PRIMARY KEY,
        Name      NVARCHAR(200) NOT NULL,
        Price     DECIMAL(18,0) NOT NULL,
        ImageUrl  NVARCHAR(500) NULL
    );

    INSERT INTO dbo.Products (Name, Price, ImageUrl) VALUES
    (N'ÁO POLO DỆT KIM WAFFLE',        650000, N'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=600&auto=format&fit=crop'),
    (N'SƠ MI OXFORD CLASSIC',          580000, N'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?q=80&w=600&auto=format&fit=crop'),
    (N'QUẦN TÂY MINIMAL TAILORED',     790000, N'https://images.unsplash.com/photo-1479064555552-3ef4979f8908?q=80&w=600&auto=format&fit=crop'),
    (N'ÁO THUN HEAVYWEIGHT ORGANIC',   350000, N'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?q=80&w=600&auto=format&fit=crop'),
    (N'QUẦN SHORT KAKI RELAXED',       450000, N'https://images.unsplash.com/photo-1591195853828-11db59a44f6b?q=80&w=600&auto=format&fit=crop'),
    (N'ÁO KHOÁC BLAZER UNSTRUCTURED', 1250000, N'https://images.unsplash.com/photo-1507679799987-c73779587ccf?q=80&w=600&auto=format&fit=crop'),
    (N'ÁO LEN CỔ TRÒN MERINO',         890000, N'https://images.unsplash.com/photo-1614676471928-2ed0ad1061a4?q=80&w=600&auto=format&fit=crop'),
    (N'QUẦN CHINO DÁNG SUÔNG',         650000, N'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?q=80&w=600&auto=format&fit=crop');
END
GO

IF OBJECT_ID(N'dbo.CartItems', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.CartItems (
        CartItemID INT IDENTITY(1,1) PRIMARY KEY,
        CartToken  CHAR(32)     NOT NULL,              -- mã giỏ lấy từ cookie
        ProductID  INT          NOT NULL,              -- mã SP trong bảng Products (không đặt khoá ngoại để chạy được với mọi tên cột)
        Size       NVARCHAR(5)  NOT NULL DEFAULT N'M',
        Quantity   INT          NOT NULL CHECK (Quantity > 0),
        CreatedAt  DATETIME2    NOT NULL DEFAULT SYSDATETIME(),
        UpdatedAt  DATETIME2    NOT NULL DEFAULT SYSDATETIME(),
        CONSTRAINT UQ_CartItems UNIQUE (CartToken, ProductID, Size)
    );
    CREATE INDEX IX_CartItems_Token ON dbo.CartItems(CartToken);
END
GO

SELECT * FROM dbo.Products;
SELECT * FROM dbo.CartItems;


/* =========================================================
   NẾU DÙNG MySQL (XAMPP / phpMyAdmin) thì chạy khối dưới thay cho phần trên
   =========================================================
CREATE DATABASE IF NOT EXISTS wedooks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wedooks;

CREATE TABLE IF NOT EXISTS Products (
    ProductID INT AUTO_INCREMENT PRIMARY KEY,
    Name      VARCHAR(200) NOT NULL,
    Price     DECIMAL(18,0) NOT NULL,
    ImageUrl  VARCHAR(500) NULL
);
-- (INSERT 8 sản phẩm như trên, bỏ chữ N trước dấu nháy)

CREATE TABLE IF NOT EXISTS CartItems (
    CartItemID INT AUTO_INCREMENT PRIMARY KEY,
    CartToken  CHAR(32) NOT NULL,
    ProductID  INT NOT NULL,
    Size       VARCHAR(5) NOT NULL DEFAULT 'M',
    Quantity   INT NOT NULL CHECK (Quantity > 0),
    CreatedAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY UQ_CartItems (CartToken, ProductID, Size),
    KEY IX_CartItems_Token (CartToken)
);
*/

/*CẤP QUYỀN TRUY CẬP KO CẦN USE PASS */
USE master;
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'NT AUTHORITY\SYSTEM')
    CREATE LOGIN [NT AUTHORITY\SYSTEM] FROM WINDOWS;
GO
USE WedooksDB;
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'NT AUTHORITY\SYSTEM')
    CREATE USER [NT AUTHORITY\SYSTEM] FOR LOGIN [NT AUTHORITY\SYSTEM];
ALTER ROLE db_datareader ADD MEMBER [NT AUTHORITY\SYSTEM];
ALTER ROLE db_datawriter ADD MEMBER [NT AUTHORITY\SYSTEM];
GO
