<?php
session_start();
include 'db_connect.php'; 

// 1. التحقق من الصلاحية
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. التحقق من وجود ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: currencies.php?error=invalid_id");
    exit;
}

$currency_id = intval($_GET['id']);
$local_currency_code = 'LYD'; // افترض أن العملة المحلية هي الدينار الليبي
$error_redirect = "Location: currencies.php?error=";
$success_redirect = "Location: currencies.php?success=deleted";

// 3. منع حذف العملة المحلية
$check_local_sql = "SELECT currency_code FROM currencies WHERE id = $currency_id";
$result = $conn->query($check_local_sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['currency_code'] == $local_currency_code) {
        header($error_redirect . "cannot_delete_local");
        exit;
    }
} else {
    // العملة غير موجودة
    header($error_redirect . "invalid_id");
    exit;
}

// 4. بدء المعاملة وحذف السجلات المرتبطة (لتجنب Foreign Key Errors)
$conn->begin_transaction();

try {
    
    // أ. حذف الأرصدة المرتبطة (من treasury_balances)
    $delete_balances = "DELETE FROM treasury_balances WHERE currency_id = $currency_id";
    if (!$conn->query($delete_balances)) {
        throw new Exception("خطأ في حذف الأرصدة.");
    }

    // ب. حذف أسعار الصرف المرتبطة (من exchange_rates)
    $delete_rates = "DELETE FROM exchange_rates WHERE currency_id = $currency_id";
    if (!$conn->query($delete_rates)) {
        throw new Exception("خطأ في حذف الأسعار.");
    }
    
    // ج. حذف العمليات المرتبطة (من transactions) - هذه هي الإضافة الأساسية
    // يتم حذف العمليات التي استخدمت هذه العملة كعملة منشأ (from) أو عملة وجهة (to)
    $delete_transactions = "
        DELETE FROM transactions 
        WHERE from_currency_id = $currency_id OR to_currency_id = $currency_id
    ";
    if (!$conn->query($delete_transactions)) {
        throw new Exception("خطأ في حذف العمليات المرتبطة.");
    }
    
    // د. حذف العملة نفسها (من currencies)
    $delete_currency = "DELETE FROM currencies WHERE id = $currency_id";
    if (!$conn->query($delete_currency)) {
        throw new Exception("خطأ في حذف العملة نفسها.");
    }

    // ه. تأكيد المعاملة
    $conn->commit();
    
    // 5. إعادة التوجيه للنجاح
    header($success_redirect);
    exit;

} catch (Exception $e) {
    // 6. التراجع عن المعاملة في حالة الخطأ
    $conn->rollback();
    
    // 7. إعادة التوجيه للخطأ
    header($error_redirect . "db_error"); 
    exit;
}

$conn->close();
?>