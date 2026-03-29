<?php
// ملف إعدادات التطبيق للعميل (Client Application Settings)
// هذا الملف يحتوي على المتغيرات الأساسية اللازمة لعمل التطبيق، مثل مفاتيح API، روابط الخدمة، وإعدادات البيئة.
// --------------------------------------------------------------------------------

/**
 * إعدادات التطبيق الأساسية
 *
 * @var array
 */
$clientAppSettings = [
    // إعداد البيئة: يمكن أن يكون 'development' (تطوير) أو 'production' (إنتاج)
    'ENVIRONMENT' => 'development',

    // مفتاح API العام (Public API Key) المستخدم للاتصال بالخدمات الخارجية (يجب استبدال القيمة)
    'PUBLIC_API_KEY' => 'YOUR_PUBLIC_API_KEY_FOR_CLIENT_SIDE',

    // الرابط الأساسي (Base URL) لنهاية الـ API الخلفية (Backend API Endpoint)
    'API_BASE_URL' => 'https://api.yourdomain.com/v1/',

    // رابط صفحة تسجيل الدخول/المصادقة (Authentication URL)
    'LOGIN_URL' => '/auth/login.php',

    // إعدادات التخزين المؤقت (Caching) - تشغيل/إيقاف
    'CACHE_ENABLED' => true,
    
    // مدة صلاحية التخزين المؤقت بالدقائق
    'CACHE_LIFETIME_MINUTES' => 60,

    // اسم التطبيق الذي سيظهر في واجهة المستخدم
    'APP_NAME' => 'تطبيق العميل',

    // إصدار التطبيق الحالي
    'APP_VERSION' => '1.0.0',
];

// --------------------------------------------------------------------------------
// دالة مساعدة (Helper Function) للوصول إلى الإعدادات بسهولة
if (!function_exists('get_client_setting')) {
    /**
     * تسترجع قيمة إعداد محدد من مصفوفة الإعدادات.
     *
     * @param string $key مفتاح الإعداد (مثل 'API_BASE_URL').
     * @param mixed $default القيمة الافتراضية في حال عدم العثور على المفتاح.
     * @return mixed
     */
    function get_client_setting($key, $default = null) {
        global $clientAppSettings;
        return $clientAppSettings[$key] ?? $default;
    }
}

// مثال على كيفية استخدام الإعدادات (يمكن حذفه في الكود النهائي):
// $apiUrl = get_client_setting('API_BASE_URL');
// // echo "API URL: " . $apiUrl;
// --------------------------------------------------------------------------------
?>