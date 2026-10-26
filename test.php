<?php
$connect = mysqli_connect(
    getenv('DB_HOST') ?: 'db',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    getenv('DB_NAME') ?: 'db_docker'
);

if (!$connect) {
    http_response_code(500);
    exit('Database connection failed.');
}

$result = mysqli_query(
    $connect,
    'SELECT first_name, middle_name, last_name
     FROM students ORDER BY id ASC LIMIT 1'
);

if (!$result) {
    http_response_code(500);
    exit('Unable to read student records.');
}

$record = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Record Test</title>
</head>
<body>
    <h1>Database Reader Test</h1>

    <?php if (!$record): ?>
        <p>No student records found. Add a record on the main page.</p>
    <?php else: ?>
        <p>First Name:
            <?= htmlspecialchars($record['first_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p>Middle Name:
            <?= htmlspecialchars($record['middle_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p>Last Name:
            <?= htmlspecialchars($record['last_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>
</body>
</html>