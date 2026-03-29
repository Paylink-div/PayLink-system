<?php
// display_api.php - شاشة عرض أسعار الصرف
require_once 'db_connect.php'; 

// جلب البيانات من القاعدة
$sql = "SELECT c.currency_name, c.currency_code, er.sell_rate, er.buy_rate
        FROM exchange_rates er
        JOIN currencies c ON er.currency_code = c.currency_code
        WHERE er.is_display_active = 1
        ORDER BY er.last_updated DESC";

$result = $conn->query($sql);
$rates = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rates[] = $row;
    }
}

// التحقق مما إذا كان الطلب يريد JSON (مثل التطبيقات) أو صفحة عرض
if (isset($_GET['format']) && $_GET['format'] == 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'rates' => $rates]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>شاشة أسعار الصرف - PayLink</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #1a1a2e; color: white; margin: 0; padding: 20px; overflow: hidden; }
        .header { text-align: center; padding: 20px; background: #16213e; border-bottom: 4px solid #0f3460; margin-bottom: 30px; }
        .container { max-width: 1200px; margin: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 2.5rem; text-align: center; }
        th { background-color: #0f3460; color: #e94560; padding: 20px; border: 1px solid #16213e; }
        td { padding: 25px; border: 1px solid #16213e; background-color: #16213e; }
        .currency-name { font-weight: bold; color: #4db8ff; }
        .rate-val { font-family: 'Courier New', monospace; color: #00ff41; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; padding: 10px; font-size: 1.2rem; background: #0f3460; }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>

<div class="header">
    <h1>أسعار صرف العملات الآن</h1>
    <p><?php echo date("Y-m-d H:i"); ?></p>
</div>

<div class="container">
    <table>
        <thead>
            <tr>
                <th>العملة</th>
                <th>شراء</th>
                <th>بيع </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rates as $rate): ?>
            <tr>
                <td class="currency-name"><?php echo $rate['currency_name'] . " (" . $rate['currency_code'] . ")"; ?></td>
                <td class="rate-val"><?php echo number_format($rate['buy_rate'], 3); ?></td>
                <td class="rate-val"><?php echo number_format($rate['sell_rate'], 3); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="footer"> نأمل لكم يوماً سعيداً
</div>

</body>
</html>