<?php
// =======================================================
// smtp_config.php - إعدادات الإيميل (مطلوب بواسطة process_password_reset.php)
// =======================================================

// إعدادات PHPMailer باستخدام خادم Gmail
$smtp_settings = [
    // 💡 الخادم: خادم SMTP الخاص بـ Google
    'Host'       => 'smtp.gmail.com',         
    
    // 💡 المصادقة: يجب أن تكون true لتسجيل الدخول
    'SMTPAuth'   => true,                      
    
    // 💡 اسم المستخدم: بريدك الإلكتروني الكامل الذي سيتم الإرسال منه 
    'Username'   => 'zagdon01@gmail.com',    
    
    // 🛑 كلمة السر: كلمة سر التطبيق (App Password) المُولدة من إعدادات أمان Google. 
    // لا تستخدم كلمة السر العادية لحسابك.
    'Password'   => 'uijy ylvl avpz sdbu',       
    
    // 💡 التشفير: يفضل استخدام 'tls' مع المنفذ 587
    'SMTPSecure' => 'tls',                     
    
    // 💡 المنفذ: المنفذ القياسي لـ TLS
    'Port'       => 587,                       
    
    // 💡 بيانات المرسل: [البريد الإلكتروني, اسم العرض]
    'setFrom'    => ['YOUR_GMAIL_ADDRESS@gmail.com', 'PayLink Support'] 
];

// ملاحظة: إذا فشل الاتصال باستخدام TLS/587، يمكنك محاولة التبديل إلى SSL/465:
/*
$smtp_settings = [
    'Host'       => 'smtp.gmail.com', 
    'SMTPAuth'   => true,
    'Username'   => 'YOUR_GMAIL_ADDRESS@gmail.com',
    'Password'   => 'YOUR_APP_PASSWORD',
    'SMTPSecure' => 'ssl', 
    'Port'       => 465,
    'setFrom'    => ['YOUR_GMAIL_ADDRESS@gmail.com', 'PayLink Support']
];
*/

?>