<?php
// C:\xampp\htdocs\paylink_system\lang_manager.php

// 1. بدء الجلسة (لضمان عمل $_SESSION)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. تحديد اللغة الافتراضية
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar'; 
}

// 3. معالجة طلب تغيير اللغة (إذا ضغط المستخدم على زر التبديل)
if (isset($_GET['set_lang'])) {
    $allowed_langs = ['ar', 'en'];
    $new_lang = strtolower($_GET['set_lang']);
    
    if (in_array($new_lang, $allowed_langs)) {
        $_SESSION['lang'] = $new_lang;
    }
    
    // إعادة التوجيه لتنظيف الرابط
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $redirect_url);
    exit;
}

// 4. تحميل مصفوفة الترجمة الحالية
$current_lang = $_SESSION['lang'];
$lang_file = __DIR__ . "/lang/{$current_lang}.php";

if (file_exists($lang_file)) {
    // تحميل المصفوفة في المتغير $lang (هذا هو أهم متغير سنستخدمه)
    $lang = require $lang_file;
} else {
    // تحميل الإنجليزية كنسخة احتياطية
    $lang = require _DIR_ . "/lang/en.php";
}

// 5. تحديد اتجاه الصفحة (للعربية RTL وللإنجليزية LTR)
$lang_direction = ($current_lang === 'ar') ? 'rtl' : 'ltr';

?>