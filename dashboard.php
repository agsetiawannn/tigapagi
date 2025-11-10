<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Biarkan aktif sebentar untuk melihat error jika ada
include 'db.php'; 

if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = intval($_SESSION['client_id']);
$name = htmlspecialchars($_SESSION['client_name'] ?? 'Client');

// Query 1: Current Progress (Hanya status ONGOING)
$current = $conn->query("
    SELECT title, description, created_at 
    FROM progress 
    WHERE client_id = $client_id AND status = 'ongoing' 
    ORDER BY created_at DESC
"); 

// Query 2: History Progress (Hanya status COMPLETED)
$history = $conn->query("
    SELECT title, description, updated_at 
    FROM progress 
    WHERE client_id = $client_id AND status = 'completed' 
    ORDER BY updated_at DESC
"); 
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= $name ?></title>
    <style>body { font-family: sans-serif; margin: 20px; }</style>
</head>
<body>

<h2>Selamat datang, <?= $name ?></h2>
<a href="logout.php">Logout</a>

<h3>Current Progress</h3>
<?php if ($current && $current->num_rows > 0): ?>
    <ul>
        <?php while ($row = $current->fetch_assoc()): ?>
            <li>
                <b><?= htmlspecialchars($row['title']) ?></b> — 
                <?= htmlspecialchars($row['description']) ?> 
                (<?= htmlspecialchars($row['created_at']) ?>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>Tidak ada progres yang sedang berlangsung.</p>
<?php endif; ?>

<h3>History Progress</h3>
<?php if ($history && $history->num_rows > 0): ?>
    <ul>
        <?php while ($row = $history->fetch_assoc()): ?>
            <li>
                <b><?= htmlspecialchars($row['title']) ?></b> — 
                <?= htmlspecialchars($row['description']) ?> 
                (<?= htmlspecialchars($row['updated_at']) ?>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>Belum ada progres yang selesai.</p>
<?php endif; ?>

</body>
</html>