<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$panel = $_GET['panel'] ?? '';
$editingUser = null;
$error = '';
$success = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_user') {
            $role = $_POST['role'] ?? 'learner';
            $name = trim($_POST['name'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $password = $_POST['password'] ?? '';
            $number = trim($_POST['number'] ?? '');
            $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;

            if (!in_array($role, ['admin', 'instructor', 'learner'], true)) {
                throw new RuntimeException('Invalid role selected.');
            }

            if ($name === '' || $surname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
                throw new RuntimeException('Please complete all required fields correctly.');
            }

            $adminNumber = $role === 'admin' ? ($number ?: null) : null;
            $invigilatorNumber = $role === 'instructor' ? ($number ?: null) : null;

            $stmt = $pdo->prepare(
                "INSERT INTO users
                 (role, name, surname, email, contact, password_hash, admin_number, invigilator_number, class_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $role,
                $name,
                $surname,
                $email,
                $contact,
                password_hash($password, PASSWORD_DEFAULT),
                $adminNumber,
                $invigilatorNumber,
                $classId
            ]);

            header('Location: manage_users.php?panel=view&success=created');
            exit;
        }

        if ($action === 'update_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'learner';
            $name = trim($_POST['name'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $number = trim($_POST['number'] ?? '');
            $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;

            if ($userId <= 0 || !in_array($role, ['admin', 'instructor', 'learner'], true)) {
                throw new RuntimeException('Invalid user details.');
            }

            $adminNumber = $role === 'admin' ? ($number ?: null) : null;
            $invigilatorNumber = $role === 'instructor' ? ($number ?: null) : null;

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET role = ?, name = ?, surname = ?, email = ?, contact = ?,
                     admin_number = ?, invigilator_number = ?, class_id = ?
                 WHERE user_id = ?"
            );
            $stmt->execute([
                $role,
                $name,
                $surname,
                $email,
                $contact,
                $adminNumber,
                $invigilatorNumber,
                $classId,
                $userId
            ]);

            if (!empty($_POST['password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $userId]);
            }

            header('Location: manage_users.php?panel=view&success=updated');
            exit;
        }

        if ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId === (int)$_SESSION['user_id']) {
                throw new RuntimeException('You cannot delete the account you are currently using.');
            }

            $stmt = $pdo->prepare("SELECT role, admin_number FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if ($target && $target['role'] === 'admin') {
                throw new RuntimeException('Administrator accounts cannot be deleted.');
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);

            header('Location: manage_users.php?panel=view&success=deleted');
            exit;
        }
    }

    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editingUser = $stmt->fetch();
        $panel = 'add';
    }

    $users = $pdo->query(
        "SELECT u.*, c.class_name
         FROM users u
         LEFT JOIN classes c ON u.class_id = c.class_id
         ORDER BY u.created_at DESC"
    )->fetchAll();

    $classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();

    $messages = [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.'
    ];

    if (!empty($_GET['success']) && isset($messages[$_GET['success']])) {
        $success = $messages[$_GET['success']];
    }
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        $error = 'That email or administrator/instructor number is already registered.';
    } else {
        $error = 'The user could not be saved.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1>Manage Users</h1>
        <p>Create, update and remove users without creating duplicate accounts.</p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="action-toolbar">
    <a class="btn btn-primary" href="manage_users.php?panel=add">
        <i class="fa-solid fa-user-plus"></i> Add User
    </a>
    <a class="btn btn-outline" href="manage_users.php?panel=view">
        <i class="fa-solid fa-users"></i> View Users
    </a>
</div>

<?php if ($panel === 'add'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2><?= $editingUser ? 'Edit User' : 'Add User' ?></h2>
            <p class="text-muted">Learners can also register themselves from the registration page.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="<?= $editingUser ? 'update_user' : 'create_user' ?>">
        <?php if ($editingUser): ?>
            <input type="hidden" name="user_id" value="<?= (int)$editingUser['user_id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="input-group">
                <label>Role</label>
                <select name="role" required>
                    <?php foreach (['learner' => 'Learner', 'instructor' => 'Instructor', 'admin' => 'Admin'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($editingUser['role'] ?? '') === $value) ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label>Name</label>
                <input name="name" required value="<?= e($editingUser['name'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Surname</label>
                <input name="surname" required value="<?= e($editingUser['surname'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= e($editingUser['email'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Contact</label>
                <input name="contact" value="<?= e($editingUser['contact'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label><?= (($editingUser['role'] ?? '') === 'instructor') ? 'Invigilator Number' : 'Admin Number' ?></label>
                <input name="number" value="<?= e($editingUser['admin_number'] ?? $editingUser['invigilator_number'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Class</label>
                <select name="class_id">
                    <option value="">Not assigned</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int)$class['class_id'] ?>" <?= ((int)($editingUser['class_id'] ?? 0) === (int)$class['class_id']) ? 'selected' : '' ?>>
                            <?= e($class['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label>Password <?= $editingUser ? '(leave blank to keep current)' : '' ?></label>
                <input type="password" name="password" <?= $editingUser ? '' : 'required' ?> minlength="6">
            </div>
        </div>

        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-save"></i>
            <?= $editingUser ? 'Update User' : 'Create User' ?>
        </button>
    </form>
</section>
<?php endif; ?>

<?php if ($panel === 'view'): ?>
<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Registered Users</h2>
            <p class="text-muted"><?= count($users) ?> user(s) in the system.</p>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Class</th><th>Number</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= e($user['name'] . ' ' . $user['surname']) ?></strong></td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="badge badge-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span></td>
                    <td><?= e($user['class_name'] ?? 'Unassigned') ?></td>
                    <td><?= e($user['admin_number'] ?? $user['invigilator_number'] ?? '-') ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-outline btn-small" href="manage_users.php?edit=<?= (int)$user['user_id'] ?>">Edit</a>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($panel === ''): ?>
<section class="dashboard-grid">
    <div class="dashboard-card">
        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
        <h2><?= count($users) ?></h2>
        <p class="text-muted">Registered users</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
        <h2><?= count(array_filter($users, fn($u) => $u['role'] === 'learner')) ?></h2>
        <p class="text-muted">Learners</p>
    </div>
    <div class="dashboard-card">
        <div class="stat-icon purple"><i class="fa-solid fa-chalkboard-user"></i></div>
        <h2><?= count(array_filter($users, fn($u) => $u['role'] === 'instructor')) ?></h2>
        <p class="text-muted">Instructors</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
