<?php
session_start();

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$connect = mysqli_connect(
    getenv('DB_HOST') ?: 'db',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    getenv('DB_NAME') ?: 'db_docker'
);

if (!$connect) {
    http_response_code(500);
    exit('Database connection failed. Check Docker services.');
}

mysqli_set_charset($connect, 'utf8mb4');

$createTable = "
    CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";

if (!mysqli_query($connect, $createTable)) {
    http_response_code(500);
    exit('Unable to create the students table.');
}

$message = '';
$error = '';
$editingStudent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid form submission. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            $error = 'Invalid student record.';
        } else {
            $stmt = mysqli_prepare($connect, 'DELETE FROM students WHERE id = ?');

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: index.php?status=deleted');
                    exit;
                }

                mysqli_stmt_close($stmt);
            }

            $error = 'Unable to delete the student record.';
        }
    } elseif ($action === 'create' || $action === 'update') {
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');

        if ($first === '' || $middle === '' || $last === '') {
            $error = 'Please complete all name fields.';
        } elseif (
            mb_strlen($first) > 100 ||
            mb_strlen($middle) > 100 ||
            mb_strlen($last) > 100
        ) {
            $error = 'Each name must be 100 characters or fewer.';
        } elseif ($action === 'update') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                $error = 'Invalid student record.';
            } else {
                $editingStudent = [
                    'id' => $id,
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                ];
                $stmt = mysqli_prepare(
                    $connect,
                    'UPDATE students
                     SET first_name = ?, middle_name = ?, last_name = ?
                     WHERE id = ?'
                );

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt, 'sssi', $first, $middle, $last, $id
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header('Location: index.php?status=updated');
                        exit;
                    }

                    mysqli_stmt_close($stmt);
                }

                $error = 'Unable to update the student record.';
            }
        } else {
            $stmt = mysqli_prepare(
                $connect,
                'INSERT INTO students (first_name, middle_name, last_name)
                 VALUES (?, ?, ?)'
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sss', $first, $middle, $last);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: index.php?status=created');
                    exit;
                }

                mysqli_stmt_close($stmt);
            }

            $error = 'Unable to save the student record.';
        }
    } else {
        $error = 'Invalid form action.';
    }
}

if (isset($_GET['status'])) {
    $messages = [
        'created' => 'Student record saved successfully!',
        'updated' => 'Student record updated successfully!',
        'deleted' => 'Student record deleted successfully.',
    ];
    $message = $messages[$_GET['status']] ?? '';
}

if ($editingStudent === null && isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $stmt = mysqli_prepare(
            $connect,
            'SELECT id, first_name, middle_name, last_name
             FROM students WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                $editResult = mysqli_stmt_get_result($stmt);
                $editingStudent = mysqli_fetch_assoc($editResult) ?: null;
            }

            mysqli_stmt_close($stmt);
        }
    }

    if ($editingStudent === null) {
        $error = 'Student record not found.';
    }
}

$result = mysqli_query(
    $connect,
    'SELECT id, first_name, middle_name, last_name, created_at
     FROM students ORDER BY id DESC'
);

if (!$result) {
    http_response_code(500);
    exit('Unable to retrieve student records.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Record System</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 16px;
            font-family: Arial, sans-serif;
            background: #eef2f7;
            color: #1f2937;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 28px;
            border-radius: 14px;
            box-shadow: 0 5px 20px #00000012;
        }
        h1 { color: #174ea6; margin-top: 0; }
        .subtitle { color: #64748b; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }
        button {
            margin-top: 14px;
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            background: #174ea6;
            color: white;
            cursor: pointer;
        }
        .notice { padding: 10px; border-radius: 6px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .table-wrap { overflow-x: auto; }
        .actions { white-space: nowrap; }
        .inline-form { display: inline; }
        .action-link { margin-right: 8px; color: #174ea6; }
        .delete-button { margin-top: 0; background: #b42318; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        th { background: #174ea6; color: white; }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .container { padding: 18px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Student Record System</h1>
    <p class="subtitle">
        PHP + MySQL powered by Docker Compose
    </p>

    <?php if ($message): ?>
        <p class="notice success">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="notice error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($editingStudent): ?>
        <h2>Edit Student</h2>
        <form method="POST" action="index.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int) $editingStudent['id'] ?>">
            <div class="form-grid">
                <input name="first_name" placeholder="First Name"
                       maxlength="100" required
                       value="<?= htmlspecialchars($editingStudent['first_name'], ENT_QUOTES, 'UTF-8') ?>">
                <input name="middle_name" placeholder="Middle Name"
                       maxlength="100" required
                       value="<?= htmlspecialchars($editingStudent['middle_name'], ENT_QUOTES, 'UTF-8') ?>">
                <input name="last_name" placeholder="Last Name"
                       maxlength="100" required
                       value="<?= htmlspecialchars($editingStudent['last_name'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit">Save Changes</button>
            <a href="index.php">Cancel</a>
        </form>
    <?php endif; ?>

    <h2>Add Student</h2>
    <form method="POST" action="index.php">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <input name="first_name" placeholder="First Name"
                   maxlength="100" required>
            <input name="middle_name" placeholder="Middle Name"
                   maxlength="100" required>
            <input name="last_name" placeholder="Last Name"
                   maxlength="100" required>
        </div>
        <button type="submit">Save Student</button>
    </form>

    <h2>Student Records</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Last Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['middle_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="actions">
                        <a class="action-link"
                           href="index.php?edit=<?= (int) $row['id'] ?>">Edit</a>
                        <form class="inline-form" method="POST" action="index.php"
                              onsubmit="return confirm('Delete this student record?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="delete-button" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>