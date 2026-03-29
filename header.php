<?php 
// header.php - النسخة النهائية المستقرة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: login.php");
    exit;
}

global $current_lang, $pageTitle; 
$is_page_using_sidebar = true; 
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang ?? 'ar'; ?>" dir="rtl"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title><?php echo $pageTitle ?? 'نظام إدارة الصرافة'; ?></title>
    
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css"> 
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');
        :root { --light-bg: #f8f9fa; --primary-color: #007bff; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--light-bg); margin: 0; padding: 0; text-align: right; direction: rtl; overflow-x: hidden; }
        .content { margin-right: 280px; margin-left: 0; padding: 20px; background-color: var(--light-bg); min-height: 100vh; transition: all 0.3s; }
        
        @media print { .sidebar, .d-print-none, .footer { display: none !important; } .content-wrapper { margin-right: 0 !important; padding: 0 !important; } body { background-color: #fff !important; color: #000 !important; } }
        
        @media screen and (max-width: 992px) { 
            .content { margin-right: 0 !important; padding: 15px; } 
        }
    </style>
</head>
<body>
    <?php if ($is_page_using_sidebar): ?>
        <?php include 'sidebar.php'; ?> 
    <?php endif; ?>
    <div class="content-wrapper">
        <div class="content">