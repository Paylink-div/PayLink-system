<?php
// auth_check.php
session_start();

// التحقق من أن المستخدم قام بتسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    // إذا لم يكن مسجل دخوله، وجهه إلى صفحة الدخول
    header("location: login.php");
    exit;
}

// الدالة المساعدة للتحقق من الصلاحيات (ميزة 1: إدارة الصلاحيات)
function check_role($required_role) {
    if ($_SESSION['user_role'] !== $required_role) {
        // يمكن تغيير التوجيه لصفحة "ممنوع الوصول"
        header("location: dashboard.php?access_denied=true");
        exit;
    }
}
?>