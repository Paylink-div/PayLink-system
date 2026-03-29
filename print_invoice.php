<?php
// print_invoice.php - النسخة النهائية المحدثة لربط اسم الشركة ديناميكياً

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 
include 'functions.php'; 

// التحقق من الجلسة
if (!isset($_SESSION['user_id'])) {
    die("خطأ أمني: يرجى تسجيل الدخول أولاً.");
}

$serial_number = $conn->real_escape_string($_GET['serial'] ?? '');
if (empty($serial_number)) die("خطأ: رقم الفاتورة غير محدد.");

// الاستعلام لجلب بيانات العملية
$sql = "SELECT t.*, c.currency_name_ar, c.currency_code
        FROM transactions t
        LEFT JOIN currencies c ON t.from_currency_id = c.id
        WHERE t.serial_number = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $serial_number);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) die("خطأ: الفاتورة غير موجودة.");

// --- التعديل: جلب اسم الشركة من الثابت المعرف في db_connect.php ---
$company_display_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'نظام PayLink للمحاسبة';

$currency_display = $transaction['currency_name_ar'] ? $transaction['currency_name_ar'] : $transaction['currency_code'];
$type_label = ($transaction['transaction_type'] == 'بيع') ? "المبلغ المباع" : "المبلغ المشترى";
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة #<?php echo $transaction['serial_number']; ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; direction: rtl; background: #f4f4f4; }
        .invoice-box { background: #fff; margin: auto; padding: 15px; border: 1px solid #eee; }
        .header { text-align: center; border-bottom: 2px solid #333; margin-bottom: 10px; padding-bottom: 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .details-table td { padding: 8px; border-bottom: 1px solid #eee; font-size: 14px; }
        .net-amount { font-size: 20px; font-weight: bold; color: #fff; text-align: center; padding: 12px; background: #27ae60; border-radius: 5px; margin: 15px 0; }
        .signatures { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; font-size: 12px; }
        .sig-block { width: 30%; border-top: 1px dashed #000; padding-top: 5px; }
        .no-print { text-align: center; padding: 20px; background: #fff; border-bottom: 1px solid #ddd; }
        @media screen and (min-width: 768px) { .invoice-box { width: 148mm; margin-top: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); } }
        @media screen and (max-width: 767px) { .invoice-box { width: 100%; max-width: 80mm; padding: 5px; } body { background: #fff; } }
        @media print { .no-print { display: none; } body { background: #fff; } .invoice-box { width: 100%; border: none; margin: 0; padding: 0; } @page { size: auto; margin: 5mm; } }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding:12px 30px; background:#007bff; color:#fff; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">طباعة الفاتورة</button>
    <a href="exchange.php" style="padding:12px 30px; background:#6c757d; color:#fff; text-decoration:none; border-radius:5px; margin-right:10px; display:inline-block;">رجوع</a>
</div>

<div class="invoice-box">
    <div class="header">
        <h2 style="margin:0;"><?php echo htmlspecialchars($company_display_name); ?></h2>
        <p style="margin:5px 0; font-size:14px;">إيصال صرف عملات</p>
    </div>

    <table class="details-table">
        <tr>
            <td><strong>رقم الوصل:</strong></td>
            <td><?php echo $transaction['serial_number']; ?></td>
        </tr>
        <tr>
            <td><strong>التاريخ:</strong></td>
            <td><?php echo date('Y/m/d H:i', strtotime($transaction['transaction_date'])); ?></td>
        </tr>
        <tr>
            <td><strong>العميل:</strong></td>
            <td><?php echo htmlspecialchars($transaction['client_name']); ?></td>
        </tr>
    </table>

    <table class="details-table" style="background: #fdfdfd;">
        <tr>
            <td><strong>العملة:</strong></td>
            <td><?php echo $currency_display; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo $type_label; ?>:</strong></td>
            <td style="font-size:16px;"><strong><?php echo number_format($transaction['amount_foreign'], 2); ?></strong></td>
        </tr>
        <tr>
            <td><strong>سعر الصرف:</strong></td>
            <td><?php echo number_format($transaction['rate_used'], 4); ?></td>
        </tr>
    </table>

    <div class="net-amount">
        الصافي: <?php echo number_format($transaction['net_amount'], 2); ?> د.ل
    </div>

    <div class="signatures">
        <div class="sig-block">توقيع الموظف</div>
        <div style="width:30%;">الختم</div>
        <div class="sig-block">توقيع العميل</div>
    </div>

    <div style="text-align:center; margin-top:20px; font-size:11px; color:#555; border-top:1px solid #eee; padding-top:10px;">
        الموظف: <?php echo $_SESSION['full_name'] ?? '---'; ?> | الدفع: <?php echo $transaction['payment_method']; ?>
        <br>شكراً لتعاملكم معنا
    </div>
</div>

</body>
</html>