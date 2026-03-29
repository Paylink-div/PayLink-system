<?php
// db_connect.php - النسخة الاحترافية المتوافقة مع Docker و السيرفرات السحابية

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// جلب بيانات الاتصال من البيئة (Environment) أو استخدام الافتراضي للدوكر
$master_host = getenv('DB_HOST') ?: "db"; 
$master_user = getenv('DB_USER') ?: "root";
$master_pass = getenv('DB_PASS') ?: "root"; 
$master_db   = getenv('DB_NAME') ?: "paylink_admin_system";

// الاتصال بالنظام المركزي
$master_conn = new mysqli($master_host, $master_user, $master_pass, $master_db);

if ($master_conn->connect_error) {
    // محاولة أخيرة بـ localhost في حال فشل الدوكر (للتجربة المحلية فقط)
    $master_conn = new mysqli("localhost", "root", "", $master_db);
    if ($master_conn->connect_error) {
        die("خطأ في الاتصال بالنظام المركزي: " . $master_conn->connect_error);
    }
}
$master_conn->set_charset("utf8mb4");

$subdomain = "";

// 1. إذا كان اسم الشركة موجود في الرابط
if (isset($_GET['company'])) {
    $subdomain = $_GET['company'];
    $_SESSION['saas_subdomain'] = $subdomain; 
} 
// 2. البحث عنه في الجلسة
elseif (isset($_SESSION['saas_subdomain'])) {
    $subdomain = $_SESSION['saas_subdomain'];
}
// 3. التحقق من الساب دومين الحقيقي (للسيرفرات الحقيقية)
else {
    $host_parts = explode('.', $_SERVER['HTTP_HOST']);
    if (count($host_parts) >= 2 && $host_parts[0] !== 'localhost' && $host_parts[0] !== 'www') {
        $subdomain = $host_parts[0];
        $_SESSION['saas_subdomain'] = $subdomain;
    }
}

// توجيه الاتصال لقاعدة بيانات الشركة المعنية
if (!empty($subdomain)) {
    $stmt = $master_conn->prepare("SELECT * FROM companies WHERE subdomain = ? AND status = 'active'");
    $stmt->bind_param("s", $subdomain);
    $stmt->execute();
    $result = $stmt->get_result();
    $company_data = $result->fetch_assoc();

    if ($company_data) {
        // الاتصال بقاعدة بيانات الشركة الفرعية باستخدام نفس بيانات السيرفر
        $conn = new mysqli($master_host, $master_user, $master_pass, $company_data['db_name']);
        
        if ($conn->connect_error) {
            die("عذراً، قاعدة بيانات الشركة غير متوفرة حالياً.");
        }

        if (!defined('COMPANY_NAME')) define('COMPANY_NAME', $company_data['company_name']);
        if (!defined('IS_COMPANY_CONTEXT')) define('IS_COMPANY_CONTEXT', true);
        if (!defined('ENABLED_FEATURES')) define('ENABLED_FEATURES', $company_data['enabled_features']);
        
        $conn->set_charset("utf8mb4");
    } else {
        die("الشركة المطلوبة غير موجودة أو حسابها معطل.");
    }
} else {
    // وضع الإدارة المركزية
    $conn = $master_conn;
    if (!defined('IS_ADMIN_CONTEXT')) define('IS_ADMIN_CONTEXT', true);
    if (!defined('COMPANY_NAME')) define('COMPANY_NAME', 'نظام PayLink المركزي'); 
}

$master_connection = $master_conn; 
$conn->set_charset("utf8mb4");
?>