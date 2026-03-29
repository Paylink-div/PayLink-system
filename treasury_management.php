<?php
// ملف: treasury_management.php (التعديل النهائي باستخدام ACL)


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 🛑 1. تضمين ملف ACL قبل أي منطق يتعلق بالدور أو الصلاحيات 🛑
include 'acl_functions.php';

// تأكد من وجود ملف db_connect.php ونجاح الاتصال
include 'db_connect.php'; 


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🛑 2. التحقق من الصلاحية المطلوبة وإيقاف التنفيذ في حالة الرفض 🛑
// نستخدم الصلاحية التي ظهرت في رسالة الخطأ: CAN_MANAGE_TREASURY_BALANCES
require_permission($conn, 'CAN_MANAGE_TREASURY_BALANCES', 'unauthorized.php');


// 💡 تحديد الصلاحيات والبيانات الأساسية (نستخدم check_permission الآن) 💡
$user_role = $_SESSION['user_role'] ?? 'موظف';
$is_general_manager = ($user_role == 'مدير عام');
// لم نعد بحاجة للتحقق اليدوي، check_permission هي الأصح
// $is_branch_manager = ($user_role == 'مدير فرع' || $is_general_manager); 
// الكود أعلاه تم حذفه!


// ------------------------------------
// جلب الفروع
// ------------------------------------
$current_branch_id = $_SESSION['branch_id'] ?? 0;
$current_branch_name = $_SESSION['branch_name'] ?? 'غير محدد';
$branches_list = [];
$branches_map = [];

// 💡 نفتح الاتصال مرة أخرى للـ query 💡
$conn_query = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn_query->connect_error) {
    die("Connection failed: " . $conn_query->connect_error);
}

$branches_result = $conn_query->query("SELECT id, name FROM branches ORDER BY name ASC");
if ($branches_result) {
    while($row = $branches_result->fetch_assoc()){
        $branches_list[] = $row;
        $branches_map[$row['id']] = $row['name'];
    }
}
// 💡 نغلق اتصال الـ query الآن 💡
$conn_query->close(); 


// تحديد الفرع المفلتر للعرض
$filter_branch_id = $current_branch_id;
$filter_branch_name = $current_branch_name;

if ($is_general_manager) {
    $filter_branch_id = intval($_REQUEST['branch_id'] ?? 0); 
    
    if ($filter_branch_id > 0 && isset($branches_map[$filter_branch_id])) {
        $filter_branch_name = $branches_map[$filter_branch_id];
    } elseif ($filter_branch_id === 0) {
        $filter_branch_name = 'كل الفروع (عرض مجمع)';
    } else {
        $filter_branch_id = 0; 
        $filter_branch_name = 'كل الفروع (عرض مجمع)';
    }
}


$message = '';
$message_type = '';


