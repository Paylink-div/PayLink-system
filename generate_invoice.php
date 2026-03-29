<?php

session_start();

// يجب التأكد من وجود هذه الملفات
include 'db_connect.php'; 

// 🛑🛑 تم حذف: require_once 'acl_functions.php'; 🛑🛑


// =================================================================
// 🚨 التحقق الوحيد المتبقي: التحقق من تسجيل الدخول فقط
// =================================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🛑🛑 تم حذف: require_permission($conn, 'CAN_PRINT_INVOICES'); 🛑🛑


// -----------------------------------------------------------------------------------------
// 1. جلب بيانات العملية
// -----------------------------------------------------------------------------------------

$trx_id = $conn->real_escape_string($_GET['trx_id'] ?? 0);

if ($trx_id == 0) {
    die("خطأ: لم يتم تحديد معرف العملية (Transaction ID).");
}


// استعلام لجلب تفاصيل العملية، العميل، والموظف الذي أجرى العملية
// ملاحظة: تم تعديل الاستعلام لجلب رقم العميل بدلاً من client_id_number لأنه قد يسبب خطأ
$query = "
    SELECT 
        t.*, 
        c.full_name AS client_name,
        c.phone_number AS client_phone,
        c.id_number AS client_id_number,
        u.full_name AS user_name,
        u.email AS user_email
    FROM 
        client_transactions t
    JOIN 
        clients c ON t.client_id = c.id
    JOIN 
        users u ON t.user_id = u.id
    WHERE 
        t.id = '$trx_id' AND t.is_deleted = 0
";


$result = $conn->query($query);


if ($result->num_rows === 0) {
    die("خطأ: العملية المالية غير موجودة أو محذوفة.");
}


$transaction = $result->fetch_assoc();
$conn->close();


// تحويل نوع العملية للعرض
$transaction_type_ar = ($transaction['transaction_type'] == 'DEPOSIT') ? 'إيداع (دائن)' : 'سحب (مدين)';


// 🛑 تم إلغاء خاصية النسختين

