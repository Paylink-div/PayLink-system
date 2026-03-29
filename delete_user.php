<?php
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// التأكد من أن المستخدم لا يحذف نفسه
if ($_GET['id'] == $_SESSION['user_id']) {
    header("Location: user_management.php?error=self_delete");
    exit;
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    // عملية الحذف
    $sql = "DELETE FROM users WHERE id = $user_id";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: user_management.php?success=deleted");
    } else {
        // إذا فشل الحذف بسبب خطأ في قاعدة البيانات
        header("Location: user_management.php?error=db_error");
    }
} else {
    // إذا لم يتم تحديد ID
    header("Location: user_management.php?error=no_id");
}

$conn->close();
exit;
?>