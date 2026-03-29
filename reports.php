<?php

// reports.php - صفحة التقارير الشاملة المحدثة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ⚠ تضمين ملف اتصال قاعدة البيانات أولاً
include 'db_connect.php'; 

// ⚠ تضمين ملف التحميل التلقائي لـ Composer
require 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. التحقق من تسجيل الدخول فقط
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$current_user_role = $_SESSION['user_role'] ?? 'موظف'; 
$current_user_branch_id = $_SESSION['branch_id'] ?? 0;
$current_page = basename(__FILE__); 

// -----------------------------------------------------------------------------------------
// 2. تعريف متغيرات الفرع التشغيلي/التصفية (branch_filter_id) 
// -----------------------------------------------------------------------------------------

$operating_branch_id = 0; 

$selected_branch_id = $_GET['branch_id'] ?? 'all'; 

if ($selected_branch_id !== 'all' && $selected_branch_id !== null) {
    $operating_branch_id = intval($selected_branch_id);
} 

// -----------------------------------------------------------------------------------------
// 3. معالجة بيانات الإدخال الأخرى
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$export_to_excel = isset($_GET['export']) && $_GET['export'] == 'true';

// 4. جلب الفروع (للقائمة المنسدلة والعرض)
$branches = [];
$branch_query_sql = "SELECT id, name FROM branches ORDER BY id";
if (isset($conn)) {
    $branch_result = $conn->query($branch_query_sql);
    while(isset($branch_result) && $row = $branch_result->fetch_assoc()) {
        $branches[$row['id']] = $row['name'];
    }
}

// 5. بناء شروط الاستعلام باستخدام $operating_branch_id
// تم إبقاء هذا الشرط للعمليات، ولكنه تم إزالته من جداول الأرصدة لتفادي الأخطاء (إذا لم يكن العمود موجوداً)
$branch_condition_general = "";
if ($operating_branch_id > 0) {
    $branch_condition_general = " AND branch_id = " . $operating_branch_id;
} 

// -----------------------------------------------------------------------------------------
// 6. جلب بيانات التقارير (إجمالي العمليات والأرباح)
// -----------------------------------------------------------------------------------------

$report_data = [
    'sales_total' => 0,
    'purchases_total' => 0,
    'discounts_total' => 0,
    'total_profit' => 0, 
    'branch_name' => ($operating_branch_id > 0 && isset($branches[$operating_branch_id])) ? $branches[$operating_branch_id] : 'جميع الفروع',
];

