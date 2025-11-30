<?php
session_start();
include 'db.php';

// --- Cek Login Client ---
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = intval($_SESSION['client_id']);
$name = htmlspecialchars($_SESSION['client_name'] ?? 'Client');

// --- PERBAIKAN: Hitung Path Aman untuk Tautan Kembali ---
// Ini akan mengambil path file saat ini (misal: /proyek_anda/client_progress.php)
$current_script_path = strtok($_SERVER["REQUEST_URI"], '?'); 
// -----------------------------------------------------

// --- Tampilan History Spesifik (Jika ada parameter history_id di URL) ---
$history_mode = false;
$archived_detail = [];

if (isset($_GET['history_id'])) {
    $history_mode = true;
    $history_id = intval($_GET['history_id']);
    
    // Ambil detail arsip dari tabel project_history
    $stmt_hist = $conn->prepare("SELECT title, phase, data_json, archived_at FROM project_history WHERE id = ? AND client_id = ?");
    $stmt_hist->bind_param("ii", $history_id, $client_id);
    $stmt_hist->execute();
    $res_hist = $stmt_hist->get_result();
    $archived_detail = $res_hist->fetch_assoc();

    if ($archived_detail) {
        $history_title = htmlspecialchars($archived_detail['title']);
        $history_phase = htmlspecialchars(ucfirst($archived_detail['phase']));
        $history_data = json_decode($archived_detail['data_json'], true);
    } else {
        $history_mode = false; // Jika ID arsip tidak valid, kembali ke mode normal
    }
}


