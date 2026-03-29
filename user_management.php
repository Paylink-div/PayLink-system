<?php
// user_management.php - إدارة فريق العمل (نسخة الأوفلاين المتجاوبة)

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 
require_once 'functions.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'موظف'; 
$current_user_branch = $_SESSION['branch_id'] ?? 0;

// السماح فقط للمدراء
if ($current_user_role !== 'مدير عام' && $current_user_role !== 'مدير فرع') {
    header("Location: dashboard.php"); 
    exit;
}

// قائمة الموديولات المتاحة للصلاحيات
$available_modules = [
    'exchange_process' => 'إجراء عملية صرف', 
    'invoices_log' => 'سجل الفواتير',
    'currency_balance_management' => 'إدارة العملات والأرصدة', 
    'company_treasury' => 'خزينة الشركة البنكية',
    'clients_management' => 'إدارة العملاء', 
    'users_management' => 'إدارة المستخدمين',
    'exchange_rate_settings' => 'إعدادات أسعار الصرف', 
    'treasury_balance_management' => 'إدارة أرصدة الصناديق',
    'comprehensive_reports' => 'التقارير الشاملة'
];

$message = $error = '';
$edit_id = 0; 
$edit_full_name = $edit_username = $edit_phone_number = $edit_email = ''; 
$edit_is_active = 1; $edit_branch_id = 0; $edit_user_role = 'موظف'; $edit_permissions = [];

// جلب الفروع المتاحة حسب صلاحية المدير
$branches_list = [];
$b_sql = "SELECT id, name FROM branches WHERE is_active = 1";
if ($current_user_role === 'مدير فرع') $b_sql .= " AND id = $current_user_branch";
$stmt_b = $conn->prepare($b_sql);
$stmt_b->execute();
$b_res = $stmt_b->get_result();
while($r = $b_res->fetch_assoc()) $branches_list[$r['id']] = $r['name'];

// معالجة الإرسال (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);
        if ($delete_id == $current_user_id) {
            $error = "❌ لا يمكنك حذف حسابك الشخصي.";
        } else {
            $del_sql = "DELETE FROM users WHERE id = ?";
            if ($current_user_role === 'مدير فرع') $del_sql .= " AND branch_id = $current_user_branch";
            $stmt = $conn->prepare($del_sql);
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) $message = "✅ تم حذف المستخدم بنجاح.";
            else $error = "❌ فشل الحذف: لا تملك صلاحية حذف هذا الموظف.";
        }
    } else {
        $user_id_post = intval($_POST['user_id'] ?? 0);
        $full_name = strip_tags(trim($_POST['full_name']));
        $username = strip_tags(trim($_POST['username']));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = strip_tags(trim($_POST['phone_number']));
        $role = ($current_user_role === 'مدير فرع' && $_POST['user_role'] === 'مدير عام') ? 'موظف' : $_POST['user_role'];
        $branch = ($current_user_role === 'مدير فرع') ? $current_user_branch : intval($_POST['branch_id']);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $perms = json_encode($_POST['permissions'] ?? []);

        if ($user_id_post > 0) {
            if ($current_user_role === 'مدير فرع') {
                $check = $conn->query("SELECT id FROM users WHERE id=$user_id_post AND branch_id=$current_user_branch");
                if ($check->num_rows == 0) die("❌ غير مصرح لك بتعديل موظف خارج فرعك.");
            }

            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, email=?, phone_number=?, password_hash=?, user_role=?, branch_id=?, is_active=?, permissions_json=? WHERE id=?");
                $stmt->bind_param("ssssssiisi", $full_name, $username, $email, $phone, $hash, $role, $branch, $active, $perms, $user_id_post);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, email=?, phone_number=?, user_role=?, branch_id=?, is_active=?, permissions_json=? WHERE id=?");
                $stmt->bind_param("sssssiisi", $full_name, $username, $email, $phone, $role, $branch, $active, $perms, $user_id_post);
            }
            if ($stmt->execute()) $message = "✅ تم تحديث بيانات الموظف بنجاح."; else $error = "❌ خطأ: اسم المستخدم أو البريد موجود مسبقاً.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone_number, password_hash, user_role, branch_id, is_active, permissions_json, created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW())");
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->bind_param("ssssssiis", $full_name, $username, $email, $phone, $hash, $role, $branch, $active, $perms);
            if ($stmt->execute()) $message = "✅ تم إضافة الموظف الجديد بنجاح."; else $error = "❌ خطأ: بيانات مكررة في النظام.";
        }
    }
    if ($message && !$error) { header("Location: user_management.php?msg=".urlencode($message)); exit; }
}

if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    if ($u = $stmt->get_result()->fetch_assoc()) {
        if ($current_user_role === 'مدير فرع' && $u['branch_id'] != $current_user_branch) {
            header("Location: user_management.php?msg=".urlencode("❌ لا يمكنك تعديل موظفي الفروع الأخرى."));
            exit;
        }
        $edit_full_name = $u['full_name']; $edit_username = $u['username']; $edit_email = $u['email'];
        $edit_phone_number = $u['phone_number']; $edit_user_role = $u['user_role'];
        $edit_branch_id = $u['branch_id']; $edit_is_active = $u['is_active'];
        $edit_permissions = json_decode($u['permissions_json'] ?? '[]', true);
    }
}

$users_sql = "SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id";
if ($current_user_role === 'مدير فرع') $users_sql .= " WHERE u.branch_id = $current_user_branch";
$users_sql .= " ORDER BY u.id DESC";
$users_result = $conn->query($users_sql);