// جلب الرسالة المنقولة من POST عبر GET بعد إعادة التوجيه
if (isset($_GET['msg']) && isset($_GET['msg_type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['msg_type']);
}


// -----------------------------------------------------------------------------------------
// 1. معالجة طلبات التعديل (POST)
// -----------------------------------------------------------------------------------------


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 🛑 3. إعادة الاتصال بقاعدة البيانات للمنطق الذي يحتاج اتصالات مفتوحة 🛑
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        // يمكنك تسجيل خطأ أو التعامل معه بشكل مناسب
        $message = "❌ فشل الاتصال بقاعدة البيانات لمعالجة POST.";
        $message_type = 'danger';
        // لا تتابع المعالجة
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit_balance') {
        
        $currency_id = intval($_POST['currency_id'] ?? 0);
        $new_balance_amount = (float)($_POST['new_balance_amount'] ?? 0.0); 
        $target_branch_id = ($is_general_manager) ? intval($_POST['target_branch_id'] ?? 0) : $current_branch_id;


        if ($target_branch_id == 0) {
            $message = "❌ فشل: يرجى تحديد الفرع المستهدف (خطأ: target_branch_id).";
            $message_type = 'danger';
        } 
        elseif ($currency_id <= 0 || !isset($_POST['new_balance_amount']) || !is_numeric($_POST['new_balance_amount'])) {
            $message = "❌ بيانات التعديل غير صالحة. (خطأ: currency_id أو new_balance_amount).";
            $message_type = 'danger';
        } else {
            
            // استعلام الحفظ يستخدم ON DUPLICATE KEY UPDATE 
            $update_query = $conn->prepare("
                INSERT INTO treasury_balances (currency_id, current_balance, last_updated, branch_id)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE 
                    current_balance = VALUES(current_balance),
                    last_updated = NOW()
            ");

            $update_query->bind_param("idi", $currency_id, $new_balance_amount, $target_branch_id);

            if ($update_query->execute()) {
                $target_branch_name_saved = $branches_map[$target_branch_id] ?? 'الفرع المستهدف';
                
                $message = "✅ تم حفظ رصيد العملة بنجاح في فرع {$target_branch_name_saved} بقيمة " . number_format($new_balance_amount, 4);
                $message_type = 'success';
                
                // إعادة التوجيه لضمان تحديث العرض وتطبيق التصفية الجديدة 
                header("Location: treasury_management.php?branch_id={$target_branch_id}&msg_type={$message_type}&msg=" . urlencode($message));
                exit;

            } else {
                $message = "❌ خطأ في معالجة الرصيد: " . $update_query->error . ". (تأكد من إعداد المفتاح الفريد المركب).";
                $message_type = 'danger';
            }
            $update_query->close();
        }
    }
    
    // 🛑 4. إغلاق الاتصال بعد معالجة POST 🛑
    if (isset($conn)) {
        $conn->close();
    }
}


// -----------------------------------------------------------------------------------------
// 5. جلب العملات والأرصدة الحالية (للعرض)
// -----------------------------------------------------------------------------------------

// 🛑 6. إعادة الاتصال بقاعدة البيانات مرة أخرى لاستعلام العرض 🛑
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$balances_data = [];
$result = false;


if ($filter_branch_id > 0) {
    $balances_query = "
        SELECT 
            c.id AS currency_id, 
            c.currency_code,
            c.currency_name_ar,
            COALESCE(tb.current_balance, 0.0000) AS current_balance, 
            tb.last_updated
        FROM 
            currencies c
        LEFT JOIN 
            treasury_balances tb ON c.id = tb.currency_id AND tb.branch_id = ?
        ORDER BY 
            c.currency_code ASC
    ";
    
    $stmt = $conn->prepare($balances_query);
    if ($stmt === false) {
          $message = "❌ خطأ في تحضير استعلام عرض الأرصدة: " . $conn->error;
          $message_type = 'danger';
    } else {
        $stmt->bind_param("i", $filter_branch_id); 
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
    
} elseif ($is_general_manager && $filter_branch_id === 0) {
    // جلب مجموع الأرصدة (عرض مجمع للمدير العام)
     $balances_query = "
        SELECT 
            c.id AS currency_id, 
            c.currency_code,
            c.currency_name_ar,
            COALESCE(SUM(tb.current_balance), 0.0000) AS current_balance, 
            MAX(tb.last_updated) AS last_updated
        FROM 
            currencies c
        LEFT JOIN 
            treasury_balances tb ON c.id = tb.currency_id 
        GROUP BY
            c.id, c.currency_code, c.currency_name_ar
        ORDER BY 
            c.currency_code ASC
    ";
    $result = $conn->query($balances_query);
}


if ($result) {
    while ($row = $result->fetch_assoc()) {
          $balances_data[] = $row;
    }
}

// 🛑 7. إغلاق الاتصال بعد الانتهاء من استعلام العرض 🛑
if (isset($conn)) {
    $conn->close();
}


?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة أرصدة العملات (الخزينة) - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    </style>
</head>
<body>

    <?php 
    // تأكد من وجود ملف sidebar.php 
    include 'sidebar.php'; 
    ?> 

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-coins"></i> إدارة أرصدة العملات (الخزينة)</h1>
            
            <?php if ($is_general_manager): ?>
            <p class="lead text-danger">⚠ أنت في وضع المدير العام. يمكنك تعديل رصيد أي فرع من خلال المودال.</p>
            <form method="GET" class="form-inline mb-4 p-3 bg-white rounded shadow-sm">
                <label for="branch_id" class="mr-2">عرض أرصدة الفرع:</label>
                <select class="form-control mr-2" id="branch_id" name="branch_id">
                    <option value="0" <?php echo ($filter_branch_id == 0) ? 'selected' : ''; ?>>جميع الفروع (ملخص)</option>
                    <?php foreach ($branches_list as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php echo ($branch['id'] == $filter_branch_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> فلترة العرض</button>
            </form>
            <p class="lead">الفرع المُختار حالياً للعرض: <?php echo htmlspecialchars($filter_branch_name); ?></p>
            <?php else: ?>
            <p class="lead">الفرع الحالي: <?php echo htmlspecialchars($current_branch_name); ?> (رقم: <?php echo htmlspecialchars($current_branch_id); ?>)</p>
            <?php endif; ?>
            
            <p class="lead">استخدم زر الإجراءات (<i class="fas fa-edit"></i>) لـ إدخال رأس المال الأولي أو تعديل الأرصدة الحالية.</p>
            <hr>


            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    قائمة العملات وأرصدتها الحالية في <?php echo htmlspecialchars($filter_branch_name); ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm">
                            <thead class="thead-dark">
                                <tr>
                                    <th>رمز العملة</th>
                                    <th>اسم العملة</th>
                                    <th>الرصيد الحالي</th>
                                    <th>حالة الرصيد</th>
                                    <th>آخر تحديث</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($balances_data)): ?>
                                    <?php foreach ($balances_data as $row): 
                                        $balance = $row['current_balance'] ?? 0.0000;
                                        $status_text = ($balance > 0) ? 'موجود' : 'لم يتم الإدخال';
                                        $status_class = ($balance > 0) ? 'badge-success' : 'badge-danger';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['currency_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['currency_name_ar']); ?></td>
                                            <td><?php echo number_format((float)$balance, 4); ?></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                            <td><?php echo $row['last_updated'] ? date('Y-m-d H:i:s', strtotime($row['last_updated'])) : '---'; ?></td>
                                            <td>
                                                <?php if ($filter_branch_id > 0 || !$is_general_manager): ?>
                                                <button type="button" class="btn btn-sm btn-info edit-balance-btn" 
                                                                data-toggle="modal" 
                                                                data-target="#editBalanceModal"
                                                                data-id="<?php echo $row['currency_id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($row['currency_name_ar'] . ' (' . $row['currency_code'] . ')'); ?>"
                                                                data-balance="<?php echo $balance; ?>">
                                                               <i class="fas fa-edit"></i> 
                                                </button>
                                                <?php else: ?>
                                                     <span class="text-muted">غير متاح</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">لا توجد عملات في النظام أو لا يمكن جلب بيانات الأرصدة للفرع المختار.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="editBalanceModal" tabindex="-1" role="dialog" aria-labelledby="editBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="treasury_management.php"> 
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="editBalanceModalLabel">إدخال/تعديل رأس المال للعملة</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_balance">
                        <input type="hidden" name="currency_id" id="edit-currency-id">
                        
                        <?php if ($is_general_manager): ?>
                        <div class="form-group">
                            <label for="target-branch-id">الفرع المستهدف (للتعديل):</label>
                            <select class="form-control" name="target_branch_id" id="target-branch-id" required>
                                <option value="">-- اختر الفرع --</option>
                                <?php foreach ($branches_list as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>">
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-danger">⚠ كمدير عام، يجب اختيار الفرع الذي يتم تعديل رصيده.</small>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="target_branch_id" value="<?php echo $current_branch_id; ?>">
                        <?php endif; ?>


                        <div class="form-group">
                            <label for="edit-currency-name">العملة:</label>
                            <input type="text" class="form-control" id="edit-currency-name" readonly>
                        </div>
                        <div class="form-group">
                            <label for="edit-current-balance">الرصيد الحالي (للمراجعة):</label>
                            <input type="text" class="form-control" id="edit-current-balance" readonly>
                        </div>
                        <div class="form-group">
                            <label for="new_balance_amount">القيمة الجديدة لرأس المال:</label>
                            <input type="number" step="0.0001" min="0" class="form-control" name="new_balance_amount" id="new_balance_amount_input" required>
                            <small class="form-text text-muted">سيتم تسجيل هذه القيمة كرأس مال أو رصيد العملة الحالي (إجمالي المبلغ الحالي).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-info">حفظ رأس المال</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#editBalanceModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            
            var currencyId = button.data('id');
            var currencyName = button.data('name');
            var currentBalance = button.data('balance');
            
            modal.find('#edit-currency-id').val(currencyId);
            
            modal.find('#edit-currency-name').val(currencyName);
            modal.find('#edit-current-balance').val(parseFloat(currentBalance).toFixed(4));
            
            modal.find('#new_balance_amount_input').val(''); 
            
            <?php if ($is_general_manager): ?>
                 var filterBranchId = <?php echo $filter_branch_id; ?>;
                 if (filterBranchId > 0) {
                     modal.find('#target-branch-id').val(filterBranchId);
                 } else {
                     modal.find('#target-branch-id').val(''); 
                 }
            <?php endif; ?>
        });
    });
    </script>

</body>
</html>