<?php
// set_language.php

// 🚨 1. يجب بدء الجلسة في بداية الملف
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. التحقق من وجود اللغة الجديدة في الرابط
if (isset($_GET['lang'])) {
    $new_lang = $_GET['lang'];
    
    // 3. التحقق من أن اللغة المدخلة مدعومة
    if (in_array($new_lang, ['ar', 'en'])) {
        
        // 4. 🚨 تحديث متغير الجلسة باللغة الجديدة 
        $_SESSION['lang'] = $new_lang;
    }
}

// 5. إعادة التوجيه إلى الصفحة التي جاء منها المستخدم (لإعادة تحميلها باللغة الجديدة)
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';

header("Location: " . $redirect_url);
exit(); // إنهاء السكربت
?>