<?php

// view_reports.php - صفحة عرض وتصدير تقارير نهاية اليوم للمدير العام


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php'; 

// 🛑 التحقق من الصلاحية: يجب أن يكون المدير العام أو من لديه صلاحية العرض
// نمنع الموظف العادي من الوصول للصفحة الشاملة، ونسمح فقط للمدير العام
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'مدير عام')) {
    header("Location: unauthorized.php");
    exit;
}


// --------------------------------------------------------------------
// 1. معالجة طلب التصدير إلى Excel
// --------------------------------------------------------------------

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // تحديد اسم الملف وتاريخه
    $filename = "End_of_Day_Reports_" . date('Ymd') . ".xls";
    
    // إرسال ترويسات الـ HTTP اللازمة لملفات Excel
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    // منع التخزين المؤقت
    header("Pragma: no-cache");
    header("Expires: 0");

    // سنقوم بتوليد محتوى التقرير لاحقاً لضمان وجوده في ملف HTML واحد
    $export_mode = true;
} else {
    $export_mode = false;
    // تم التعليق على تضمين الهيدر هنا مؤقتًا للمساعدة في اختبار مشكلة "headers already sent"
    // يجب إزالة أي مسافات أو مخرجات قبل وسم PHP الافتتاحي في db_connect.php و header.php
    include 'header.php'; // تضمين الترويسة العادية لصفحة الويب
}


// --------------------------------------------------------------------
// 2. جلب البيانات من قاعدة البيانات
// --------------------------------------------------------------------

// الاستعلام لجلب جميع التقارير مع اسم الفرع واسم الموظف
// *تم التعديل:* تغيير b.branch_name_ar إلى b.name لحل مشكلة Unknown column
$reports_q = $conn->prepare("
    SELECT 
        r.report_date, 
        r.summary_text, 
        r.attached_file_path, 
        b.name AS branch_name,  
        u.full_name AS user_name,
        r.sent_at
    FROM end_of_day_reports r
    JOIN branches b ON r.branch_id = b.id
    JOIN users u ON r.user_id = u.id
    ORDER BY r.sent_at DESC
");
$reports_q->execute();
$reports_r = $reports_q->get_result();


// --------------------------------------------------------------------
// 3. بناء هيكل HTML للتقرير (يُستخدم للعرض والتصدير)
// --------------------------------------------------------------------
?>

<?php if (!$export_mode): // هذا الجزء يظهر فقط في وضع العرض العادي ?>
<div class="container-fluid">
    <h1 class="mb-4"><i class="fas fa-file-invoice"></i> تقارير نهاية اليوم الشاملة</h1>
    
    <div class="mb-3">
        <a href="?export=excel" class="btn btn-success"><i class="fas fa-file-excel"></i> تصدير إلى Excel</a>
        <button onclick="window.print()" class="btn btn-danger"><i class="fas fa-file-pdf"></i> طباعة (PDF)</button>
    </div>
<?php endif; ?>


    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="reportsTable">
            <thead>
                <tr>
                    <th>تاريخ الإرسال</th>
                    <th>تاريخ التقرير</th>
                    <th>الفرع</th>
                    <th>المرسل (الموظف)</th>
                    <th>ملخص التقرير</th>
                    <th>الملف المرفق</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports_r->num_rows > 0): ?>
                    <?php while ($row = $reports_r->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['sent_at'])); ?></td>
                            <td><?php echo $row['report_date']; ?></td>
                            <td><?php echo $row['branch_name']; ?></td>
                            <td><?php echo $row['user_name']; ?></td>
                            <td><?php echo htmlspecialchars($row['summary_text']); ?></td>
                            <td>
                                <?php if ($row['attached_file_path']): ?>
                                    <a href="<?php echo $row['attached_file_path']; ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i> تحميل</a>
                                <?php else: ?>
                                    لا يوجد
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">لا توجد تقارير نهاية يوم مُرسلة حتى الآن.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php if (!$export_mode): // هذا الجزء يظهر فقط في وضع العرض العادي ?>
</div>

<?php 
include 'footer.php';
endif;

// إغلاق الاتصال بعد الانتهاء
if (isset($conn)) {
    $conn->close();
}

?>