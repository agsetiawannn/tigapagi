<?php
session_start();
include 'db.php';

// Validasi login client
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = intval($_SESSION['client_id']);
$name = htmlspecialchars($_SESSION['client_name'] ?? 'Client');

// --- Ambil data progres detail dan preferensi tampilan ---
$stmt = $conn->prepare("
    SELECT onboard, presprint, sprint, client_view
    FROM client_progress
    WHERE client_id = ?
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$progress_data = $res->fetch_assoc();

if (!$progress_data) {
    // Tampilkan pesan jika belum ada progres detail yang dimasukkan admin
    $progress_message = "Progres proyek Anda akan segera diunggah. Silakan hubungi admin jika ada pertanyaan.";
    $client_view = 'none'; // Set view ke 'none' untuk tampilan kosong
} else {
    // Dekode data JSON
    $onboard_data   = json_decode($progress_data['onboard'], true)   ?? [];
    $presprint_data = json_decode($progress_data['presprint'], true) ?? [];
    $sprint_data    = json_decode($progress_data['sprint'], true)    ?? [];
    $client_view    = $progress_data['client_view']; // Ambil preferensi tampilan admin

    // Tentukan data yang akan ditampilkan
    $display_data = [];
    $display_title = "";
    $steps = [];

    switch ($client_view) {
        case 'onboard':
            $display_data = $onboard_data;
            $display_title = "Progress Tahap On Board";
            $steps = ['Kick Off','Roadmap & Visual Concept Development','Present'];
            break;
        case 'presprint':
            $display_data = $presprint_data;
            $display_title = "Progress Tahap Pre-Sprint";
            $steps = ['Visit Concept','Site Visit Date Option','Visit Day'];
            break;
        case 'sprint':
            $display_data = $sprint_data;
            $display_title = "Progress Tahap Sprint Week";
            // Langkah Sprint akan diberi nama "Minggu ke-X" di HTML
            break;
        default:
            $progress_message = "Admin belum menentukan fokus tampilan progres Anda.";
            $client_view = 'none';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progress - <?= $name ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
        h2 { margin-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even){background-color: #f2f2f2;}
        .info { margin-top: 15px; color: #555; font-size: 14px; }
        .message { padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404; border-radius: 4px; }
        a { color: #0066cc; text-decoration: none; }
    </style>
</head>
<body>

<h2>Progres Proyek Anda</h2>
<p>Halo, **<?= $name ?>**.</p>
<hr>

<?php if ($client_view == 'none'): ?>

    <div class="message">
        <?= $progress_message ?>
    </div>

<?php else: ?>

    <h3><?= $display_title ?></h3>

    <table>
        <tr>
            <th>Tahapan</th>
            <th>Tanggal</th>
            <th>Status</th>
        </tr>
        <?php 
        // Looping untuk menampilkan data yang sudah difilter
        foreach ($display_data as $i => $item): 
            $step_name = ($client_view == 'sprint') ? "Minggu ke-" . ($i + 1) : ($steps[$i] ?? 'Tahapan Tidak Dikenal');
        ?>
        <tr>
            <td><?= htmlspecialchars($step_name) ?></td>
            <td><?= htmlspecialchars($item['date'] ?? '-') ?></td>
            <td><strong><?= htmlspecialchars(ucwords($item['status'] ?? 'pending')) ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </table>

<?php endif; ?>

<hr>
<a href="logout.php">Logout</a>

</body>
</html>