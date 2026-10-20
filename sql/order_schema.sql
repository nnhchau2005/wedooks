/* =========================================================
   order_schema.sql – SQL Server (chạy trong SSMS, bấm F5)
   Chạy SAU cart_schema.sql. Tạo bảng Orders + OrderItems.
   Chạy lại nhiều lần không sao: bảng đã có thì bỏ qua.
   ========================================================= */
USE WedooksDB;
GO

IF OBJECT_ID(N'dbo.Orders', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Orders (
        OrderID       VARCHAR(20)    NOT NULL PRIMARY KEY,   -- VD WDK260930482913
        CustomerName  NVARCHAR(100)  NOT NULL,
        Phone         VARCHAR(15)    NOT NULL,
        Email         NVARCHAR(150)  NULL,
        Address       NVARCHAR(500)  NOT NULL,
        Note          NVARCHAR(500)  NULL,
        Subtotal      DECIMAL(18,0)  NOT NULL,
        ShippingFee   DECIMAL(18,0)  NOT NULL DEFAULT 0,
        TotalAmount   DECIMAL(18,0)  NOT NULL,
        PaymentMethod VARCHAR(20)    NOT NULL,               -- COD / VNPAY / MOMO
        PaymentStatus VARCHAR(20)    NOT NULL DEFAULT 'PENDING',  -- PENDING / PAID / FAILED
        CreatedAt     DATETIME2      NOT NULL DEFAULT SYSDATETIME()
    );
END
GO

IF OBJECT_ID(N'dbo.OrderItems', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.OrderItems (
        OrderItemID INT IDENTITY(1,1) PRIMARY KEY,
        OrderID     VARCHAR(20)   NOT NULL
                    REFERENCES dbo.Orders(OrderID) ON DELETE CASCADE,
        ProductID   INT           NOT NULL,
        ProductName NVARCHAR(200) NOT NULL,   -- lưu lại tên & giá LÚC MUA
        Size        NVARCHAR(5)   NOT NULL,
        UnitPrice   DECIMAL(18,0) NOT NULL,
        Quantity    INT           NOT NULL CHECK (Quantity > 0),
        LineTotal   DECIMAL(18,0) NOT NULL
    );
    CREATE INDEX IX_OrderItems_Order ON dbo.OrderItems(OrderID);
END
GO

-- Xem đơn vừa đặt:
SELECT TOP 20 * FROM dbo.Orders ORDER BY CreatedAt DESC;
SELECT * FROM dbo.OrderItems;


/* =========================================================
   NẾU DÙNG MySQL thì chạy khối dưới thay cho phần trên
   =========================================================
USE wedooks;

CREATE TABLE IF NOT EXISTS Orders (
    OrderID       VARCHAR(20)   NOT NULL PRIMARY KEY,
    CustomerName  VARCHAR(100)  NOT NULL,
    Phone         VARCHAR(15)   NOT NULL,
    Email         VARCHAR(150)  NULL,
    Address       VARCHAR(500)  NOT NULL,
    Note          VARCHAR(500)  NULL,
    Subtotal      DECIMAL(18,0) NOT NULL,
    ShippingFee   DECIMAL(18,0) NOT NULL DEFAULT 0,
    TotalAmount   DECIMAL(18,0) NOT NULL,
    PaymentMethod VARCHAR(20)   NOT NULL,
    PaymentStatus VARCHAR(20)   NOT NULL DEFAULT 'PENDING',
    CreatedAt     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS OrderItems (
    OrderItemID INT AUTO_INCREMENT PRIMARY KEY,
    OrderID     VARCHAR(20)   NOT NULL,
    ProductID   INT           NOT NULL,
    ProductName VARCHAR(200)  NOT NULL,
    Size        VARCHAR(5)    NOT NULL,
    UnitPrice   DECIMAL(18,0) NOT NULL,
    Quantity    INT           NOT NULL CHECK (Quantity > 0),
    LineTotal   DECIMAL(18,0) NOT NULL,
    KEY IX_OrderItems_Order (OrderID),
    FOREIGN KEY (OrderID) REFERENCES Orders(OrderID) ON DELETE CASCADE
);
*/
