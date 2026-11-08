<?php

$host = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

$dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Unable to connect to the database. Please try again shortly.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message !== '') {
        $statement = $pdo->prepare(
            'INSERT INTO messages (message) VALUES (:message)'
        );

        $statement->execute([
            'message' => $message,
        ]);
    }

    header('Location: /');
    exit;
}

$statement = $pdo->query(
    'SELECT id, message, created_at FROM messages ORDER BY id DESC'
);

$messages = $statement->fetchAll();

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Docker Tutorial</title>
</head>
<body>

<h1>PHP Docker Tutorial</h1>

<h2>Leave a message</h2>

<form method="post">
    <label for="message">Message:</label>
    <input type="text" id="message" name="message" required>
    <button type="submit">Save message</button>
</form>

<h2>Saved messages</h2>

<?php if (count($messages) === 0): ?>

    <p>No messages have been saved yet.</p>

<?php else: ?>

    <ul>
        <?php foreach ($messages as $message): ?>
            <li>
                <?= escape($message['message']) ?>
                <small>
                    (<?= escape($message['created_at']) ?>)
                </small>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>

</body>
</html>