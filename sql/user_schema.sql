/* =========================================================
   user_schema.sql – SQL Server (chạy trong SSMS, bấm F5)
   Chạy SAU order_schema.sql. Tạo bảng Users (đăng nhập bằng email)
   và thêm cột UserID vào Orders (đơn thuộc tài khoản nào).
   Chạy lại nhiều lần không sao: đã có thì bỏ qua.
   ========================================================= */
USE WedooksDB;
GO

IF OBJECT_ID(N'dbo.Users', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Users (
        UserID       INT IDENTITY(1,1) PRIMARY KEY,
        Email        NVARCHAR(150) NOT NULL UNIQUE,   -- luôn lưu chữ thường
        PasswordHash VARCHAR(255)  NOT NULL,          -- password_hash() của PHP, KHÔNG lưu mật khẩu gốc
        FullName     NVARCHAR(100) NOT NULL,
        CreatedAt    DATETIME2     NOT NULL DEFAULT SYSDATETIME()
    );
END
GO

IF COL_LENGTH(N'dbo.Orders', N'UserID') IS NULL
    ALTER TABLE dbo.Orders ADD UserID INT NULL REFERENCES dbo.Users(UserID);
GO

SELECT UserID, Email, FullName, CreatedAt FROM dbo.Users;


/* =========================================================
   NẾU DÙNG MySQL thì chạy khối dưới thay cho phần trên
   =========================================================
USE wedooks;

CREATE TABLE IF NOT EXISTS Users (
    UserID       INT AUTO_INCREMENT PRIMARY KEY,
    Email        VARCHAR(150) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    FullName     VARCHAR(100) NOT NULL,
    CreatedAt    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE Orders
    ADD COLUMN UserID INT NULL,
    ADD FOREIGN KEY (UserID) REFERENCES Users(UserID);
*/
