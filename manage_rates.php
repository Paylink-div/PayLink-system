<?php
// manage_rates.php - إدارة أسعار الصرف (نسخة شركة واحدة)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php';
include_once 'functions.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? 'موظف';
$is_authorized = in_array(trim($user_role), ['مدير عام', 'مدير فرع']);
$message = '';

function get_all_exchange_rates_data($conn) {
    $data = [];
    $sql = "SELECT c.id as currency_fk_id, e.id as rate_id, c.currency_code, c.currency_name_ar, 
                   e.buy_rate, e.sell_rate, e.commission_percentage, e.is_display_active, e.last_updated
            FROM currencies c
            LEFT JOIN exchange_rates e ON (c.currency_code = e.currency_code)
            ORDER BY c.is_base_currency DESC, c.currency_code ASC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    return $data;
}

$rates_data = get_all_exchange_rates_data($conn); 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_rates']) && $is_authorized) {
    $conn->begin_transaction();
    try {
        foreach ($_POST['rates'] as $rate_id => $data) { 
            $rate_id = intval($rate_id);
            $buy = floatval($data['buy_rate']);
            $sell = floatval($data['sell_rate']);
            $comm = floatval($data['commission_percentage']);
            $disp = isset($data['is_display_active']) ? 1 : 0; 

            $stmt = $conn->prepare("UPDATE exchange_rates SET buy_rate = ?, sell_rate = ?, commission_percentage = ?, is_display_active = ?, last_updated = NOW() WHERE id = ?");
            $stmt->bind_param("ddddi", $buy, $sell, $comm, $disp, $rate_id);
            $stmt->execute();
        }
        $conn->commit();
        $message = '<div class="alert alert-success">✅ تم تحديث الأسعار بنجاح.</div>';
        $rates_data = get_all_exchange_rates_data($conn); 
    } catch (Exception $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">❌ فشل التحديث: ' . $e->getMessage() . '</div>';
    }
}

$pageTitle = 'إدارة أسعار الصرف';
include 'header.php'; 
?>

<div class="container-fluid py-4" dir="rtl text-right">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h2 class="mb-0"><i class="fas fa-hand-holding-usd text-success ml-2"></i> إدارة أسعار صرف العملات</h2>
            <p class="text-muted">تحكم في الأسعار التي تظهر للزبائن وفي العمليات المالية</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="display_api.php" target="_blank" class="btn btn-dark shadow-sm px-4">
                <i class="fas fa-desktop ml-1"></i> فتح شاشة العرض الخارجية
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 font-weight-bold"><i class="fas fa-list ml-2"></i> قائمة العملات المتاحة</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-hover text-center align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>العملة</th>
                                <th>سعر الشراء (نشتري)</th>
                                <th>سعر البيع (نبيع)</th>
                                <th>العمولة %</th>
                                <th>عرض على الشاشة</th>
                                <th>آخر تحديث</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rates_data as $rate) : ?>
                            <tr>
                                <td class="text-right">
                                    <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($rate['currency_name_ar']); ?></div>
                                    <small class="badge badge-info"><?php echo htmlspecialchars($rate['currency_code']); ?></small>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" name="rates[<?php echo $rate['rate_id']; ?>][buy_rate]" 
                                           value="<?php echo $rate['buy_rate']; ?>" class="form-control text-center mx-auto font-weight-bold" style="max-width:130px; border-color: #28a745;">
                                </td>
                                <td>
                                    <input type="number" step="0.0001" name="rates[<?php echo $rate['rate_id']; ?>][sell_rate]" 
                                           value="<?php echo $rate['sell_rate']; ?>" class="form-control text-center mx-auto font-weight-bold" style="max-width:130px; border-color: #dc3545;">
                                </td>
                                <td>
                                    <input type="number" step="0.1" name="rates[<?php echo $rate['rate_id']; ?>][commission_percentage]" 
                                           value="<?php echo $rate['commission_percentage']; ?>" class="form-control text-center mx-auto" style="max-width:90px;">
                                </td>
                                <td>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="disp_<?php echo $rate['rate_id']; ?>" 
                                               name="rates[<?php echo $rate['rate_id']; ?>][is_display_active]" value="1" <?php echo ($rate['is_display_active'] == 1) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="disp_<?php echo $rate['rate_id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <i class="far fa-clock ml-1"></i><?php echo date('d/m H:i', strtotime($rate['last_updated'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($is_authorized): ?>
                    <div class="text-left mt-4">
                        <button type="submit" name="update_rates" class="btn btn-success btn-lg px-5 shadow">
                            <i class="fas fa-save ml-1"></i> حفظ كافة التغييرات
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center mt-3">
                        <i class="fas fa-exclamation-triangle ml-1"></i> ليس لديك صلاحية تعديل الأسعار.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<style>
    /* تحسينات بصرية إضافية لسهولة الاستخدام */
    .table td { vertical-align: middle !important; }
    .form-control:focus { box-shadow: 0 0 5px rgba(40, 167, 69, 0.5); border-color: #28a745; }
    .custom-switch .custom-control-label::before { height: 1.5rem; width: 2.75rem; border-radius: 1rem; }
    .custom-switch .custom-control-label::after { width: calc(1.5rem - 4px); height: calc(1.5rem - 4px); background-color: #adb5bd; border-radius: 50%; }
    .custom-switch .custom-control-input:checked ~ .custom-control-label::after { transform: translateX(1.25rem); background-color: #fff; }
    .custom-switch .custom-control-input:checked ~ .custom-control-label::before { background-color: #28a745; }
</style>

<?php include 'footer.php'; ?>