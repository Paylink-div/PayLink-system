<?php
// ai_db_functions.php

/**
 * *1. تضمين ملف الاتصال بقاعدة بيانات MySQL*
 * يجب تغيير 'db_connection.php' ليتطابق مع اسم ملف الاتصال الخاص بمنظومتك.
 * هذا الملف يجب أن يقوم بإنشاء متغير الاتصال $db
 */
include 'db_connect.php'; 

function get_financial_summary() {
    
    // *2. الإعلان عن متغير الاتصال كنطاق عام*
    global $db; 

    // تحقق من وجود الاتصال قبل التنفيذ
    if (!$db) {
         return "خطأ فادح: لم يتم العثور على اتصال بقاعدة البيانات. تأكد من 'include' لملف الاتصال.";
    }
    
    // ***************************************************************
    // 3. استعلام الأداء المالي الإجمالي (آخر 90 يوماً):
    // يجب التأكد من صحة أسماء الأعمدة: profit_amount و transaction_date
    // ***************************************************************
    $profit_query = "SELECT 
                        SUM(t.profit_amount) AS total_profit, 
                        COUNT(t.id) AS total_txns 
                     FROM transactions t
                     WHERE t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
    
    
    $result = $db->query($profit_query);
    if (!$result) {
        return "خطأ في استعلام الأداء المالي: " . $db->error;
    }
    $data = $result->fetch_assoc();


    // ***************************************************************
    // 4. استعلام أداء الفروع (أعلى 3 فروع ربحاً):
    // يجب التأكد من صحة عمود الربط: t.branch_id
    // ***************************************************************
    $branch_query = "SELECT 
                        b.branch_name, 
                        SUM(t.profit_amount) as total_profit_branch 
                     FROM transactions t
                     JOIN branches b ON t.branch_id = b.id  
                     GROUP BY b.branch_name 
                     ORDER BY total_profit_branch DESC 
                     LIMIT 3";
    
    $branch_results = $db->query($branch_query)->fetch_all(MYSQLI_ASSOC);

    // 5. تجميع البيانات 
    $summary = [
        'last_90_days_profit' => $data['total_profit'] ?? 0,
        'total_transactions' => $data['total_txns'] ?? 0,
        'avg_daily_profit' => round(($data['total_profit'] ?? 0) / 90, 2), 
        'branch_performance' => $branch_results, 
    ];

    // 6. تجهيز النص السياقي (Context Text) لـ GPT-4o
    $context_text = "ملخص الأداء المالي الحالي لمنظومة الصرافة (آخر 90 يوماً): 
- إجمالي الربح المُحقق: {$summary['last_90_days_profit']} دينار ليبي.
- إجمالي المعاملات: {$summary['total_transactions']} معاملة.
- متوسط الربح اليومي: {$summary['avg_daily_profit']} دينار ليبي.
- أداء الفروع الرئيسية (الربح): " . json_encode($summary['branch_performance'], JSON_UNESCAPED_UNICODE) . ".";

    return $context_text;
}
?>