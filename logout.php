<?php
// logout.php - نسخة SaaS الاحترافية
session_start();

/**
 * 1. قبل تدمير الجلسة، نحفظ اسم الساب دومين الخاص بالشركة 
 * لكي لا نفقد المسار بعد تسجيل الخروج.
 */
$current_subdomain = $_SESSION['saas_subdomain'] ?? '';

// 2. مسح كافة متغيرات الجلسة
$_SESSION = array();

// 3. مسح ملفات تعريف الارتباط (Cookies) الخاصة بالجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. تدمير الجلسة فعلياً من السيرفر
session_destroy();

/**
 * 5. التوجيه الذكي:
 */
if (!empty($current_subdomain)) {
    // العودة لصفحة تسجيل دخول الشركة المحددة
    header("Location: login.php?company=" . urlencode($current_subdomain));
} else {
    // العودة لصفحة الدخول العامة في حال عدم وجود شركة
    header("Location: login.php");
}

exit;
?>