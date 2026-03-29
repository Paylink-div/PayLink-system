<?php

// functions.php

// 1. تضمين ملف الاتصال بقاعدة البيانات الخاص بك
include 'db_connect.php'; 


/**
 * دالة لإنشاء رقم تسلسلي فريد
 * @return string الرقم التسلسلي الجديد
 */
function generate_serial_number() {
    // تنسيق YYYYMMDDHHMMSSSSS - يضمن التفرد والتسلسل الزمني
    return date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}


/**
 * دالة لجلب أسعار الصرف من قاعدة البيانات
 * @param mysqli $conn كائن الاتصال بقاعدة البيانات
 * @return array مصفوفة بأسعار الصرف مفهرسة برمز العملة
 */
function get_exchange_rates($conn) {
    $rates = [];
    // يجب أن يحتوي هذا الجدول على حقول: currency_code, buy_rate, sell_rate, commission_percentage
    $sql = "SELECT currency_code, currency_name_ar, buy_rate, sell_rate, commission_percentage FROM exchange_rates";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rates[$row['currency_code']] = $row;
        }
    }
    return $rates;
}


/**
 * دالة لجلب ID العملة من جدول العملات باستخدام كود العملة
 * يجب أن يكون لديك جدول currencies يحوي: id و currency_code
 * @param mysqli $conn كائن الاتصال بقاعدة البيانات
 * @param string $code رمز العملة (مثال: USD)
 * @return int|null معرف العملة أو Null إذا لم يتم العثور عليها
 */
function get_currency_id_by_code($conn, $code) {
    $stmt = $conn->prepare("SELECT id FROM currencies WHERE currency_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (int)$row['id'];
    }
    $stmt->close();
    return NULL; 
}


/**
 * دالة لحساب وجلب الأرصدة الحالية من جميع المصادر (transactions و treasury_transactions)
 * * @param mysqli $conn كائن الاتصال بقاعدة البيانات
 * @return array مصفوفة بالأرصدة مفهرسة برمز العملة (بما في ذلك LYD)
 */
function get_current_balances($conn) {
    global $exchange_rates;
    
    $balances = [];
    
    // 1. حساب رصيد الدينار الليبي (LYD) من جدول transactions
    // 'بيع': الصراف يستلم LYD (+) | 'شراء': الصراف يدفع LYD (-)
    $sql_lyd_transactions = "SELECT 
        SUM(CASE WHEN transaction_type = 'بيع' THEN net_amount ELSE -net_amount END) AS total_lyd_balance
    FROM transactions";
    
    $result_lyd_t = $conn->query($sql_lyd_transactions);
    $balances['LYD'] = 0.00;
    
    if ($result_lyd_t) {
        $row_lyd_t = $result_lyd_t->fetch_assoc();
        if ($row_lyd_t && $row_lyd_t['total_lyd_balance'] !== NULL) {
             $balances['LYD'] = (float) $row_lyd_t['total_lyd_balance'];
        }
    }


    // 2. حساب أرصدة العملات الأجنبية من جدول transactions
    // 'شراء': الصراف يشتري (تدخل عملة أجنبية) (+) | 'بيع': الصراف يبيع (تخرج عملة أجنبية) (-)
    $sql_foreign_transactions = "SELECT 
        c.currency_code,
        SUM(CASE WHEN t.transaction_type = 'شراء' THEN t.amount_foreign ELSE -t.amount_foreign END) AS total_foreign_balance
    FROM transactions t
    JOIN currencies c ON t.from_currency_id = c.id
    GROUP BY c.currency_code";
                    
    $result_foreign_t = $conn->query($sql_foreign_transactions);

    if ($result_foreign_t) {
        while ($row = $result_foreign_t->fetch_assoc()) {
            $balances[$row['currency_code']] = ($balances[$row['currency_code']] ?? 0.00) + (float) $row['total_foreign_balance'];
        }
    }
    
    // --- 🆕 جزء دمج أرصدة الخزينة (Treasury Transactions) ---
    
    // 3. دمج حركات الخزينة (الرصيد الافتتاحي، التعديلات، المصروفات، الإيداعات)
    // نستخدم SUM(amount_in - amount_out) لتحديد صافي الحركة لكل عملة
    $sql_treasury = "SELECT 
        currency_in_code,
        SUM(amount_in) AS total_in,
        SUM(amount_out) AS total_out
    FROM treasury_transactions
    WHERE currency_in_code IS NOT NULL
    GROUP BY currency_in_code";
                    
    $result_treasury = $conn->query($sql_treasury);

    if ($result_treasury) {
        while ($row_t = $result_treasury->fetch_assoc()) {
            $code = $row_t['currency_in_code'];
            $net_treasury_amount = (float)$row_t['total_in'] - (float)$row_t['total_out'];
            
            // إضافة صافي رصيد الخزينة إلى الرصيد الحالي (سواء كان LYD أو أجنبية)
            $balances[$code] = ($balances[$code] ?? 0.00) + $net_treasury_amount;
        }
    }
    // --- 🔚 نهاية الدمج ---

    
    // 4. التأكد من وجود جميع العملات المعرفة في أسعار الصرف ضمن مصفوفة الأرصدة (وإلا تكون 0)
    foreach ($exchange_rates as $code => $rate) {
        if (!isset($balances[$code])) {
            $balances[$code] = 0.00;
        }
    }
    // التأكد من وجود LYD
    if (!isset($balances['LYD'])) {
         $balances['LYD'] = 0.00;
    }

    return $balances;
}


// جلب الأسعار بشكل عام لتكون متاحة للصفحات التي تتضمن functions.php
$exchange_rates = get_exchange_rates($conn);


// دالة وهمية للتحقق من تسجيل الدخول (يجب أن يتم تطويرها لاحقاً)
function check_login() {
    // هنا يجب أن يتم وضع منطق التحقق من جلسة المستخدم
    // session_start();
    // if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    //     header("Location: login.php");
    //     exit();
    // }
}

?>