if (isset($conn)) {
    $transactions_sql = "
        SELECT 
            SUM(CASE WHEN transaction_type = 'SELL' THEN amount_foreign ELSE 0 END) AS sales_total,
            SUM(CASE WHEN transaction_type = 'BUY' THEN amount_foreign ELSE 0 END) AS purchases_total,
            SUM(discount_amount) AS discounts_total,
            SUM(amount_in - amount_LYD + commission_amount) AS total_profit  
        FROM transactions
        WHERE DATE(transaction_date) BETWEEN ? AND ?
        " . $branch_condition_general . "
    ";

    $stmt = $conn->prepare($transactions_sql);
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $report_data['sales_total'] = $data['sales_total'] ?? 0;
            $report_data['purchases_total'] = $data['purchases_total'] ?? 0;
            $report_data['discounts_total'] = $data['discounts_total'] ?? 0;
            $report_data['total_profit'] = $data['total_profit'] ?? 0; 
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------------------------------
// 7. جلب أرصدة الخزينة الحالية (تعديل: إزالة شرط الفرع من تصفية الخزينة مؤقتاً)
// -----------------------------------------------------------------------------------------
$treasury_balances = [];
if (isset($conn)) {
    $treasury_sql = "
        SELECT 
            t.currency_id, 
            c.currency_code, 
            SUM(t.current_balance) as total_balance
        FROM treasury_balances t
        JOIN currencies c ON t.currency_id = c.id
        WHERE 1=1
    ";
    
    // تم حذف هذا الشرط مؤقتاً لتجنب خطأ العمود غير المعروف
    /*
    if ($operating_branch_id > 0) {
           $treasury_sql .= " AND t.branch_id = " . $operating_branch_id;
    }
    */
    
    $treasury_sql .= " GROUP BY t.currency_id, c.currency_code";
    $treasury_result = $conn->query($treasury_sql);
    while(isset($treasury_result) && $row = $treasury_result->fetch_assoc()) {
        $treasury_balances[] = $row;
    }
}

// -----------------------------------------------------------------------------------------
// 8. جلب بيانات التقرير التفصيلي
// -----------------------------------------------------------------------------------------
$detailed_transactions = [];
$added_users = [];
$added_clients = [];
$exchange_rates_history = []; 
$customer_balances_summary = []; 

if (isset($conn)) {
    // 8.1 العمليات التفصيلية (صرف/فواتير)
    $detailed_transactions_sql = "
        SELECT 
            tr.transaction_date, tr.transaction_type, tr.amount_foreign, c.currency_code, 
            tr.amount_LYD, tr.rate_used AS exchange_rate, u.username AS created_by
        FROM transactions tr 
        LEFT JOIN users u ON tr.user_id = u.id
        LEFT JOIN currencies c ON tr.to_currency_id = c.id
        WHERE DATE(tr.transaction_date) BETWEEN ? AND ?
        " . ($operating_branch_id > 0 ? " AND tr.branch_id = " . $operating_branch_id : "") . "
        ORDER BY tr.transaction_date DESC
    ";
    $stmt = $conn->prepare($detailed_transactions_sql); 
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $detailed_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 8.2 المستخدمون المضافون
    $added_users_sql = "
        SELECT username, user_role, created_at
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        " . $branch_condition_general . "
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($added_users_sql);
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $added_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // 8.3 العملاء الجدد المضافون 
    $added_clients_sql = "
        SELECT full_name, phone_number, created_at
        FROM clients
        WHERE DATE(created_at) BETWEEN ? AND ?
        " . $branch_condition_general . "
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($added_clients_sql);
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $added_clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 8.4 سجل أسعار الصرف المحدثة (تم تفعيله)
    $exchange_rates_history_sql = "
        SELECT 
            c1.currency_code AS from_currency, 
            c2.currency_code AS to_currency, 
            h.old_rate,
            h.new_rate,
            h.updated_at,
            u.username AS updated_by
        FROM currency_rates_history h
        JOIN currencies c1 ON h.from_currency_id = c1.id
        JOIN currencies c2 ON h.to_currency_id = c2.id
        JOIN users u ON h.user_id = u.id
        WHERE DATE(h.updated_at) BETWEEN ? AND ?
        " . ($operating_branch_id > 0 ? " AND h.branch_id = " . $operating_branch_id : "") . "
        ORDER BY h.updated_at DESC
    ";
    $stmt = $conn->prepare($exchange_rates_history_sql);
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $exchange_rates_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // 8.5 ملخص أرصدة العملاء (تعديل: إزالة الربط بـ currency_id وشرط branch_id)
    $customer_balances_summary_sql = "
        SELECT 
            cl.full_name,
            'LYD (افتراضي)' AS currency_code, -- استبدال العملة الافتراضية هنا
            cb.current_balance
        FROM client_balances cb
        JOIN clients cl ON cb.client_id = cl.id
        -- تم حذف JOIN currencies c ON cb.currency_id = c.id
        WHERE 1=1
        -- تم حذف شرط الفرع AND cb.branch_id = ...
        AND cb.current_balance != 0
        ORDER BY cl.full_name
    ";
    $customer_balances_summary_result = $conn->query($customer_balances_summary_sql);
    while(isset($customer_balances_summary_result) && $row = $customer_balances_summary_result->fetch_assoc()) {
        $customer_balances_summary[] = $row;
    }
}

// -----------------------------------------------------------------------------------------
// 9. معالجة التصدير إلى Excel 🚀 (القسم المعدل)
// -----------------------------------------------------------------------------------------
if ($export_to_excel) {
    $spreadsheet = new Spreadsheet();
    $sheetIndex = 0;

    // -------------------------------------------------
    // تبويب 1: الملخص المالي وأرصدة الخزينة
    // -------------------------------------------------
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('ملخص الأداء المالي');
    
    // إعداد نمط اتجاه النص إلى اليمين
    $spreadsheet->getDefaultStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $row = 1;
    $sheet->setCellValue('A' . $row, 'تقرير الملخص المالي');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);

    $row += 2;
    $sheet->setCellValue('A' . $row, 'الفرع: ' . $report_data['branch_name']); $row++;
    $sheet->setCellValue('A' . $row, 'من تاريخ: ' . $start_date); $row++;
    $sheet->setCellValue('A' . $row, 'إلى تاريخ: ' . $end_date); $row++;

    $row += 2;
    $sheet->setCellValue('A' . $row, 'البند');
    $sheet->setCellValue('B' . $row, 'القيمة');
    $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
    $row++;

    $sheet->setCellValue('A' . $row, 'إجمالي المبيعات');
    $sheet->setCellValue('B' . $row, $report_data['sales_total']); $row++;
    
    $sheet->setCellValue('A' . $row, 'إجمالي المشتريات');
    $sheet->setCellValue('B' . $row, $report_data['purchases_total']); $row++;
    
    $sheet->setCellValue('A' . $row, 'إجمالي الخصومات');
    $sheet->setCellValue('B' . $row, $report_data['discounts_total']); $row++;

    $row += 2;
    $sheet->setCellValue('A' . $row, 'صافي الأرباح (خلال الفترة)');
    $sheet->setCellValue('B' . $row, $report_data['total_profit']); 
    $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
    $row++;

    $row += 3;
    $sheet->setCellValue('A' . $row, 'أرصدة الخزينة الحالية');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    $sheet->setCellValue('A' . $row, 'العملة');
    $sheet->setCellValue('B' . $row, 'الرصيد الحالي');
    $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
    $row++;
    foreach ($treasury_balances as $balance) {
        $sheet->setCellValue('A' . $row, $balance['currency_code']);
        $sheet->setCellValue('B' . $row, $balance['total_balance']);
        $row++;
    }
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    // -------------------------------------------------

    // -------------------------------------------------
    // تبويب 2: العمليات التفصيلية
    // -------------------------------------------------
    $spreadsheet->createSheet();
    $sheetIndex++;
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('العمليات التفصيلية');
    
    $header = ['التاريخ والوقت', 'النوع', 'مبلغ العملة الأجنبية', 'العملة', 'المبلغ بالدينار الليبي', 'سعر الصرف', 'منفذ العملية'];
    $sheet->fromArray($header, NULL, 'A1');
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    $sheet->fromArray($detailed_transactions, NULL, 'A2');
    foreach (range('A', $sheet->getHighestColumn()) as $column) { $sheet->getColumnDimension($column)->setAutoSize(true); }

    // -------------------------------------------------
    // تبويب 3: سجل أسعار الصرف المحدثة 
    // -------------------------------------------------
    $spreadsheet->createSheet();
    $sheetIndex++;
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('سجل أسعار الصرف');
    
    $header = ['من العملة', 'إلى العملة', 'السعر السابق', 'السعر الجديد', 'تاريخ التحديث', 'المحدث بواسطة']; 
    $sheet->fromArray($header, NULL, 'A1');
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    
    $excel_rates_data = array_map(function($rate) {
        return [
            $rate['from_currency'],
            $rate['to_currency'],
            $rate['old_rate'],
            $rate['new_rate'],
            $rate['updated_at'],
            $rate['updated_by']
        ];
    }, $exchange_rates_history);
    
    $sheet->fromArray($excel_rates_data, NULL, 'A2');
    foreach (range('A', $sheet->getHighestColumn()) as $column) { $sheet->getColumnDimension($column)->setAutoSize(true); }
    
    // -------------------------------------------------
    // تبويب 4: العملاء الجدد المضافون
    // -------------------------------------------------
    $spreadsheet->createSheet();
    $sheetIndex++;
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('العملاء الجدد');
    
    $header = ['الاسم الكامل', 'رقم الهاتف', 'تاريخ الإضافة']; 
    $sheet->fromArray($header, NULL, 'A1');
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    
    $excel_clients_data = array_map(function($client) {
        return [
            $client['full_name'],
            $client['phone_number'],
            $client['created_at']
        ];
    }, $added_clients);
    
    $sheet->fromArray($excel_clients_data, NULL, 'A2');
    foreach (range('A', $sheet->getHighestColumn()) as $column) { $sheet->getColumnDimension($column)->setAutoSize(true); }
    
    // -------------------------------------------------
    // تبويب 5: ملخص أرصدة العملاء 
    // -------------------------------------------------
    $spreadsheet->createSheet();
    $sheetIndex++;
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('أرصدة العملاء');
    
    $header = ['اسم العميل', 'العملة', 'الرصيد الحالي']; 
    $sheet->fromArray($header, NULL, 'A1');
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    
    $excel_balances_data = array_map(function($balance) {
        return [
            $balance['full_name'],
            $balance['currency_code'] ?? 'LYD', 
            $balance['current_balance']
        ];
    }, $customer_balances_summary);
    
    $sheet->fromArray($excel_balances_data, NULL, 'A2');
    foreach (range('A', $sheet->getHighestColumn()) as $column) { $sheet->getColumnDimension($column)->setAutoSize(true); }
    
    // -------------------------------------------------
    // تبويب 6: المستخدمون المضافون
    // -------------------------------------------------
    $spreadsheet->createSheet();
    $sheetIndex++;
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle('المستخدمون الجدد');
    
    $header = ['اسم المستخدم', 'الدور', 'تاريخ الإضافة']; 
    $sheet->fromArray($header, NULL, 'A1');
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    
    $excel_users_data = array_map(function($user) {
        return [
            $user['username'],
            $user['user_role'],
            $user['created_at']
        ];
    }, $added_users);
    
    $sheet->fromArray($excel_users_data, NULL, 'A2');
    foreach (range('A', $sheet->getHighestColumn()) as $column) { $sheet->getColumnDimension($column)->setAutoSize(true); }

    // -------------------------------------------------
    // تصدير الملف
    // -------------------------------------------------
    $filename = "Detailed_Report_" . $start_date . "to" . $end_date . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'. $filename .'"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    exit; 
}

