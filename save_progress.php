<?php
session_start();
include 'db.php';

// --- Cek Login Admin ---
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// --- Validasi ID Klien ---
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id <= 0) {
    die("Klien tidak valid.");
}

// --- Ambil Nama Klien ---
$stmt = $conn->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$client = $res->fetch_assoc();
if (!$client) die("Data klien tidak ditemukan.");

// --- Ambil Progress Lama (jika ada) ---
// Perbaikan: Menambahkan client_view di SELECT query
$stmt2 = $conn->prepare("SELECT onboard, presprint, sprint, client_view FROM client_progress WHERE client_id = ?");
$stmt2->bind_param("i", $client_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$progress_data = $res2->fetch_assoc();

$onboard_data   = $progress_data ? json_decode($progress_data['onboard'], true)   : [];
$presprint_data = $progress_data ? json_decode($progress_data['presprint'], true) : [];
$sprint_data    = $progress_data ? json_decode($progress_data['sprint'], true)    : [];
// Nilai view saat ini di database (tidak digunakan untuk tampilan admin, hanya referensi)
$db_client_view = $progress_data['client_view'] ?? 'onboard'; 


$success = "";

// --- Simpan Perubahan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onboard   = json_encode($_POST['onboard'] ?? []);
    $presprint = json_encode($_POST['presprint'] ?? []);
    $sprint    = json_encode($_POST['sprint'] ?? []);

    // 🔥 PERBAIKAN: Ambil nilai client_view dari hidden input POST
    // Jika admin sedang di view=all, maka kita tidak menyimpan "all" ke klien, kita simpan yang terakhir.
    $client_view = ($_POST['client_view'] == 'all') ? $db_client_view : $_POST['client_view'];
    
    // Fallback jika 'view' tidak terdefinisi
    if(empty($client_view)) $client_view = 'onboard';


    $stmt3 = $conn->prepare("SELECT id FROM client_progress WHERE client_id = ?");
    $stmt3->bind_param("i", $client_id);
    $stmt3->execute();
    $exists = $stmt3->get_result();

    if ($exists->num_rows > 0) {
        $stmt4 = $conn->prepare("UPDATE client_progress 
                                SET onboard=?, presprint=?, sprint=?, client_view=?, updated_at=NOW() 
                                WHERE client_id=?");
        // URUTAN HARUS SAMA: s s s s i (4 string, 1 integer)
        $stmt4->bind_param("ssssi", $onboard, $presprint, $sprint, $client_view, $client_id); 
        $stmt4->execute();
    } else {
        $stmt5 = $conn->prepare("INSERT INTO client_progress (client_id, onboard, presprint, sprint, client_view) 
                                VALUES (?, ?, ?, ?, ?)");
        // URUTAN HARUS SAMA: i s s s s (1 integer, 4 string)
        $stmt5->bind_param("issss", $client_id, $onboard, $presprint, $sprint, $client_view);
        $stmt5->execute();
    }

    // Tentukan view untuk redirect agar filter tetap terpilih
    $redirect_view = $_GET['view'] ?? 'all'; 

    $success = "Progress berhasil disimpan.";
    header("Location: ".$_SERVER['PHP_SELF']."?client_id=".$client_id."&view=".$redirect_view."&saved=1");
    exit();
}

// --- Filter tampilan ---
// Menambahkan pesan success ke view
$view = $_GET['view'] ?? 'all';
$success_msg = isset($_GET['saved']) ? '<div style="background:#d1e7dd; color:#0f5132; padding:10px; border-radius:4px; margin-bottom:10px;">Progress berhasil disimpan.</div>' : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Progress Klien - <?= htmlspecialchars($client['name']) ?></title>
<style>
body { font-family: Arial, sans-serif; margin:20px; background:#f5f5f5; }
.container { background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 5px rgba(0,0,0,0.1); }
h2 { margin-top:0; border-bottom:2px solid #007bff; padding-bottom:5px; }
table { border-collapse:collapse; width:100%; margin-bottom:20px; }
th,td { border:1px solid #ddd; padding:8px; text-align:left; }
th { background:#007bff; color:#fff; }
tr:nth-child(even){background:#fafafa;}
input[type="date"],select { padding:6px; width:95%; }
button { background:#28a745; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
button:hover { background:#1e7e34; }
.filter a { margin-right:10px; text-decoration:none; color:#007bff; font-weight:bold; }
.filter a.active { text-decoration:underline; }
</style>
</head>
<body>
<div class="container">
    <h2>Progress Klien: <?= htmlspecialchars($client['name']) ?></h2>
    <a href="admin_dashboard.php">← Kembali ke Dashboard</a>
    
    <?= $success_msg ?> <div class="filter">
        <a href="?client_id=<?= $client_id ?>&view=all" class="<?= $view=='all'?'active':'' ?>">Semua</a>
        <a href="?client_id=<?= $client_id ?>&view=onboard" class="<?= $view=='onboard'?'active':'' ?>">On Board</a>
        <a href="?client_id=<?= $client_id ?>&view=presprint" class="<?= $view=='presprint'?'active':'' ?>">Pre-Sprint</a>
        <a href="?client_id=<?= $client_id ?>&view=sprint" class="<?= $view=='sprint'?'active':'' ?>">Sprint Week</a>
    </div>

    <form method="post">
        
        <input type="hidden" name="client_view" value="<?= htmlspecialchars($_GET['view'] ?? 'onboard') ?>">

        <?php if ($view=='all' || $view=='onboard'): ?>
        <h3>On Board</h3>
        <table>
            <tr><th>Tahapan</th><th>Tanggal</th><th>Status</th></tr>
            <?php
            $onboard_steps = ['Kick Off','Roadmap & Visual Concept Development','Present'];
            foreach ($onboard_steps as $i => $step):
                $saved = $onboard_data[$i] ?? ['date'=>'','status'=>'pending'];
            ?>
            <tr>
                <td><?= $step ?></td>
                <td><input type="date" name="onboard[<?= $i ?>][date]" value="<?= htmlspecialchars($saved['date']) ?>"></td>
                <td>
                    <select name="onboard[<?= $i ?>][status]">
                        <option value="pending"   <?= $saved['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="ongoing"   <?= $saved['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $saved['status']=='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if ($view=='all' || $view=='presprint'): ?>
        <h3>Pre-Sprint</h3>
        <table>
            <tr><th>Tahapan</th><th>Tanggal</th><th>Status</th></tr>
            <?php
            $pre_steps = ['Visit Concept','Site Visit Date Option','Visit Day'];
            foreach ($pre_steps as $i => $step):
                $saved = $presprint_data[$i] ?? ['date'=>'','status'=>'pending'];
            ?>
            <tr>
                <td><?= $step ?></td>
                <td><input type="date" name="presprint[<?= $i ?>][date]" value="<?= htmlspecialchars($saved['date']) ?>"></td>
                <td>
                    <select name="presprint[<?= $i ?>][status]">
                        <option value="pending"   <?= $saved['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="ongoing"   <?= $saved['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $saved['status']=='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if ($view=='all' || $view=='sprint'): ?>
        <h3>Sprint Week</h3>
        <table>
            <tr><th>Tanggal</th><th>Minggu</th><th>Status</th></tr>
            <?php for ($i=0;$i<4;$i++):
                $saved = $sprint_data[$i] ?? ['date'=>'','status'=>'pending'];
            ?>
            <tr>
                <td><input type="date" name="sprint[<?= $i ?>][date]" value="<?= htmlspecialchars($saved['date']) ?>"></td>
                <td>Minggu ke-<?= $i+1 ?></td>
                <td>
                    <select name="sprint[<?= $i ?>][status]">
                        <option value="pending"   <?= $saved['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="ongoing"   <?= $saved['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $saved['status']=='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </td>
            </tr>
            <?php endfor; ?>
        </table>
        <?php endif; ?>

        <button type="submit">Simpan Progress</button>
    </form>
</div>
</body>
</html>