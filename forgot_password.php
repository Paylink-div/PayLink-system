<?php
// forgot_password.php
session_start();

// استقبال الرسائل من ملف المعالجة عبر الجلسة
$message = '';
$message_type = '';

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']); // حذف الرسالة بعد عرضها
}

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>نسيت كلمة السر - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { 
            background-color: #f8f9fa; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }
        .reset-container { 
            width: 90%; 
            max-width: 400px; 
            padding: 25px; 
            margin: auto; 
            background-color: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .reset-container a {
            color: #007bff;
        }
    </style>
</head>
<body>

    <div class="reset-container">
        <h3 class="text-center text-primary mb-4">🔑 إعادة تعيين كلمة السر</h3>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center small"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="process_password_reset.php">
            <p class="text-center text-muted small mb-4">أدخل بريدك الإلكتروني لإرسال رمز إعادة تعيين كلمة السر.</p>
            
            <div class="form-group text-right">
                <label for="email" class="small font-weight-bold">البريد الإلكتروني:</label>
                <input type="email" class="form-control" name="email" id="email" placeholder="example@mail.com" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-4 shadow-sm">
                إرسال رمز التحقق
            </button>
            
            <p class="text-center mt-4 mb-0">
                <a href="login.php" class="text-secondary small">العودة لتسجيل الدخول</a>
            </p>
        </form>
    </div>

</body>
</html>