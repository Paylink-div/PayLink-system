<?php
// ملف: receipt.php (النسخة النهائية: إيصال HTML للطباعة المباشرة مع QR والتوثيق)

// 🚨 مرحلة الاتصال والجلسة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. تضمين ملف الاتصال بقاعدة البيانات
require_once 'db_connect.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🚨 مرحلة معالجة المعاملة وجلب البيانات الإضافية
if (isset($_GET['transaction_id'])) {
    $transaction_id = intval($_GET['transaction_id']);
    
    // *القراءة من الرابط: إضافة بيانات التوثيق*
    $client_id_number = isset($_GET['id_number']) ? htmlspecialchars($_GET['id_number']) : 'غير متاح (فقدت)';
    $client_phone_number = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : 'غير متاح (فقدت)';
    
    // 1. بناء رابط الإيصال الكامل لرمز QR
    // يتم استخدام الرابط الذي دخل إليه المستخدم حاليًا.
    $full_receipt_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // 2. بناء رابط QR Code باستخدام خدمة خارجية
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($full_receipt_url);
    
    // استعلام لجلب تفاصيل العملية 
    $sql = "SELECT 
                t.*, 
                c.full_name AS client_name,
                u.full_name AS teller_name,
                fc.currency_code AS from_code, fc.currency_name_ar AS from_name,
                tc.currency_code AS to_code, tc.currency_name_ar AS to_name,
                b.name AS branch_name
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN currencies fc ON t.from_currency_id = fc.id
            LEFT JOIN currencies tc ON t.to_currency_id = tc.id
            LEFT JOIN branches b ON t.branch_id = b.id
            WHERE t.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $transaction = $result->fetch_assoc();
        
        // منطق تحديد المبلغ المعطى والمستلم
        if ($transaction['transaction_type'] == 'SELL') {
            $amount_received_customer = $transaction['amount_sold']; 
            $currency_received_customer = $transaction['to_code']; 
            $amount_paid_by_customer = $transaction['amount_received'];
            $currency_paid_by_customer = $transaction['from_code']; 
            $type_ar = 'بيع عملة (من الخزينة)';
        } else { // BUY
            $amount_received_customer = $transaction['amount_received']; 
            $currency_received_customer = $transaction['to_code']; 
            $amount_paid_by_customer = $transaction['amount_sold'];
            $currency_paid_by_customer = $transaction['from_code']; 
            $type_ar = 'شراء عملة (للخزينة)';
        }
        
        $transaction_date_formatted = date("Y-m-d H:i:s", strtotime($transaction['transaction_date']));
        $rate_used_formatted = number_format($transaction['rate_used'], 6);
        $profit_loss_formatted = number_format($transaction['profit_loss'], 4);
        
        $client_name = htmlspecialchars($transaction['client_name']);
        $teller_name = htmlspecialchars($transaction['teller_name']);
        $branch_name = htmlspecialchars($transaction['branch_name'] ?? 'غير محدد');
        $discount_formatted = number_format($transaction['discount_amount'], 4);

    } else {
        die("خطأ: لم يتم العثور على المعاملة.");
    }

    $stmt->close();
    $conn->close(); // إغلاق الاتصال بعد جلب البيانات

} else {
    die("خطأ: لم يتم تحديد رقم المعاملة.");
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال معاملة رقم: <?php echo $transaction_id; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { font-family: 'Tahoma', sans-serif; padding: 20px; background-color: #f8f9fa; }
        .receipt-container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #fff; 
            border: 1px solid #ccc; 
            padding: 30px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header, .footer { text-align: center; margin-bottom: 20px; }
        .details table { width: 100%; margin-top: 10px; }
        .details th, .details td { padding: 8px; border-bottom: 1px solid #eee; text-align: right; }
        .details th { background-color: #f9f9f9; font-weight: bold; width: 40%; }
        .total-row td { font-size: 1.2em; font-weight: bold; color: #007bff; }
        .qr-section { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px dashed #ccc; }
        .qr-section img { border: 1px solid #ddd; padding: 5px; }
        
        /* تنسيقات الطباعة (إخفاء العناصر غير الضرورية) */
        @media print {
            body { background-color: #fff; }
            .receipt-container { border: none; box-shadow: none; max-width: 100%; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header">
            <h3><?php echo $branch_name; ?></h3>
            <h1><i class="fas fa-receipt"></i> إيصال عملية صرف</h1>
            <p><strong>تاريخ ووقت المعاملة:</strong> <?php echo $transaction_date_formatted; ?></p>
        </div>

        <div class="details">
            <h5 class="text-primary">بيانات المعاملة</h5>
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <th>رقم الإيصال (ID)</th>
                        <td>INV-<?php echo $transaction_id; ?></td>
                    </tr>
                    <tr>
                        <th>نوع العملية</th>
                        <td><?php echo $type_ar; ?></td>
                    </tr>
                    <tr>
                        <th>اسم العميل</th>
                        <td><?php echo $client_name; ?></td>
                    </tr>
                    <tr>
                        <th>رقم الهوية/جواز السفر</th>
                        <td><?php echo $client_id_number; ?></td>
                    </tr>
                    <tr>
                        <th>رقم الهاتف</th>
                        <td><?php echo $client_phone_number; ?></td>
                    </tr>
                </tbody>
            </table>

            <h5 class="text-success mt-4">ملخص العملية</h5>
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <th>المبلغ الذي دفعه العميل</th>
                        <td><?php echo number_format($amount_paid_by_customer, 4); ?> <span class="badge badge-warning"><?php echo $currency_paid_by_customer; ?></span></td>
                    </tr>
                    <tr class="table-info">
                        <th>المبلغ الذي استلمه العميل (النهائي)</th>
                        <td class="total-row"><?php echo number_format($amount_received_customer, 4); ?> <span class="badge badge-primary"><?php echo $currency_received_customer; ?></span></td>
                    </tr>
                    <tr>
                        <th>سعر الصرف المستخدم</th>
                        <td><?php echo $rate_used_formatted; ?></td>
                    </tr>
                    <tr>
                        <th>قيمة الخصم</th>
                        <td><?php echo $discount_formatted; ?> <span class="text-muted"><?php echo $currency_received_customer; ?></span></td>
                    </tr>
                    <tr>
                        <th>الموظف (الصراف)</th>
                        <td><?php echo $teller_name; ?></td>
                    </tr>
                    <tr>
                        <th>الربح/الخسارة للفرع</th>
                        <td class="<?php echo $transaction['profit_loss'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $profit_loss_formatted; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="qr-section">
            <h5>رمز التحقق والتوثيق</h5>
            <img src="<?php echo $qr_code_url; ?>" alt="QR Code" style="width: 150px; height: 150px;">
            <p class="text-muted mt-2">امسح الكود ضوئياً للتحقق من تفاصيل المعاملة عبر الإنترنت.</p>
        </div>
        
        <div class="footer mt-4">
            <p class="text-muted">شكرًا لاختياركم خدماتنا.</p>
        </div>
        
        <div class="text-center mt-3">
            <button class="btn btn-success btn-print" onclick="window.print()"><i class="fas fa-print"></i> طباعة الإيصال</button>
        </div>

    </div>

</body>
</html>