<?php
// ملف: lock_status_check.php (النسخة المُحدثة)

// يجب تضمين db_connect.php مسبقاً

// 🛑 قم بتعريف مفتاح النجاح النهائي هنا 🛑
// هذا المفتاح لا يجب أن يتغير أبداً.
define('PERMANENT_LICENSE_KEY', 'FULLY_PAID_2025'); 

$query = "SELECT license_status FROM system_config WHERE id = 1 LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $config = $result->fetch_assoc();
    
    $current_status = trim($config['license_status']);
    
    // 🛑 التحقق من حالة القفل 🛑
    
    if ($current_status !== PERMANENT_LICENSE_KEY) {
        // إذا كان المفتاح الحالي لا يطابق المفتاح النهائي المدفوع
        
        // يمكنك إضافة شروط أخرى هنا (مثل PAUSED أو REVOKED)
        // حالياً، أي شيء غير المفتاح الدائم سيعتبر مقفولاً.
        
        // توجيه المستخدم إلى صفحة رسالة القفل
        header("Location: system_locked_page.php");
        exit;
    }
    
    // إذا كان المفتاح يطابق المفتاح الدائم، يستمر تنفيذ الصفحة (المنظومة مفتوحة)
} else {
    // إذا لم يتم العثور على سجل الإعدادات، يعتبر النظام مقفلاً للأمان
    header("Location: system_locked_page.php");
    exit;
}

?>