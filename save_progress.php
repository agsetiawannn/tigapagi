<?php
session_start();
include 'db.php';

// --- DEFINISI PATH AMAN UNTUK LOCALHOST ---
// PATH PROYEK ANDA: /client-progress/
$project_base_path = "/client-progress/"; 
// ------------------------------------------

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

// --- Ambil Progress Lama (Initial Load) ---
$stmt2 = $conn->prepare("SELECT onboard, presprint, sprint, client_view FROM client_progress WHERE client_id = ?");
$stmt2->bind_param("i", $client_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$progress_data = $res2->fetch_assoc();

// Dekode data untuk ditampilkan di form
$db_client_view_saved = $progress_data['client_view'] ?? 'none'; 
$onboard_data   = $progress_data ? json_decode($progress_data['onboard'], true)   : [];
$presprint_data = $progress_data ? json_decode($progress_data['presprint'], true) : [];
$sprint_data    = $progress_data ? json_decode($progress_data['sprint'], true)    : [];


// --- Handle Simpan dan Arsip ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ------------------------------------------
    // A. LOGIKA PENGARSIPAN
    // ------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'archive') {
        $phase = $_POST['phase_to_archive'];
        $phase_key = strtolower($phase); 
        
        $json_to_archive = $progress_data[$phase_key]; 
        
        $current_date_formatted = date("d F Y");
        $title_archive = "Arsip " . ucfirst($phase) . " (Selesai pada " . $current_date_formatted . ")";
        
        if ($json_to_archive && json_decode($json_to_archive) !== null && json_decode($json_to_archive, true) != []) {
            
            $stmt_archive = $conn->prepare("INSERT INTO project_history (client_id, title, phase, data_json) VALUES (?, ?, ?, ?)");
            $stmt_archive->bind_param("isss", $client_id, $title_archive, $phase_key, $json_to_archive);
            $stmt_archive->execute();
            
            $archive_id = $conn->insert_id; 

            $empty_json = json_encode([]);
            $stmt_clear = $conn->prepare("UPDATE client_progress SET $phase_key = ?, updated_at=NOW() WHERE client_id = ?");
            $stmt_clear->bind_param("si", $empty_json, $client_id);
            $stmt_clear->execute();

            $redirect_view = $_GET['view'] ?? 'all'; 
            header("Location: ".$_SERVER['PHP_SELF']."?client_id=".$client_id."&view=".$redirect_view."&archived=1&title=".urlencode($title_archive)."&archive_id=".$archive_id); 
            exit();

        } else {
            $redirect_view = $_GET['view'] ?? 'all'; 
            header("Location: ".$_SERVER['PHP_SELF']."?client_id=".$client_id."&view=".$redirect_view."&archive_error=".urlencode("Data ".ucfirst($phase)." kosong atau belum diisi."));
            exit();
        }
    }
    
    // ------------------------------------------
    // B. LOGIKA SIMPAN REGULER
    // ------------------------------------------
    else {
        
        // --- Preserve existing data for phases not present in the POST ---
        // If the form does not submit a phase (because the UI only showed one view),
        // we must not overwrite the stored JSON with an empty array. Merge behavior:
        $existing_onboard   = $progress_data['onboard']   ?? json_encode([]);
        $existing_presprint = $progress_data['presprint'] ?? json_encode([]);
        $existing_sprint    = $progress_data['sprint']    ?? json_encode([]);

        $onboard   = isset($_POST['onboard'])   ? json_encode($_POST['onboard'])   : $existing_onboard;
        $presprint = isset($_POST['presprint']) ? json_encode($_POST['presprint']) : $existing_presprint;
        $sprint    = isset($_POST['sprint'])    ? json_encode($_POST['sprint'])    : $existing_sprint;
        $client_view = $_POST['client_view'] ?? $progress_data['client_view'] ?? 'none'; 
        
        $stmt3 = $conn->prepare("SELECT id FROM client_progress WHERE client_id = ?");
        $stmt3->bind_param("i", $client_id);
        $stmt3->execute();
        $exists = $stmt3->get_result();

        if ($exists->num_rows > 0) {
            $stmt4 = $conn->prepare("UPDATE client_progress SET onboard=?, presprint=?, sprint=?, client_view=?, updated_at=NOW() WHERE client_id=?");
            $stmt4->bind_param("ssssi", $onboard, $presprint, $sprint, $client_view, $client_id); 
            $stmt4->execute();
        } else {
            $stmt5 = $conn->prepare("INSERT INTO client_progress (client_id, onboard, presprint, sprint, client_view) VALUES (?, ?, ?, ?, ?)");
            $stmt5->bind_param("issss", $client_id, $onboard, $presprint, $sprint, $client_view);
            $stmt5->execute();
        }

        // Muat ulang data
        $stmt2->execute(); $res2 = $stmt2->get_result(); $progress_data = $res2->fetch_assoc();
        $db_client_view_saved = $progress_data['client_view'] ?? 'none'; 
        $onboard_data   = $progress_data ? json_decode($progress_data['onboard'], true)   : [];
        $presprint_data = $progress_data ? json_decode($progress_data['presprint'], true) : [];
        $sprint_data    = $progress_data ? json_decode($progress_data['sprint'], true)    : [];
        
        $display_success = '<div class="success">Progress berhasil disimpan.</div>';
    }
}

