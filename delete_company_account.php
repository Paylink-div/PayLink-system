<?php

// delete_company_account.php - معالجة حذف حساب بنكي للشركة
// تم التعديل ليعتمد على تسجيل الدخول فقط (حذف ACL)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// يتضمن هذا الملف الاتصال بقاعدة البيانات ($conn)
require_once 'db_connect.php'; 

// التحقق من اتصال قاعدة البيانات
if (!isset($conn) || $conn->connect_error) {
    die("خطأ في الاتصال بقاعدة البيانات.");
}

$current_user_id = $_SESSION['user_id'] ?? 0;

// 1. التحقق من تسجيل الدخول (التعديل: أصبحت هذه هي نقطة التحقق الوحيدة)
if (!$current_user_id) {
    header("Location: index.php");
    exit;
}

// 🛑🛑 تم حذف التحقق من الصلاحية (acl_functions.php / CAN_MANAGE_COMPANY_ACCOUNTS) 🛑🛑

// 2. جلب معرّف الحساب
$account_id = $_GET['id'] ?? null;

if (empty($account_id) || !is_numeric($account_id)) {
    $_SESSION['error_message'] = "معرّف الحساب غير صالح.";
    header("Location: company_treasury.php");
    exit;
}


// 3. تنفيذ عملية الحذف
$sql = "DELETE FROM company_bank_accounts WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $account_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success_message'] = "تم حذف الحساب البنكي رقم (#{$account_id}) بنجاح.";
        } else {
            $_SESSION['error_message'] = "لم يتم العثور على حساب بالمعرّف المحدد للحذف.";
        }
    } else {
        $_SESSION['error_message'] = "خطأ في تنفيذ عملية الحذف: " . $stmt->error;
    }
    
    $stmt->close();

} else {
    $_SESSION['error_message'] = "خطأ في تجهيز استعلام الحذف: " . $conn->error;
}


$conn->close();

// إعادة التوجيه إلى صفحة الخزينة
header("Location: company_treasury.php");
exit;

?>