?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال <?php echo $transaction['id']; ?></title>
    <style>
        /* تعريف الألوان للمظهر الجذاب على الشاشة */
        :root {
            --main-color: #17a2b8; /* أزرق فيروزي */
            --secondary-color: #6c757d; /* رمادي للعناصر الثانوية */
            --light-bg: #e0f7fa; /* خلفية فاتحة للأقسام */
            --dark-text: #343a40; /* لون نص داكن */
            --page-bg: #f4f6f9; /* خلفية الصفحة */
        }
        
        /* ------------------------------------------------ */
        /* تصميم الإيصال للعرض على الشاشة (محاذاة مركزية وألوان) */
        /* ------------------------------------------------ */
        body {
            font-family: 'Arial', sans-serif; 
            background-color: var(--page-bg); 
            margin: 0;
            padding: 20px;
            display: flex; 
            flex-direction: column;
            align-items: center; /* محاذاة مركزية أفقية */
            min-height: 100vh;
            color: var(--dark-text);
        }
        .invoice-wrapper {
            width: 80mm; /* 📏 العرض الثابت للطابعة الحرارية */
            background: #fff; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            border-radius: 8px; 
            /* 💡 التعديلات لظهور الفاتورة كاملة على الشاشة (قابلة للتمرير) */
            max-height: 90vh; 
            overflow-y: auto; 
            margin-bottom: 20px; 
        }
        .invoice-container {
            width: 80mm; 
            padding: 0 5mm; 
            font-size: 10pt;
        }
        .invoice-header {
            text-align: center;
            padding: 10px 0;
            background-color: var(--main-color); 
            color: #fff; 
            margin-bottom: 10px;
        }
        .invoice-header h2 {
            font-size: 16pt;
            margin: 5px 0;
        }
        .invoice-header small {
            font-size: 9pt;
            display: block;
            margin-top: 5px;
        }
        /* تصميم البيانات الرأسية (Key: Value) */
        .details-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            line-height: 1.4;
            padding: 0 2mm; 
        }
        .details-row strong {
            display: block;
            width: 45%;
            text-align: right;
            font-weight: normal;
            color: #555;
        }
        .details-row span {
            display: block;
            width: 55%;
            text-align: left;
            font-weight: bold;
            color: var(--dark-text);
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            background-color: var(--light-bg); 
            color: var(--dark-text);
            border-top: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            margin: 15px 0 8px 0;
            padding: 5px 0;
            font-size: 11pt;
        }
        .summary-box {
            border: 1px solid var(--main-color);
            background-color: var(--light-bg);
            padding: 10px;
            margin-top: 15px;
            text-align: center;
            border-radius: 5px;
        }
        .amount-display {
            font-size: 18pt;
            font-weight: bold;
            margin: 5px 0;
            padding: 5px 0;
            color: #28a745; /* لون أخضر للمبلغ */
        }
        .footer-signatures {
            margin-top: 25px;
            padding-bottom: 15px;
            text-align: center;
            font-size: 9pt;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 40%; 
            margin: 0 auto 5px auto; 
        }
        .stamp-box {
            height: 50px; 
            border: 1px dashed #999;
            margin: 15px auto 10px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70%; 
            font-size: 8pt;
            color: #666;
        }
        .copy-info {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 10px;
            color: var(--main-color);
        }
        /* تنسيق الأزرار (No-Print Controls) */
        .controls-container {
            display: flex;
            gap: 10px; /* المسافة بين الأزرار */
            justify-content: center; 
            align-items: center;
            padding: 15px;
            width: 80mm; 
            margin-bottom: 15px;
        }
        .action-button {
            flex-grow: 1; 
            padding: 10px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .print-btn {
            background-color: var(--main-color);
            color: white;
        }
        .print-btn:hover {
            background-color: #138496;
        }
        .back-btn {
            background-color: var(--secondary-color);
            color: white;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }

        /* ------------------------------------------------ */
        /* تنسيق الطباعة (Print Styles) - 80mm (أبيض وأسود، نسخة واحدة) */
        /* ------------------------------------------------ */
        @media print {
            body {
                width: 80mm; /* 📐 تحديد العرض للطابعة */
                margin: 0;
                padding: 0;
                background-color: #fff !important; 
                display: block; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                color: #000; 
            }
            .invoice-wrapper {
                width: 80mm;
                box-shadow: none !important;
                border-radius: 0 !important;
                margin-bottom: 0 !important;
                background: none !important;
                /* إلغاء التمرير لعرض الطباعة */
                max-height: none !important; 
                overflow-y: visible !important;
            }
            .invoice-container {
                padding: 0 2mm; 
                color: #000;
            }
            /* إخفاء الأزرار وعناصر التحكم عند الطباعة */
            .no-print, .controls-container {
                display: none !important;
            }
            /* إجبار كل العناصر الملونة على أن تكون سوداء/بيضاء عند الطباعة */
            .invoice-header, .section-title, .summary-box {
                background-color: #fff !important; 
                color: #000 !important;
                border-color: #000 !important;
            }
            .details-row strong, .details-row span, .amount-display, .copy-info {
                color: #000 !important; 
            }
        }
    </style>
</head>
<body>

    <div class="controls-container no-print">
        
        <button onclick="window.print()" class="action-button print-btn">
            طباعة الإيصال 
        </button>

        <a href="client_details.php?id=<?php echo $transaction['client_id']; ?>" class="action-button back-btn">
             العودة للسجل المالي
        </a>
    </div>

    <div class="invoice-wrapper">
        <div class="invoice-container">
            
            <div class="invoice-header">
                <h2>PayLink</h2>
                <small>إيصال عملية مالية</small>
            </div>

            <div class="section-title">بيانات العملية</div>
            <div class="details-row">
                <strong>رقم الإيصال:</strong>
                <span>#TRX-<?php echo $transaction['id']; ?></span>
            </div>
            <div class="details-row">
                <strong>تاريخ ووقت:</strong>
                <span><?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?></span>
            </div>
            <div class="details-row">
                <strong>الموظف:</strong>
                <span><?php echo htmlspecialchars($transaction['user_name']); ?></span>
            </div>
            
            <div class="section-title">بيانات العميل</div>
            <div class="details-row">
                <strong>الاسم الكامل:</strong>
                <span><?php echo htmlspecialchars($transaction['client_name']); ?></span>
            </div>
            <div class="details-row">
                <strong>رقم الهاتف:</strong>
                <span><?php echo htmlspecialchars($transaction['client_phone']); ?></span>
            </div>
            <div class="details-row">
                <strong>الرقم الوطني:</strong>
                <span><?php echo htmlspecialchars($transaction['client_id_number'] ?? 'غير متوفر'); ?></span>
            </div>

            <div class="section-title">تفاصيل الحركة</div>
            <div class="details-row">
                <strong>نوع العملية:</strong>
                <span><?php echo $transaction_type_ar; ?></span>
            </div>
            <div class="details-row">
                <strong>الملاحظات:</strong>
                <span><?php echo htmlspecialchars($transaction['notes'] ?? 'لا يوجد'); ?></span>
            </div>
            
            <div class="summary-box">
                <strong>المبلغ المدفوع/المسحوب:</strong>
                <div class="amount-display">
                    <?php echo number_format($transaction['amount'], 2) . ' ' . htmlspecialchars($transaction['currency_code']); ?>
                </div>
            </div>
            
            <div class="section-title">ملخص الأرصدة</div>
            <div class="details-row">
                <strong>الرصيد قبل العملية:</strong>
                <span><?php echo number_format($transaction['balance_before'], 2) . ' ' . $transaction['currency_code']; ?></span>
            </div>
            <div class="details-row">
                <strong>الرصيد الحالي (بعد):</strong>
                <span style="font-weight: bold; color: #28a745;">
                    <?php echo number_format($transaction['balance_after'], 2) . ' ' . $transaction['currency_code']; ?>
                </span>
            </div>
            
            <div class="footer-signatures">
                <div class="stamp-box">
                    مكان الختم الرسمي للشركة
                </div>

                <div class="details-row" style="margin-top: 15px;">
                    <strong style="width: 50%; text-align: center;">توقيع العميل</strong>
                    <strong style="width: 50%; text-align: center;">توقيع الموظف</strong>
                </div>
                <div class="details-row">
                    <div class="signature-line" style="float: right;"></div>
                    <div class="signature-line" style="float: left;"></div>
                </div>

                <div class="details-row" style="margin-top: 15px; font-size: 8pt; text-align: center;">
                    <small> لا نتحمل مسؤولية أي خطأ بعد 24 ساعه</small>
                </div>
                
                <div class="text-center">
                    <p class="copy-info">شكرا لكم علي تعاملكم معنا</p>
                </div>
            </div>

        </div> 
    </div>

    </body>
</html>