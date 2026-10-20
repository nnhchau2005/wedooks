/* =========================================================
   momo_schema.sql – SQL Server (chạy trong SSMS, bấm F5)
   Chạy SAU order_schema.sql. Thêm 3 cột vào bảng Orders để lưu kết quả MoMo.
   Chạy lại nhiều lần không sao: cột đã có thì bỏ qua.
   ========================================================= */
USE WedooksDB;
GO

IF COL_LENGTH(N'dbo.Orders', N'MomoTransId') IS NULL
    ALTER TABLE dbo.Orders ADD MomoTransId VARCHAR(30) NULL;      -- mã giao dịch phía MoMo
IF COL_LENGTH(N'dbo.Orders', N'PaidAt') IS NULL
    ALTER TABLE dbo.Orders ADD PaidAt DATETIME2 NULL;             -- thời điểm thanh toán thành công
IF COL_LENGTH(N'dbo.Orders', N'PaymentNote') IS NULL
    ALTER TABLE dbo.Orders ADD PaymentNote NVARCHAR(255) NULL;    -- thông báo MoMo trả về
GO

-- Xem trạng thái thanh toán các đơn gần nhất:
SELECT TOP 20 OrderID, TotalAmount, PaymentMethod, PaymentStatus, MomoTransId, PaidAt, PaymentNote
FROM dbo.Orders
ORDER BY CreatedAt DESC;


/* =========================================================
   NẾU DÙNG MySQL thì chạy khối dưới thay cho phần trên
   =========================================================
USE wedooks;
ALTER TABLE Orders
    ADD COLUMN MomoTransId VARCHAR(30)  NULL,
    ADD COLUMN PaidAt      DATETIME     NULL,
    ADD COLUMN PaymentNote VARCHAR(255) NULL;
*/
