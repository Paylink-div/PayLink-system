<?php
include 'db_connect.php';

// جلب سجل التدقيق بالكامل
$audit_result = $conn->query("
    SELECT al.*, u.full_name 
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY action_timestamp DESC 
    LIMIT 50
");

// *ملاحظة:* يجب أن تكون لديك واجهة لإدخال بيانات مستخدم مبدئي في جدول users حتى يظهر الاسم هنا
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سجل التدقيق (Audit Log) - PayLink</title>
    <style>
        /* إضافة ستايلات بسيطة */
        body { font-family: Tahoma, Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h2 { border-bottom: 2px solid #a72828; padding-bottom: 10px; color: #a72828; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top; }
        th { background-color: #a72828; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>سجل التدقيق الأمني (Audit Log - ميزة 21)</h2>
        <p>هذا السجل يوثق جميع الإجراءات الحساسة في النظام.</p>

        <table>
            <tr>
                <th>التاريخ والوقت</th>
                <th>نوع الإجراء</th>
                <th>الجدول المتأثر</th>
                <th>السجل/الـ ID</th>
                <th>المستخدم</th>
                <th>القيمة القديمة</th>
                <th>القيمة الجديدة</th>
            </tr>
            <?php
            if ($audit_result->num_rows > 0) {
                while($row = $audit_result->fetch_assoc()) {
                    $user_name = $row['full_name'] ? $row['full_name'] : 'ID: ' . $row['user_id'];
                    echo "<tr>";
                    echo "<td>{$row['action_timestamp']}</td>";
                    echo "<td><b>{$row['action_type']}</b></td>";
                    echo "<td>{$row['table_name']}</td>";
                    echo "<td>{$row['record_id']}</td>";
                    echo "<td>{$user_name}</td>";
                    echo "<td><pre style='margin:0;'>" . htmlspecialchars($row['old_value']) . "</pre></td>";
                    echo "<td><pre style='margin:0;'>" . htmlspecialchars($row['new_value']) . "</pre></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>لم يتم تسجيل أي إجراءات حساسة بعد.</td></tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>

<?php $conn->close(); ?>