// -----------------------------------------------------------------------------------------
// 10. عرض HTML
// -----------------------------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>التقارير الشاملة - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
    <style>
        /* ... (تنسيقات CSS كما هي) ... */
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');
        
        :root {
            --sidebar-bg: #343a40; 
            --sidebar-link-color: #ffffff;
            --sidebar-active-color: #ffc107; 
            --sidebar-hover-bg: #495057;
            --light-bg: #f8f9fa; 
            --primary-color: #007bff;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            padding: 0; 
            text-align: right;
            direction: rtl;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            right: 0; 
            height: 100vh;
            z-index: 1000;
            background: var(--sidebar-bg);
            color: var(--sidebar-link-color);
            transition: all 0.3s;
            overflow-y: auto;
            padding-top: 20px;
        }
        
        #sidebarCollapseMobile {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1050;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
        }

        .sidebar ul a {
            padding: 10px 15px;
            font-size: 1.1em;
            display: block;
            color: var(--sidebar-link-color);
            border-bottom: 1px solid #495057;
            text-decoration: none;
        }

        .sidebar ul a:hover {
            color: var(--sidebar-active-color);
            background: var(--sidebar-hover-bg);
            text-decoration: none;
        }

        .sidebar ul a.active {
            color: var(--sidebar-bg);
            background: var(--sidebar-active-color);
            font-weight: bold;
        }
        
        .sidebar ul a.active-dropdown {
            background: #495057; 
            color: var(--sidebar-active-color);
        }
        
        .sidebar ul ul a {
            font-size: 1em !important;
            padding-right: 30px !important; 
            background: #23272b;
            border-left: 3px solid var(--sidebar-active-color);
        }

        .content-wrapper { 
            transition: all 0.3s;
        }
        .content {
            margin-right: 250px; 
            margin-left: 0; 
            padding: 20px; 
            background-color: var(--light-bg); 
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .report-card { border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,.1); margin-bottom: 20px; }
        .report-card-title { font-size: 1rem; font-weight: bold; } 
        .report-value { font-size: 1.8rem; font-weight: bold; } 
        .table-title { font-size: 1.25rem; margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
        
        @media screen and (max-width: 768px) {
            .sidebar {
                margin-right: -250px; 
            }
            .sidebar.active {
                margin-right: 0;
            }
            .content {
                margin-right: 0 !important; 
                padding: 10px; 
            }
        }
    </style>
</head>
<body>
    <button type="button" id="sidebarCollapseMobile" class="btn btn-info d-block d-md-none">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper"> 
        <div class="content">
            <div class="container-fluid">
                <h1 class="mb-4 text-dark"><i class="fas fa-chart-bar"></i> التقارير الشاملة</h1>
                <p class="lead">استعراض وتحليل الأداء المالي والتشغيلي لـ <?php echo htmlspecialchars($report_data['branch_name']); ?> بين <?php echo htmlspecialchars($start_date); ?> و <?php echo htmlspecialchars($end_date); ?>.</p>
                <hr>

                <form method="GET" action="reports.php" class="mb-4 bg-white p-4 rounded shadow-sm">
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-3 mb-3">
                            <label for="start_date">من تاريخ:</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3 mb-3">
                            <label for="end_date">إلى تاريخ:</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3 mb-3">
                            <label for="branch_id">الفرع:</label>
                            <select id="branch_id" name="branch_id" class="form-control">
                                <option value="all" <?php echo ($selected_branch_id == 'all' ? 'selected' : ''); ?>>جميع الفروع</option>
                                <?php foreach ($branches as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo ($selected_branch_id == $id ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-end mb-3">
                            <button type="submit" class="btn btn-primary btn-block mr-2"><i class="fas fa-filter"></i> تطبيق الفلتر</button>
                            <button type="submit" name="export" value="true" class="btn btn-success btn-block" formtarget="_blank"><i class="fas fa-file-excel"></i> تصدير Excel</button>
                        </div>
                    </div>
                </form>

                <h3 class="mb-3">ملخص العمليات (<?php echo htmlspecialchars($report_data['branch_name']); ?>)</h3>
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card bg-success text-white report-card">
                            <div class="card-body">
                                <div class="report-card-title">إجمالي المبيعات (بيع)</div>
                                <div class="report-value"><i class="fas fa-arrow-up"></i> <?php echo number_format($report_data['sales_total'], 2); ?></div>
                                <p class="card-text small">كمية العملة المباعة</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card bg-danger text-white report-card">
                            <div class="card-body">
                                <div class="report-card-title">إجمالي المشتريات (شراء)</div>
                                <div class="report-value"><i class="fas fa-arrow-down"></i> <?php echo number_format($report_data['purchases_total'], 2); ?></div>
                                <p class="card-text small">كمية العملة المشتراة</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card bg-warning text-dark report-card">
                            <div class="card-body">
                                <div class="report-card-title">إجمالي الخصومات</div>
                                <div class="report-value"><i class="fas fa-minus-circle"></i> <?php echo number_format($report_data['discounts_total'], 2); ?></div>
                                <p class="card-text small">إجمالي الخصم الممنوح</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card bg-primary text-white report-card">
                            <div class="card-body">
                                <div class="report-card-title">صافي الأرباح (بالدينار الليبي)</div>
                                <div class="report-value"><i class="fas fa-dollar-sign"></i> <?php echo number_format($report_data['total_profit'], 2); ?></div>
                                <p class="card-text small">الأرباح المحققة من العمليات</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3 class="mt-4 mb-3">أرصدة الخزينة الحالية (للفرع أو إجمالي الفروع)</h3>
                <div class="row">
                    <?php if (empty($treasury_balances)): ?>
                        <div class="col-12"><div class="alert alert-info">لا توجد أرصدة خزينة مسجلة.</div></div>
                    <?php else: ?>
                        <?php foreach ($treasury_balances as $balance): ?>
                            <div class="col-12 col-sm-6 col-lg-3 mb-3">
                                <div class="card bg-light report-card border-secondary">
                                    <div class="card-body">
                                        <div class="report-card-title text-secondary">الرصيد - (<?php echo htmlspecialchars($balance['currency_code']); ?>)</div>
                                        <div class="report-value text-dark" style="font-size: 1.5rem;"><i class="fas fa-coins"></i> <?php echo number_format($balance['total_balance'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="table-title"><i class="fas fa-exchange-alt"></i> سجل العمليات المفصلة (صرف/فواتير)</div>
                <?php if (empty($detailed_transactions)): ?>
                    <div class="alert alert-warning">لا توجد عمليات مسجلة في الفترة المحددة.</div>
                <?php else: ?>
                    <div class="table-responsive bg-white rounded shadow-sm">
                        <table class="table table-hover table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>التاريخ والوقت</th>
                                    <th>النوع</th>
                                    <th>مبلغ العملة الأجنبية</th>
                                    <th>العملة</th>
                                    <th>المبلغ بالدينار الليبي</th>
                                    <th>سعر الصرف</th>
                                    <th>منفذ العملية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_transactions as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['transaction_date']); ?></td>
                                        <td><span class="badge badge-<?php echo ($t['transaction_type'] == 'SELL' ? 'success' : 'danger'); ?>"><?php echo htmlspecialchars($t['transaction_type']); ?></span></td>
                                        <td><?php echo number_format($t['amount_foreign'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($t['currency_code']); ?></td>
                                        <td><?php echo number_format($t['amount_LYD'], 2); ?></td>
                                        <td><?php echo number_format($t['exchange_rate'], 4); ?></td>
                                        <td><?php echo htmlspecialchars($t['created_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <div class="table-title"><i class="fas fa-tags"></i> سجل أسعار الصرف المحدثة</div>
                <?php if (empty($exchange_rates_history)): ?>
                    <div class="alert alert-warning">لا توجد تحديثات لأسعار الصرف في الفترة المحددة.</div>
                <? else: ?>
                    <div class="table-responsive bg-white rounded shadow-sm">
                        <table class="table table-hover table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>من العملة</th>
                                    <th>إلى العملة</th>
                                    <th>السعر السابق</th>
                                    <th>السعر الجديد</th>
                                    <th>تاريخ التحديث</th>
                                    <th>المحدث بواسطة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exchange_rates_history as $rate): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rate['from_currency']); ?></td>
                                        <td><?php echo htmlspecialchars($rate['to_currency']); ?></td>
                                        <td><?php echo number_format($rate['old_rate'], 4); ?></td>
                                        <td><?php echo number_format($rate['new_rate'], 4); ?></td>
                                        <td><?php echo htmlspecialchars($rate['updated_at']); ?></td>
                                        <td><?php echo htmlspecialchars($rate['updated_by']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-title"><i class="fas fa-user-plus"></i> العملاء الجدد المضافون</div>
                        <?php if (empty($added_clients)): ?>
                            <div class="alert alert-warning">لم يتم إضافة عملاء جدد في الفترة المحددة.</div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded shadow-sm">
                                <table class="table table-hover table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>الاسم الكامل</th>
                                            <th>رقم الهاتف</th>
                                            <th>تاريخ الإضافة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($added_clients as $client): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($client['phone_number']); ?></td>
                                                <td><?php echo htmlspecialchars($client['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-6">
                        <div class="table-title"><i class="fas fa-wallet"></i> ملخص أرصدة العملاء الحالية</div>
                        <?php if (empty($customer_balances_summary)): ?>
                            <div class="alert alert-info">لا توجد أرصدة عملاء حالية (أو جميعها صفر).</div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded shadow-sm">
                                <table class="table table-hover table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>اسم العميل</th>
                                            <th>العملة</th>
                                            <th>الرصيد الحالي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customer_balances_summary as $balance): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($balance['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($balance['currency_code'] ?? 'LYD'); ?></td>
                                                <td><?php echo number_format($balance['current_balance'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-title"><i class="fas fa-users"></i> المستخدمون الجدد المضافون (للفروع)</div>
                <?php if (empty($added_users)): ?>
                    <div class="alert alert-warning">لم يتم إضافة مستخدمين جدد في الفترة المحددة.</div>
                <?php else: ?>
                    <div class="table-responsive bg-white rounded shadow-sm">
                        <table class="table table-hover table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>اسم المستخدم</th>
                                    <th>الدور</th>
                                    <th>تاريخ الإضافة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($added_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['user_role']); ?></td> 
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#sidebarCollapseMobile').on('click', function() {
            $('.sidebar').toggleClass('active');
        });
    });
    </script>

</body>
</html>

<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}