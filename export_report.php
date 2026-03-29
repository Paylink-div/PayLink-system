<?php
// export_report.php - معالجة التصدير إلى CSV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php'; 

// التحقق من الصلاحية (ضروري)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'مدير عام')) {
    exit; // لا تسمح بالتصدير إذا لم يكن مدير عام
}

// 1. جلب المدخلات
$branch_id = $_GET['branch_id'] ?? 0;
$report_date = $_GET['report_date'] ?? date('Y-m-d');


if ($branch_id == 0) {
    echo "خطأ: يجب تحديد فرع.";
    exit;
}

// 2. إعداد عناوين التصدير لملف CSV
$filename = "Branch_Report_" . $branch_id . "_" . $report_date . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 3. إنشاء ملف CSV مؤقت للكتابة
$output = fopen('php://output', 'w');

// إضافة BOM (Byte Order Mark) لدعم اللغة العربية في Excel
fwrite($output, "\xEF\xBB\xBF");

// 4. كتابة عناوين الأعمدة (Header Row)
$header = [
    'التاريخ والوقت', 
    'الفرع ID', 
    'اسم الفرع', 
    'الموظف', 
    'النوع', 
    'المبلغ المرسل', 
    'العملة المرسلة', 
    'المبلغ المستلم', 
    'العملة المستلمة', 
    'سعر الصرف',
];
fputcsv($output, $header);

// 5. جلب البيانات من قاعدة البيانات
$report_query = $conn->prepare("
    SELECT 
        t.transaction_date, 
        t.branch_id,
        b.name AS branch_name,
        u.full_name AS user_name,
        t.transaction_type,
        t.amount_from, 
        c_from.currency_code AS from_currency, 
        t.amount_to, 
        c_to.currency_code AS to_currency,
        t.exchange_rate
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN branches b ON t.branch_id = b.id
    LEFT JOIN currencies c_from ON t.from_currency_id = c_from.id
    LEFT JOIN currencies c_to ON t.to_currency_id = c_to.id
    WHERE t.branch_id = ? AND DATE(t.transaction_date) = ?
    ORDER BY t.transaction_date DESC
");

$report_query->bind_param("is", $branch_id, $report_date);
$report_query->execute();
$report_result = $report_query->get_result();

// 6. كتابة البيانات في ملف CSV
while ($row = $report_result->fetch_assoc()) {
    $data = [
        $row['transaction_date'],
        $row['branch_id'],
        $row['branch_name'],
        $row['user_name'],
        ($row['transaction_type'] == 'exchange' ? 'صرف عملة' : 'غير محدد'),
        number_format($row['amount_from'], 4, '.', ''), // إزالة الفواصل لتجنب المشاكل في CSV
        $row['from_currency'],
        number_format($row['amount_to'], 4, '.', ''),
        $row['to_currency'],
        number_format($row['exchange_rate'], 4, '.', ''),
    ];
    fputcsv($output, $data);
}

// 7. إغلاق الملف وإنهاء البرنامج
fclose($output);
$conn->close();
exit;
?>