include 'header.php'; 
?>

<style>
    .permissions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; background: #f8f9fc; padding: 15px; border-radius: 8px; border: 1px solid #e3e6f0; }
    .badge-soft-success { background: #e8f5e9; color: #2e7d32; }
    .badge-soft-danger { background: #ffebee; color: #c62828; }
    @media (max-width: 768px) {
        .table thead { display: none; }
        .table tr { display: block; border: 1px solid #e3e6f0; border-radius: 8px; margin-bottom: 10px; background: white; }
        .table td { display: flex; justify-content: space-between; border: none; padding: 8px 12px; text-align: left; }
        .table td::before { content: attr(data-label); font-weight: bold; color: #4e73df; margin-right: 10px; }
        .permissions-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users-cog text-primary ml-2"></i> إدارة فريق العمل</h1>
    </div>

    <?php if ($message): ?> <div class="alert alert-success shadow-sm border-0"><?php echo $message; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger shadow-sm border-0"><?php echo $error; ?></div> <?php endif; ?>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas <?php echo $edit_id ? 'fa-user-edit' : 'fa-user-plus'; ?> ml-1"></i>
                        <?php echo $edit_id ? "تعديل بيانات الموظف: $edit_full_name" : "إضافة موظف جديد"; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $edit_id; ?>">
                        <div class="row text-right">
                            <div class="form-group col-md-4"><label class="small font-weight-bold">الاسم الكامل:</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($edit_full_name); ?>" required></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">اسم المستخدم:</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_username); ?>" required></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">الهاتف:</label><input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($edit_phone_number); ?>" required></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">البريد الإلكتروني:</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_email); ?>" required></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">كلمة المرور:</label><input type="password" name="password" class="form-control" <?php echo !$edit_id ? 'required' : ''; ?> placeholder="<?php echo $edit_id ? 'اتركه فارغاً للحفاظ عليها' : ''; ?>"></div>
                            <div class="form-group col-md-2"><label class="small font-weight-bold">الفرع:</label>
                                <select name="branch_id" class="form-control" <?php if($current_user_role === 'مدير فرع') echo 'disabled'; ?>>
                                    <?php if($current_user_role === 'مدير عام'): ?><option value="0">الإدارة العامة</option><?php endif; ?>
                                    <?php foreach($branches_list as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ($edit_branch_id == $id || ($current_user_role === 'مدير فرع' && $id == $current_user_branch)) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-2"><label class="small font-weight-bold">الدور:</label>
                                <select name="user_role" class="form-control">
                                    <option value="موظف" <?php if($edit_user_role=='موظف') echo 'selected'; ?>>موظف</option>
                                    <?php if($current_user_role=='مدير عام'): ?>
                                        <option value="مدير فرع" <?php if($edit_user_role=='مدير فرع') echo 'selected'; ?>>مدير فرع</option>
                                        <option value="مدير عام" <?php if($edit_user_role=='مدير عام') echo 'selected'; ?>>مدير عام</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <label class="font-weight-bold small text-primary mb-2 text-right d-block">صلاحيات الوصول الممنوحة:</label>
                        <div class="permissions-grid text-right">
                            <?php foreach($available_modules as $key => $val): ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="p_<?php echo $key; ?>" name="permissions[]" value="<?php echo $key; ?>" <?php echo in_array($key, $edit_permissions)?'checked':''; ?>>
                                <label class="custom-control-label mr-4 small" for="p_<?php echo $key; ?>"><?php echo $val; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="actSw" name="is_active" value="1" <?php if($edit_is_active || !$edit_id) echo 'checked'; ?>>
                                <label class="custom-control-label font-weight-bold small" for="actSw">الحساب نشط</label>
                            </div>
                            <div>
                                <?php if($edit_id): ?> <a href="user_management.php" class="btn btn-light border btn-sm ml-2">إلغاء</a> <?php endif; ?>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">حفظ البيانات</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3"><h6 class="m-0 font-weight-bold"><i class="fas fa-list-ul ml-1"></i> قائمة الموظفين</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-right">
                            <thead class="bg-light">
                                <tr>
                                    <th>الموظف</th><th>الدور</th><th>الفرع</th><th>الحالة</th><th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="الموظف:">
                                        <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="small text-muted">@<?php echo htmlspecialchars($u['username']); ?></div>
                                    </td>
                                    <td data-label="الدور:"><span class="badge badge-light border"><?php echo $u['user_role']; ?></span></td>
                                    <td data-label="الفرع:"><?php echo htmlspecialchars($u['branch_name'] ?? 'الإدارة'); ?></td>
                                    <td data-label="الحالة:">
                                        <span class="badge <?php echo $u['is_active'] ? 'badge-soft-success' : 'badge-soft-danger'; ?> p-2">
                                            <?php echo $u['is_active'] ? 'نشط' : 'معطل'; ?>
                                        </span>
                                    </td>
                                    <td data-label="الإجراءات:">
                                        <div class="btn-group">
                                            <a href="user_management.php?edit=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                            <?php if($u['id'] != $current_user_id): ?>
                                                <button onclick="if(confirm('هل أنت متأكد من حذف هذا الموظف؟')) { document.getElementById('del_<?php echo $u['id']; ?>').submit(); }" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                <form id="del_<?php echo $u['id']; ?>" method="POST" style="display:none;"><input type="hidden" name="delete_id" value="<?php echo $u['id']; ?>"></form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>setTimeout(() => { $(".alert").fadeOut(); }, 4000);</script>
<?php include 'footer.php'; if(isset($conn)) $conn->close(); ?>