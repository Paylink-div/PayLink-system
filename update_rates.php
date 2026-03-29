<?php
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

// -----------------------------------------------------------------------
// 1. جلب العملات الأجنبية وأسعارها الحالية
// -----------------------------------------------------------------------
$local_currency_code = 'LYD'; 
$rates_sql = "
    SELECT 
        c.id AS currency_id, 
        c.currency_code,
        c.currency_name,
        COALESCE(er.buy_rate, 0) AS buy_rate,
        COALESCE(er.sell_rate, 0) AS sell_rate
    FROM currencies c
    LEFT JOIN exchange_rates er ON c.id = er.currency_id
    WHERE c.currency_code != '$local_currency_code'
    ORDER BY c.currency_code ASC
";
$rates_result = $conn->query($rates_sql);


// -----------------------------------------------------------------------
// 2. معالجة تحديث الأسعار (POST)
// -----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_rates'])) {
    $conn->begin_transaction();
    $success = true;

    try {
        // حلقة تكرارية على جميع العملات المحدثة
        foreach ($_POST['currency_id'] as $index => $currency_id) {
            $currency_id = intval($currency_id);
            $buy_rate = floatval($_POST['buy_rate'][$index]);
            $sell_rate = floatval($_POST['sell_rate'][$index]);

            // التحقق من أن الأسعار موجبة
            if ($buy_rate < 0 || $sell_rate < 0) {
                 throw new Exception("يجب أن تكون أسعار الصرف قيمة موجبة.");
            }
            
            // تحديث/إضافة سعر الصرف باستخدام ON DUPLICATE KEY UPDATE
            $update_sql = "
                INSERT INTO exchange_rates (currency_id, buy_rate, sell_rate)
                VALUES ('$currency_id', '$buy_rate', '$sell_rate')
                ON DUPLICATE KEY UPDATE buy_rate = VALUES(buy_rate), sell_rate = VALUES(sell_rate)
            ";
            
            if (!$conn->query($update_sql)) {
                throw new Exception("خطأ في تحديث الأسعار.");
            }
        }
        
        $conn->commit();
        $message = '<div class="alert alert-success">تم تحديث أسعار الصرف بنجاح!</div>';

    } catch (Exception $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">خطأ: ' . $e->getMessage() . '</div>';
    }
    
    // إعادة جلب النتائج بعد التحديث لعرضها
    $rates_result = $conn->query($rates_sql);
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث أسعار الصرف - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-chart-line"></i> تحديث أسعار الصرف</h1>
            <hr>
            
            <?php echo $message; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    تعديل أسعار العملات الأجنبية مقابل (<?php echo $local_currency_code; ?>)
                </div>
                <div class="card-body">
                    <form method="POST" action="update_rates.php">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center">
                                <thead>
                                    <tr>
                                        <th>العملة</th>
                                        <th>سعر الشراء (للمصرف)</th>
                                        <th>سعر البيع (للمصرف)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rates_result && $rates_result->num_rows > 0): ?>
                                        <?php while ($row = $rates_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($row['currency_name'] . ' (' . $row['currency_code'] . ')'); ?>
                                                    <input type="hidden" name="currency_id[]" value="<?php echo $row['currency_id']; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="form-control text-center" 
                                                           name="buy_rate[]" value="<?php echo number_format($row['buy_rate'], 4, '.', ''); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="form-control text-center" 
                                                           name="sell_rate[]" value="<?php echo number_format($row['sell_rate'], 4, '.', ''); ?>" required>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center">لم يتم العثور على أي عملات أجنبية لإدارة أسعارها.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($rates_result && $rates_result->num_rows > 0): ?>
                            <button type="submit" name="save_rates" class="btn btn-success btn-block mt-3">
                                <i class="fas fa-save"></i> حفظ جميع الأسعار المحدثة
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>