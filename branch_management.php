<?php
// branch_management.php - إدارة الفروع (نسخة العمل أوفلاين)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php'; 
include_once 'functions.php';

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
$edit_id = 0;
$edit_name = '';
$edit_location = '';
$edit_is_active = 1;

// ===============================================
// 2. معالجة العمليات (إضافة، تحديث، حذف)
// ===============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // أ- منطق الحذف
    if (isset($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);
        $delete_sql = $conn->prepare("DELETE FROM branches WHERE id = ?");
        $delete_sql->bind_param("i", $delete_id);
        
        try {
            if ($delete_sql->execute()) {
                if ($delete_sql->affected_rows > 0) {
                    $message = "✅ تم حذف الفرع بنجاح.";
                }
            }
        } catch (mysqli_sql_exception $e) {
            $error = ($conn->errno == 1451) ? "❌ لا يمكن حذف الفرع لارتباطه بموظفين، صناديق أو عمليات مسجلة." : "❌ فشل الحذف: " . $e->getMessage();
        }
        $delete_sql->close();

    } else {
        // ب- منطق الإضافة أو التحديث
        $id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        $name = trim(strip_tags($_POST['name']));
        $location = trim(strip_tags($_POST['location']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من تكرار الاسم
        $check_sql = $conn->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
        $check_sql->bind_param("si", $name, $id);
        $check_sql->execute();
        $check_result = $check_sql->get_result();

        if ($check_result->num_rows > 0) {
            $error = "⚠️ عذراً، اسم هذا الفرع مستخدم بالفعل.";
        } else {
            try {
                if ($id > 0) {
                    // تحديث
                    $update_sql = $conn->prepare("UPDATE branches SET name=?, location=?, is_active=? WHERE id=?");
                    $update_sql->bind_param("ssii", $name, $location, $is_active, $id);
                    $update_sql->execute();
                    $message = "✅ تم تحديث بيانات الفرع بنجاح.";
                } else {
                    // إضافة جديد
                    $insert_sql = $conn->prepare("INSERT INTO branches (name, location, is_active, created_at) VALUES (?, ?, ?, NOW())");
                    $insert_sql->bind_param("ssi", $name, $location, $is_active);
                    $insert_sql->execute();
                    $message = "✅ تم إضافة الفرع الجديد بنجاح.";
                }
            } catch (Exception $e) {
                $error = "❌ فشل في حفظ البيانات: " . $e->getMessage();
            }
        }
        $check_sql->close();
    }

    // إعادة التوجيه
    if (empty($error)) {
        header("Location: branch_management.php?message=" . urlencode($message));
        exit;
    }
}

// -------------------------------------------------------------
// 3. جلب البيانات للعرض
// -------------------------------------------------------------

if (isset($_GET['message'])) $message = htmlspecialchars($_GET['message']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT name, location, is_active FROM branches WHERE id = ?"); 
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_res = $edit_stmt->get_result();
    if ($row = $edit_res->fetch_assoc()) {
        $edit_name = $row['name'];
        $edit_location = $row['location'];
        $edit_is_active = $row['is_active'];
    }
    $edit_stmt->close();
}

$branches_result = $conn->query("SELECT id, name, location, is_active, created_at FROM branches ORDER BY id DESC");

include 'header.php'; 
?>

<link rel="stylesheet" href="assets/css/sb-admin-2.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome-free/css/all.min.css">

<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800 text-right"><i class="fas fa-store-alt text-primary ml-2"></i> إدارة فروع النظام</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm border-0 alert-dismissible fade show text-right">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm border-0 alert-dismissible fade show text-right">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header <?php echo $edit_id ? 'bg-warning text-dark' : 'bg-primary text-white'; ?> font-weight-bold text-right">
                    <?php echo $edit_id ? '<i class="fas fa-edit ml-1"></i> تعديل بيانات الفرع' : '<i class="fas fa-plus-circle ml-1"></i> إضافة فرع جديد'; ?>
                </div>
                <div class="card-body text-right">
                    <form method="POST" action="branch_management.php">
                        <input type="hidden" name="branch_id" value="<?php echo $edit_id; ?>">
                        
                        <div class="form-group mb-3 text-right">
                            <label class="font-weight-bold">اسم الفرع <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_name); ?>" required>
                        </div>

                        <div class="form-group mb-3 text-right">
                            <label class="font-weight-bold">الموقع / العنوان <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($edit_location); ?>" required>
                        </div>

                        <div class="form-group mb-4 text-right">
                            <div class="custom-control custom-switch pr-5">
                                <input type="checkbox" class="custom-control-input" name="is_active" id="is_active" <?php echo ($edit_is_active) ? 'checked' : ''; ?>>
                                <label class="custom-control-label font-weight-bold" for="is_active">الفرع نشط</label>
                            </div>
                        </div>

                        <button type="submit" class="btn <?php echo $edit_id ? 'btn-warning' : 'btn-primary'; ?> btn-block shadow-sm">
                            <i class="fas fa-save ml-1"></i> <?php echo $edit_id ? 'حفظ التغييرات' : 'إضافة الفرع الآن'; ?>
                        </button>
                        
                        <?php if ($edit_id): ?>
                            <a href="branch_management.php" class="btn btn-light btn-block mt-2 border">إلغاء التعديل</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-row-reverse">
                    <span class="font-weight-bold"><i class="fas fa-list-ul ml-1"></i> الفروع المسجلة</span>
                    <span class="badge badge-light"><?php echo $branches_result ? $branches_result->num_rows : 0; ?> فرع</span>
                </div>
                <div class="card-body p-0 text-right">
                    <div class="table-responsive">
                        <table class="table table-hover text-center mb-0" dir="rtl">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-right">اسم الفرع</th>
                                    <th class="text-right">الموقع</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($branches_result && $branches_result->num_rows > 0): ?>
                                    <?php while($branch = $branches_result->fetch_assoc()): ?>
                                        <tr class="<?php echo ($edit_id == $branch['id']) ? 'table-warning' : ''; ?>">
                                            <td><?php echo $branch['id']; ?></td>
                                            <td class="text-right font-weight-bold text-primary"><?php echo htmlspecialchars($branch['name']); ?></td>
                                            <td class="text-right small"><?php echo htmlspecialchars($branch['location']); ?></td>
                                            <td>
                                                <span class="badge badge-pill <?php echo $branch['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                    <?php echo $branch['is_active'] ? 'نشط' : 'معطل'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="branch_management.php?edit=<?php echo $branch['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm ml-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" onclick="confirmDelete(<?php echo $branch['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="py-5 text-muted">لا توجد فروع مسجلة.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="delete-form" method="POST" style="display:none;">
    <input type="hidden" name="delete_id" id="delete-input">
</form>

<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
function confirmDelete(id) {
    if (confirm('⚠️ هل أنت متأكد من حذف هذا الفرع؟\nلا يمكن حذف الفروع المرتبطة ببيانات مالية.')) {
        document.getElementById('delete-input').value = id;
        document.getElementById('delete-form').submit();
    }
}
setTimeout(function() { $(".alert").fadeOut('slow'); }, 4000);
</script>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>