// Menampilkan pesan sukses / error dari URL (GET)
$view = $_GET['view'] ?? 'onboard'; // 🔥 Default ke 'onboard'
$display_success = '';

if (isset($_GET['archived']) && isset($_GET['title'])) {
    $title = htmlspecialchars(urldecode($_GET['title']));
    // Link Lihat Arsip Klien Dihapus per permintaan Anda
    $display_success = '<div class="success">Berhasil diarsipkan! Data fase aktif telah direset. Arsip: <b>' . $title . '</b></div>'; 
    
} elseif (isset($_GET['archive_error'])) {
    $error = htmlspecialchars(urldecode($_GET['archive_error']));
    $display_success = '<div class="error">Gagal Arsip: ' . $error . '</div>';
} 

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Progress Klien - <?= htmlspecialchars($client['name']) ?></title>
<style>
/* CSS */
body { font-family: Arial, sans-serif; margin:20px; background:#f5f5f5; }
.container { background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 5px rgba(0,0,0,0.1); }
h2 { margin-top:0; border-bottom:2px solid #007bff; padding-bottom:5px; }
table { border-collapse:collapse; width:100%; margin-bottom:20px; }
th,td { border:1px solid #ddd; padding:8px; text-align:left; }
th { background:#007bff; color:#fff; }
tr:nth-child(even){background:#fafafa;}
input[type="date"],select { padding:6px; width:95%; }
textarea { width: 98%; padding: 6px; box-sizing: border-box; resize: vertical; min-height: 50px; } 
button { background:#28a745; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
button:hover { background:#1e7e34; }
.filter a { margin-right:10px; text-decoration:none; color:#007bff; font-weight:bold; }
.filter a.active { text-decoration:underline; }
.success { background:#d1e7dd; color:#0f5132; padding:10px; border-radius:4px; margin-bottom:10px; } /* Gaya pesan sukses */
.error { background:#f8d7da; color:#842029; padding:10px; border-radius:4px; margin-bottom:10px; }
.archive-btn { background: #e9c46a; color: #333; }
.archive-btn:hover { background: #d4ac57; }
</style>
</head>
<body>
<div class="container">
    <h2>Progress Klien: <?= htmlspecialchars($client['name']) ?></h2>
    <a href="admin_dashboard.php">← Kembali ke Dashboard</a>
    
    <?= $display_success ?> 
    
    <div class="filter">
        <a href="?client_id=<?= $client_id ?>&view=onboard" class="<?= $view=='onboard'?'active':'' ?>">On Board</a>
        <a href="?client_id=<?= $client_id ?>&view=presprint" class="<?= $view=='presprint'?'active':'' ?>">Pre-Sprint</a>
        <a href="?client_id=<?= $client_id ?>&view=sprint" class="<?= $view=='sprint'?'active':'' ?>">Sprint Week</a>
    </div>

    <form method="post">
        
        <h3 style="margin-top:20px;">Fokus Tampilan Klien</h3>
        <select name="client_view" style="width: 300px; margin-bottom: 20px;">
            <option value="none" <?= $db_client_view_saved=='none'?'selected':'' ?>>Admin Belum Menentukan</option>
            <option value="onboard" <?= $db_client_view_saved=='onboard'?'selected':'' ?>>On Board</option>
            <option value="presprint" <?= $db_client_view_saved=='presprint'?'selected':'' ?>>Pre-Sprint</option>
            <option value="sprint" <?= $db_client_view_saved=='sprint'?'selected':'' ?>>Sprint Week</option>
        </select>
        

        <?php if ($view=='onboard'): ?> <h3>On Board</h3>
        <table>
            <tr><th>Tahapan</th><th>Tanggal</th><th>Status</th><th>Deskripsi</th></tr> 
            <?php
            $onboard_steps = ['Kick Off','Roadmap & Visual Concept Development','Present'];
            foreach ($onboard_steps as $i => $step):
                $saved = $onboard_data[$i] ?? ['date'=>'','status'=>'pending', 'description'=>''];
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
                <td>
                    <textarea name="onboard[<?= $i ?>][description]" placeholder="Deskripsi untuk klien"><?= htmlspecialchars($saved['description']) ?></textarea>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if ($view=='presprint'): ?> <h3>Pre-Sprint</h3>
        <table>
            <tr><th>Tahapan</th><th>Tanggal</th><th>Status</th><th>Deskripsi</th></tr> 
            <?php
            $pre_steps = ['Visit Concept','Site Visit Date Option','Visit Day'];
            foreach ($pre_steps as $i => $step):
                $saved = $presprint_data[$i] ?? ['date'=>'','status'=>'pending', 'description'=>''];
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
                <td>
                    <textarea name="presprint[<?= $i ?>][description]" placeholder="Deskripsi untuk klien"><?= htmlspecialchars($saved['description']) ?></textarea>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if ($view=='sprint'): ?> <h3>Sprint Week</h3>
        <table>
            <tr><th>Minggu</th><th>Tanggal</th><th>Status</th><th>Deskripsi</th></tr> 
            <?php for ($i=0;$i<4;$i++): 
                $saved = $sprint_data[$i] ?? ['date'=>'','status'=>'pending', 'description'=>''];
            ?>
            <tr>
                <td>Minggu ke-<?= $i+1 ?></td>
                <td><input type="date" name="sprint[<?= $i ?>][date]" value="<?= htmlspecialchars($saved['date']) ?>"></td>
                <td>
                    <select name="sprint[<?= $i ?>][status]">
                        <option value="pending"   <?= $saved['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="ongoing"   <?= $saved['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $saved['status']=='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </td>
                <td>
                    <textarea name="sprint[<?= $i ?>][description]" placeholder="Deskripsi untuk klien"><?= htmlspecialchars($saved['description']) ?></textarea>
                </td>
            </tr>
            <?php endfor; ?>
        </table>
        <?php endif; ?>

        <button type="submit">Simpan Progress</button>
    </form>
    
    <form method="post" style="margin-top: 30px;">
        <input type="hidden" name="action" value="archive">
        
        <h3 style="border-bottom: 0;">Arsipkan Progres Saat Ini</h3>
        <p style="margin-top: 5px; margin-bottom: 10px;">Pilih fase yang sudah selesai dan siap diarsipkan:</p>
        
        <select name="phase_to_archive" required style="width: 300px; margin-right: 10px;">
            <option value="sprint">Sprint Week</option>
        </select>
        
        <button type="submit" class="archive-btn" 
                onclick="return confirm('PERINGATAN! Tindakan ini akan mengarsipkan data fase Sprint Week dan MENGOSONGKAN data tersebut di tampilan aktif klien. Lanjutkan?')"
        >
            Arsipkan Fase & Reset
        </button>
    </form>
</div>
</body>
</html>