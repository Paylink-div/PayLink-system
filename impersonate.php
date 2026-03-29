<?php
// super_admin/impersonate.php
session_start();
include '../db_connect.php';

$company_id = $_GET['id'] ?? 0;

// التحقق من وجود الشركة
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();

if ($company) {
    // 💡 الفكرة هنا: نقوم بتغيير بيانات الجلسة لتبدو وكأنك مدير هذه الشركة
    $_SESSION['user_id'] = 0; // معرف وهمي للسوبر أدمن
    $_SESSION['company_id'] = $company['id'];
    $_SESSION['user_role'] = 'مدير عام';
    $_SESSION['full_name'] = 'مدير النظام (معاينة)';
    
    // التوجيه إلى الصفحة الرئيسية للمنظومة (ولكن بهوية الشركة المختارة)
    header("Location: http://" . $company['subdomain'] . ".paylink.com/index.php");
    exit();
} else {
    die("الشركة غير موجودة.");
}
?>