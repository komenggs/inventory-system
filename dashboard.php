<?php
require_once '../config.php';
requireLogin();
$pageTitle = 'Dashboard';

// Stats
$totalBarang  = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();
$totalStok    = $pdo->query("SELECT SUM(stok) FROM barang")->fetchColumn() ?? 0;
$stokMenupis  = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok <= stok_minimum AND stok > 0")->fetchColumn();
$stokHabis    = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok = 0")->fetchColumn();

// Transaksi bulan ini
$txMasukBulan  = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE tipe='masuk'  AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
$txKeluarBulan = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE tipe='keluar' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();

// 8 transaksi terbaru
$recentTx = $pdo->query("
    SELECT t.*, u.nama as user_nama, COUNT(dt.id) as jumlah_item
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    GROUP BY t.id
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

// Barang stok menipis / habis
$stokAlert = $pdo->query("
    SELECT b.*, k.nama_kategori
    FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id
    WHERE b.stok <= b.stok_minimum
    ORDER BY b.stok ASC LIMIT 6
")->fetchAll();

include '../includes/header.php';
?>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
        <div>
            <div class="stat-value"><?= number_format($totalBarang) ?></div>
            <div class="stat-label">Total Jenis Barang</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-layer-group"></i></div>
        <div>
            <div class="stat-value"><?= number_format($totalStok) ?></div>
            <div class="stat-label">Total Stok Keseluruhan</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-value"><?= number_format($stokMenupis) ?></div>
            <div class="stat-label">Stok Menipis</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-ban"></i></div>
        <div>
            <div class="stat-value"><?= number_format($stokHabis) ?></div>
            <div class="stat-label">Stok Habis</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px">

<!-- TRANSAKSI TERBARU -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-clock-rotate-left"></i> Transaksi Terbaru</div>
        <a href="riwayat.php" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tipe</th>
                    <th>Tanggal</th>
                    <th>Item</th>
                    <th>Oleh</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentTx): foreach ($recentTx as $tx): ?>
            <tr>
                <td><code style="font-size:.78rem"><?= htmlspecialchars($tx['no_transaksi']) ?></code></td>
                <td>
                    <?php if ($tx['tipe'] === 'masuk'): ?>
                    <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Masuk</span>
                    <?php else: ?>
                    <span class="badge badge-info"><i class="fas fa-arrow-up"></i> Keluar</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($tx['tanggal'])) ?></td>
                <td><?= $tx['jumlah_item'] ?> item</td>
                <td><?= htmlspecialchars($tx['user_nama'] ?? '-') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><br>Belum ada transaksi</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- STOK ALERT -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-bell" style="color:#f59e0b"></i> Peringatan Stok</div>
        <a href="barang.php" class="btn btn-outline btn-sm">Kelola</a>
    </div>
    <div style="padding:16px">
        <?php if ($stokAlert): foreach ($stokAlert as $b):
            $status = getStokStatus($b['stok'], $b['stok_minimum']);
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;margin-bottom:6px;background:var(--gray-50)">
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.85rem;color:var(--gray-800)"><?= htmlspecialchars($b['nama_barang']) ?></div>
                <div style="font-size:.75rem;color:var(--gray-500)"><?= $b['kode_barang'] ?></div>
            </div>
            <div style="text-align:right">
                <div style="font-weight:700;font-size:1rem;color:<?= $b['stok']==0 ? '#ef4444' : '#f59e0b' ?>"><?= $b['stok'] ?></div>
                <span class="badge <?= $status['class'] ?>" style="font-size:.68rem"><?= $status['label'] ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state" style="padding:30px">
            <i class="fas fa-check-circle" style="color:#10b981"></i>
            <h3>Semua stok aman!</h3>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- end grid -->

<?php include '../includes/footer.php'; ?>