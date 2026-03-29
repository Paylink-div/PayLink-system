<?php
// translation_functions.php

// تحديد اللغة الحالية: نعتمد على ما في الجلسة، والافتراضي هو العربي
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$current_lang = $_SESSION['lang'] ?? 'ar'; 

// مصفوفة الترجمة (يجب تحديثها باستمرار لتشمل كل النصوص الجديدة في المنظومة)
$translations = [
    // المفاتيح الإنجليزية (الافتراضية) -> القيم العربية
    'ar' => [
        'PayLink' => 'PayLink',
        'Faster transfers, greater trust' => 'تحويلات أسرع، ثقة أكبر',
        'General Manager' => 'المدير العام',
        'Branch Manager' => 'مدير الفرع',
        'Employee' => 'الموظف',
        'Unknown User' => 'مستخدم غير معروف',
        'Operating Branch' => 'فرع التشغيل',
        'General (Comprehensive View)' => 'الرئيسي (عرض شامل)',
        'Not associated with a branch' => 'غير مرتبط بفرع',
        'Dashboard' => 'لوحة التحكم',
        'System Management' => 'إدارة النظام',
        'Perform Transaction' => 'إجراء عملية صرف',
        'Daily Closure' => 'إغلاق اليومية',
        'Branch Management' => 'إدارة الفروع',
        'Currencies and Balances Management' => 'إدارة العملات والأرصدة',
        'Client Management' => 'إدارة العملاء',
        'User Management' => 'إدارة المستخدمين',
        'Update Exchange Rates' => 'تحديث أسعار الصرف',
        'Treasury Balances Management' => 'إدارة أرصدة الخزينة',
        'What-If Analysis' => 'تحليل "ماذا لو؟"',
        'Reports and Reconciliation' => 'التقارير والمطابقة',
        'Comprehensive Reports' => 'التقارير الشاملة',
        'Logout' => 'تسجيل الخروج',
        'User Role' => 'دور المستخدم',
        // 💡 ابدأ بإضافة ترجمات النصوص التي ستجدها في الملفات الأخرى هنا
        'Branch Name' => 'اسم الفرع', 
        'Branch Address' => 'عنوان الفرع',
        'Save' => 'حفظ',
        'Add New Branch' => 'إضافة فرع جديد',
        'The main dashboard' => 'لوحة التحكم الرئيسية',
        'Home' => 'الرئيسية',
        // ... (أضف المزيد من الترجمات هنا)
    ],
    // المفاتيح الإنجليزية (الافتراضية) -> القيم الإنجليزية
    'en' => [
        'PayLink' => 'PayLink',
        'Faster transfers, greater trust' => 'Faster transfers, greater trust',
        'General Manager' => 'General Manager',
        'Branch Manager' => 'Branch Manager',
        'Employee' => 'Employee',
        'Unknown User' => 'Unknown User',
        'Operating Branch' => 'Operating Branch',
        'General (Comprehensive View)' => 'General (Comprehensive View)',
        'Not associated with a branch' => 'Not associated with a branch',
        'Dashboard' => 'Dashboard',
        'System Management' => 'System Management',
        'Perform Transaction' => 'Perform Transaction',
        'Daily Closure' => 'Daily Closure',
        'Branch Management' => 'Branch Management',
        'Currencies and Balances Management' => 'Currencies and Balances Management',
        'Client Management' => 'Client Management',
        'User Management' => 'User Management',
        'Update Exchange Rates' => 'Update Exchange Rates',
        'Treasury Balances Management' => 'Treasury Balances Management',
        'What-If Analysis' => 'What-If Analysis',
        'Reports and Reconciliation' => 'Reports and Reconciliation',
        'Comprehensive Reports' => 'Comprehensive Reports',
        'Logout' => 'Logout',
        'User Role' => 'User Role',
        // 💡 ترجمات إنجليزية إضافية
        'Branch Name' => 'Branch Name', 
        'Branch Address' => 'Branch Address',
        'Save' => 'Save',
        'Add New Branch' => 'Add New Branch',
        'The main dashboard' => 'The main dashboard',
        'Home' => 'Home',
        // ... (أضف المزيد من الترجمات هنا)
    ]
];

// دالة الترجمة
function __($text) {
    global $translations, $current_lang;
    return $translations[$current_lang][$text] ?? $text; // إرجاع النص إذا لم توجد ترجمة
}
?>