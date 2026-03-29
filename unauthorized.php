<?php
// unauthorized.php - صفحة الوصول غير المصرح به

// تأكد من بدء الجلسة لاستقبال رسائل الخطأ المخزنة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// جلب رسالة الخطأ من الجلسة (إذا كانت متوفرة)
$error_message = $_SESSION['error_message'] ?? "عفواً، ليس لديك الصلاحية اللازمة للوصول إلى هذه الميزة أو الصفحة.";

// إفراغ رسالة الخطأ من الجلسة بعد عرضها
unset($_SESSION['error_message']);

// 💡 ملاحظة: إذا كنت تستخدم ملف sidebar.php في باقي صفحاتك، 
// قم بإلغاء تعليق السطر التالي وتعديل هيكل الـ HTML في الأسفل
// include 'sidebar.php'; 

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>غير مصرح بالوصول - 403</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .error-container {
            display: flex;
            justify-content: center;
            align-items: center;
            /* تم التعديل: إذا كان لديك شريط جانبي، قد تحتاج لضبط الهامش هنا */
            min-height: 100vh;
            text-align: center;
            background-color: #f8f9fa;
        }
        .error-card {
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
    </style>
</head>
<body>


<div class="error-container">
    <div class="error-card">
        <i class="fas fa-lock fa-5x text-danger mb-4"></i>
        <h1 class="display-4 text-danger">403</h1>
        <h2>غير مصرح بالوصول</h2>
        
        <p class="lead text-dark font-weight-bold"><?php echo htmlspecialchars($error_message); ?></p>
        
        <hr>
        
        <p>يرجى العودة إلى الصفحة الرئيسية أو التواصل مع مدير النظام.</p>
        <a href="index.php" class="btn btn-primary mt-3"><i class="fas fa-home"></i> العودة للوحة التحكم</a>
    </div>
</div>


</body>
</html>