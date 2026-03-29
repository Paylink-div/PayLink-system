<?php
// send_daily_report.php
// دالة لتوليد محتوى تقرير العمليات اليومية لفرع معين كـ HTML.

require_once 'db_connect.php'; 

/**
 * وظيفة لتجميع وتوليد تقرير الإغلاق اليومي للفرع.
 *
 * @param mysqli $conn اتصال قاعدة البيانات
 * @param int $branch_id رقم الفرع الذي قام بالإغلاق
 * @param string $branch_name اسم الفرع
 * @param int $user_id رقم مدير الفرع الذي قام بالإرسال
 * @return string محتوى التقرير بصيغة HTML أو رسالة خطأ.
 */
function generate_daily_branch_report_html($conn, $branch_id, $branch_name, $user_id) {
    
    // 1. تحديد نطاق التاريخ (اليوم الذي تم إغلاقه)
    $report_date = date('Y-m-d'); 
    
    // 2. جلب العمليات التي تمت في الفرع اليوم (من جدول treasury_transactions)
    $transaction_data = [];
    $sql_transactions = "SELECT 
        t.transaction_type, 
        SUM(t.profit_loss) AS total_profit_loss,
        COUNT(t.id) AS transaction_count
        FROM treasury_transactions t
        WHERE t.branch_id = ? AND DATE(t.transaction_date) = ?
        GROUP BY t.transaction_type";
        
    $stmt_trans = $conn->prepare($sql_transactions);
    
    if (!$stmt_trans) {
        return "<p style='color: red;'>خطأ في تجهيز استعلام العمليات: " . htmlspecialchars($conn->error) . "</p>";
    }
    
    $stmt_trans->bind_param("is", $branch_id, $report_date);
    $stmt_trans->execute();
    $result_trans = $stmt_trans->get_result();
    while ($row = $result_trans->fetch_assoc()) {
        $transaction_data[$row['transaction_type']] = $row;
    }
    $stmt_trans->close();
    
    // 3. جلب أرصدة الخزينة الحالية للفرع (من جدول treasury_balances)
    $balances_html = "";
    // 🛑 تم التعديل ليستخدم 'current_balance' من جدول treasury_balances
    $sql_balances = "SELECT 
        tb.current_balance AS balance, 
        c.currency_code 
        FROM treasury_balances tb
        JOIN currencies c ON tb.currency_id = c.id
        WHERE tb.branch_id = ?";
        
    $stmt_bal = $conn->prepare($sql_balances);
    
    if (!$stmt_bal) {
         $balances_html = "<p style='color: orange;'>⚠ فشل جلب أرصدة العملات بالتفصيل: " . htmlspecialchars($conn->error) . "</p>";
    } else {
        
        $stmt_bal->bind_param("i", $branch_id);
        $stmt_bal->execute();
        $result_bal = $stmt_bal->get_result();
        
        if ($result_bal->num_rows > 0) {
            $balances_html .= "<table style='width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #ddd;'>";
            $balances_html .= "<tr style='background-color: #343a40; color: white;'><th style='padding: 8px; border: 1px solid #ddd;'>العملة</th><th style='padding: 8px; border: 1px solid #ddd;'>الرصيد الحالي</th></tr>";
            while ($row = $result_bal->fetch_assoc()) {
                // نستخدم اسم المستعار 'balance' الذي وضعناه في الاستعلام
                $balances_html .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['currency_code']) . "</td><td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($row['balance'], 4) . "</td></tr>";
            }
            $balances_html .= "</table>";
        } else {
             $balances_html = "<p style='color: #FF5722;'>لا توجد أرصدة مسجلة لهذا الفرع في الوقت الحالي.</p>";
        }
        $stmt_bal->close();
    }


    // 4. تنسيق محتوى التقرير (HTML)
    $total_profit = 0;
    $profit_transaction_types = ['SELL', 'BUY', 'EXCHANGE']; 
    
    foreach ($transaction_data as $type => $data) {
        if (in_array($type, $profit_transaction_types)) {
            $total_profit += $data['total_profit_loss'];
        }
    }

    $manager_name_q = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $manager_name_q->bind_param("i", $user_id);
    $manager_name_q->execute();
    $manager_name = $manager_name_q->get_result()->fetch_assoc()['full_name'] ?? 'غير معروف';
    $manager_name_q->close();


    $report_html = "
    <div style='font-family: Arial, sans-serif; direction: rtl; text-align: right; border: 1px solid #0D47A1; padding: 20px; border-radius: 10px; background-color: #f9f9f9;'>
        <h2 style='color: #0D47A1; border-bottom: 2px solid #0D47A1; padding-bottom: 10px;'>تقرير الإغلاق اليومي لفرع PayLink</h2>
        
        <p><strong>تاريخ الإغلاق المُغطى:</strong> {$report_date}</p>
        <p><strong>الفرع:</strong> " . htmlspecialchars($branch_name) . " (ID: {$branch_id})</p>
        <p><strong>مدير الفرع المُنفذ:</strong> " . htmlspecialchars($manager_name) . "</p>
        <hr style='border-top: 1px dashed #ccc;'>

        <h3 style='color: #007bff;'>ملخص العمليات اليومية</h3>
        <ul style='list-style: none; padding-right: 0;'>
            ";
            
            foreach ($transaction_data as $type => $data) {
                $type_name = htmlspecialchars($type);
                $count = $data['transaction_count'];
                $profit = number_format($data['total_profit_loss'], 4);
                $profit_color = $data['total_profit_loss'] >= 0 ? '#28a745' : '#dc3545';
                
                $report_html .= "<li style='margin-bottom: 5px;'><strong>نوع العملية: {$type_name}</strong> (عدد: {$count}) - الربح/الخسارة: <span style='color: {$profit_color};'>{$profit}</span></li>";
            }
            
            $total_profit_color = $total_profit >= 0 ? '#1f7f3f' : '#dc3545';

    $report_html .= "
            <li style='margin-top: 15px; margin-bottom: 10px; border-top: 1px dotted #ccc; padding-top: 10px;'>
                <strong>صافي الربح الإجمالي اليومي (العمليات المربحة فقط):</strong> 
                <span style='color: {$total_profit_color}; font-weight: bold; font-size: 1.1em;'>" . number_format($total_profit, 4) . "</span>
            </li>
        </ul>
        
        <h3 style='color: #28a745; margin-top: 20px;'>أرصدة الخزينة الحالية للفرع</h3>
        {$balances_html}
        
        <p style='margin-top: 30px; font-size: 0.9em; color: #6c757d; border-top: 1px dashed #ccc; padding-top: 10px;'>تم توليد هذا التقرير آلياً بواسطة نظام PayLink عند الإغلاق اليومي.</p>
    </div>";
    
    return $report_html;
}

// ----------------------------------------------------
// 🛑 هذا الكود يمنع ظهور الصفحة البيضاء عند فتحه مباشرة
// ----------------------------------------------------
if (basename($_SERVER['PHP_SELF']) == 'send_daily_report.php') {
    exit; 
}
?>