// --- Ambil data progres detail dan preferensi tampilan (Current Progress) ---
$stmt = $conn->prepare("
    SELECT onboard, presprint, sprint, client_view
    FROM client_progress
    WHERE client_id = ?
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$progress_data = $res->fetch_assoc();

// --- Logika Data PHP ---
$section_phase_title = "Proyek Progress"; 
$display_title = "Proyek Progress";

if (!$progress_data) {
    $progress_message = "Progres proyek Anda akan segera diunggah. Silakan hubungi admin jika ada pertanyaan.";
    $client_view = 'none';
    $sprint_data = []; 
} else {
    // Dekode data JSON
    $onboard_data   = json_decode($progress_data['onboard'], true)   ?? [];
    $presprint_data = json_decode($progress_data['presprint'], true) ?? [];
    $sprint_data    = json_decode($progress_data['sprint'], true)    ?? [];
    $client_view    = $progress_data['client_view'];

    // Tentukan data, display title, dan phase title
    $display_data = [];
    $steps = [];

    switch ($client_view) {
        case 'onboard':
            $display_data = $onboard_data;
            $display_title = "Progress On Board";
            $section_phase_title = "On Board Phase"; 
            $steps = ['Kick Off','Roadmap & Visual Concept Development','Present'];
            break;
        case 'presprint':
            $display_data = $presprint_data;
            $display_title = "Progress Pre-Sprint";
            $section_phase_title = "Pre-Sprint Phase"; 
            $steps = ['Visit Concept','Site Visit Date Option','Visit Day'];
            break;
        case 'sprint':
            $display_data = $sprint_data;
            $display_title = "Progress Sprint Week";
            $section_phase_title = "Sprint Week Phase"; 
            break;
        default:
            $progress_message = "Admin belum menentukan fokus tampilan progres Anda.";
            $client_view = 'none';
    }
}
$current_month = date("F Y");

// --- Ambil Daftar Arsip (History) dari project_history ---
$stmt_history = $conn->prepare("SELECT id, title, phase, data_json, archived_at FROM project_history WHERE client_id = ? ORDER BY archived_at DESC");
$stmt_history->bind_param("i", $client_id);
$stmt_history->execute();
$history_list = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progress - <?= $name ?></title>
    <style>
        /* CSS */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #eee; color: #222; }
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; background-color: #fff; border-radius: 8px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 15px; margin-bottom: 25px; border-bottom: 1px solid #222; }
        .header h1 { font-size: 32px; font-weight: bold; margin: 0; }
        .header p { font-size: 20px; font-weight: normal; color: #222; margin: 0; }
        
        /* Gaya Kartu Sprint */
        .sprint-section { padding-bottom: 20px; }
        .sprint-section h2 { font-size: 24px; font-weight: normal; border-bottom: 1px solid #222; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .card-container { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .card { background-color: #ccc; border-radius: 8px; padding: 20px; text-align: center; flex: 1 1 200px; min-width: 200px; position: relative; cursor: pointer; }
        .card h3 { margin-top: 0; color: #222; font-size: 20px; }
        .card .see-more { display: inline-block; background-color: #aaa; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 12px; margin-top: 10px; cursor: pointer; }
        
        /* Gaya Arsip (Achieved) */
        .archive-container { margin-top: 30px; border-top: 1px solid #222; padding-top: 20px; }
        .archive-container h2 { font-size: 24px; font-weight: normal; margin-bottom: 15px; }
        .archive-card { 
            background-color: #aaa; 
            color: #222;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 16px;
            text-align: center;
            margin-bottom: 10px;
            flex: 1 1 150px;
            cursor: pointer; 
            transition: background 0.2s;
            text-decoration: none; 
            display: block;
        }
        .archive-card:hover { background-color: #999; }

        /* OVERLAYS */
        .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: #888; border-radius: 8px; padding: 20px; box-sizing: border-box; text-align: left; display: none; z-index: 10; }
        .overlay h4, .overlay p { color: #eee; margin: 5px 0; }
        .close-btn { position: absolute; bottom: 5px; right: 10px; color: #eee; font-weight: bold; font-size: 20px; cursor: pointer; }
        .download-link { color: #00ff00; text-decoration: underline; display: block; margin-top: 10px; }

        /* UNIVERSAL DETAIL OVERLAY */
        .universal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .overlay-content { background: white; padding: 25px; border-radius: 8px; max-width: 80%; min-width: 300px; box-shadow: 0 0 20px rgba(0, 0, 0, 0.5); position: relative; }
        .overlay-content h4 { margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .overlay-content button { background: #dc3545; color: white; border: none; padding: 7px 12px; border-radius: 3px; cursor: pointer; float: right; margin-top: 15px; }

        /* Gaya Tabel */
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #555; color: white; }
        tr:nth-child(even){background-color: #f2f2f2;}
        a.logout { display: block; margin-top: 30px; text-align: right; color: #0066cc; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    
    <?php if ($history_mode && $archived_detail): ?>

        <div class="message" style="background: #e9c46a; color: #333; margin-bottom: 25px;">
            Anda sedang melihat arsip proyek.
        </div>
        
        <h2>Arsip Proyek: <?= $history_title ?></h2>
        <p>Fase: **<?= $history_phase ?>** | Diarsipkan pada: <?= date('d F Y H:i', strtotime($archived_detail['archived_at'])) ?></p>
        <hr>
        
        <table>
            <tr>
                <th>Tahapan</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Deskripsi</th>
            </tr>
            <?php 
            $steps_map = [
                'onboard' => ['Kick Off','Roadmap & Visual Concept Development','Present'],
                'presprint' => ['Visit Concept','Site Visit Date Option','Visit Day'],
                'sprint' => ['Minggu ke-1','Minggu ke-2','Minggu ke-3','Minggu ke-4']
            ];
            $current_steps = $steps_map[$archived_detail['phase']] ?? [];

            foreach ($history_data as $i => $item): 
                $step_name = $current_steps[$i] ?? 'Item ' . ($i + 1);
            ?>
            <tr>
                <td><?= htmlspecialchars($step_name) ?></td>
                <td><?= htmlspecialchars($item['date'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars(ucwords($item['status'] ?? 'pending')) ?></strong></td>
                <td><?= htmlspecialchars($item['description'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <a href="<?= $current_script_path ?>?client_id=<?= $client_id ?>">← Kembali ke Progres Aktif</a>

    <?php else: ?>

        <div class="header">
            <h1>Hi <?= $name ?> :)</h1>
            <p>Here's Your <?= $display_title ?></p> 
        </div>
        
        <div class="sprint-section">
            <h2>
                <?= $section_phase_title ?> 
                <?php if ($client_view == 'sprint'): ?>
                    <span><?= $current_month ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <?php if ($client_view == 'none'): ?>

            <div class="message">
                <?= $progress_message ?>
            </div>

        <?php elseif ($client_view == 'sprint'): ?>

            <div class="card-container">
                <?php 
                $current_week = 1;
                foreach ($display_data as $i => $item): 
                    $step_name = "WEEK " . $current_week++;
                    $description = htmlspecialchars($item['description'] ?? 'Deskripsi belum tersedia.');
                    $download_url = htmlspecialchars($item['download_url'] ?? ''); 
                    $card_id = "week-" . $i;
                ?>
                <div class="card" id="<?= $card_id ?>">
                    <h3><?= $step_name ?></h3>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">Status: <?= htmlspecialchars(ucwords($item['status'] ?? 'Pending')) ?></p>
                    
                    <div class="overlay" data-card-id="<?= $card_id ?>">
                        <h4><?= $step_name ?></h4>
                        <p style="font-size: 12px; margin-bottom: 10px;"><?= $description ?></p>
                        <?php if ($download_url): ?>
                            <a href="<?= $download_url ?>" target="_blank" class="download-link">Download your Content preview</a>
                        <?php endif; ?>
                        <span class="close-btn">&times;</span>
                    </div>
                    
                    <div class="see-more" onclick="showOverlay('<?= $card_id ?>')">See More</div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="archive-container">
                <h2>Archived</h2>
                <div class="card-container">
                    <?php if ($history_list): ?>
                        <?php foreach ($history_list as $archive): 
                            $archive_id = htmlspecialchars($archive['id']);
                            $display_title_short = ucfirst($archive['phase']) . ' - ' . date('M Y', strtotime($archive['archived_at']));
                        ?>
                            <a 
                                href="?client_id=<?= $client_id ?>&history_id=<?= $archive_id ?>"
                                class="archive-card" 
                                title="<?= htmlspecialchars($archive['title']) ?>"
                            >
                                <?= htmlspecialchars($display_title_short) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="width: 100%; text-align: center; color: #555;">Belum ada riwayat progres yang diarsipkan.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>

            <table>
                <tr>
                    <th>Tahapan</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Detail</th> 
                </tr>
                <?php 
                foreach ($display_data as $i => $item): 
                    $step_name = ($steps[$i] ?? 'Tahapan Tidak Dikenal');
                    $description = htmlspecialchars($item['description'] ?? 'Deskripsi belum tersedia.');
                ?>
                <tr>
                    <td><?= htmlspecialchars($step_name) ?></td>
                    <td><?= htmlspecialchars($item['date'] ?? '-') ?></td>
                    <td><strong><?= htmlspecialchars(ucwords($item['status'] ?? 'pending')) ?></strong></td>
                    <td>
                        <a href="javascript:void(0);" 
                        class="see-more" 
                        onclick="showUniversalOverlay('<?= $step_name ?>', '<?= $description ?>')">
                        See More
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <div class="archive-container">
                <h2>Archived</h2>
                <div class="card-container">
                    <?php if ($history_list): ?>
                        <?php foreach ($history_list as $archive): 
                            $archive_id = htmlspecialchars($archive['id']);
                            $display_title_short = ucfirst($archive['phase']) . ' - ' . date('M Y', strtotime($archive['archived_at']));
                        ?>
                            <a 
                                href="?client_id=<?= $client_id ?>&history_id=<?= $archive_id ?>"
                                class="archive-card" 
                                title="<?= htmlspecialchars($archive['title']) ?>"
                            >
                                <?= htmlspecialchars($display_title_short) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="width: 100%; text-align: center; color: #555;">Belum ada riwayat progres yang diarsipkan.</p>
                    <?php endif; ?>
                </div>
            </div>


        <?php endif; ?>

        <a href="logout.php" class="logout">Logout</a>

    <?php endif; ?>
    
</div>

<div id="universal-detail-overlay" class="universal-overlay" onclick="this.style.display='none'">
    <div class="overlay-content" onclick="event.stopPropagation()">
        <h4 id="overlay-step-title">Detail Tahapan</h4>
        <p id="overlay-description-content">Deskripsi akan ditampilkan di sini.</p>
        <button onclick="document.getElementById('universal-detail-overlay').style.display='none'">Tutup</button>
    </div>
</div>

<script>
    // FUNGSI UNTUK KARTU SPRINT WEEK (Overlay di dalam kartu)
    function showOverlay(cardId) {
        document.querySelectorAll('.overlay').forEach(overlay => {
            overlay.style.display = 'none';
        });

        const card = document.getElementById(cardId);
        const overlay = card.querySelector('.overlay');
        overlay.style.display = 'block';

        overlay.style.position = 'absolute';
        overlay.style.top = '0';
        overlay.style.left = '0';
    }
    
    // FUNGSI BARU UNTUK TABEL (Overlay Universal di tengah layar)
    function showUniversalOverlay(title, description) {
        document.getElementById('overlay-step-title').innerText = title;
        document.getElementById('overlay-description-content').innerText = description;
        document.getElementById('universal-detail-overlay').style.display = 'flex';
    }

    // Menangani tombol close (X) pada kartu Sprint
    document.querySelectorAll('.close-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            this.closest('.overlay').style.display = 'none';
        });
    });
</script>

</body>
</html>
