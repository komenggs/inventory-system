<?php
require_once '../config.php';
requireLogin();
$pageTitle = 'Laporan';

$dari   = sanitize($_GET['dari']   ?? date('Y-m-01'));
$sampai = sanitize($_GET['sampai'] ?? date('Y-m-d'));
$tipe   = sanitize($_GET['tipe']   ?? '');

// Summary Masuk
$smMasuk = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(dt.subtotal),0) as total FROM transaksi t LEFT JOIN detail_transaksi dt ON t.id=dt.transaksi_id WHERE t.tipe='masuk' AND t.tanggal BETWEEN ? AND ?");
$smMasuk->execute([$dari, $sampai]);
$sumMasuk = $smMasuk->fetch();

// Summary Keluar
$smKeluar = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(dt.subtotal),0) as total FROM transaksi t LEFT JOIN detail_transaksi dt ON t.id=dt.transaksi_id WHERE t.tipe='keluar' AND t.tanggal BETWEEN ? AND ?");
$smKeluar->execute([$dari, $sampai]);
$sumKeluar = $smKeluar->fetch();

// Selisih (nilai keluar - nilai masuk dalam periode sama)
$selisih = $sumKeluar['total'] - $sumMasuk['total'];

// Top barang
$topSql = "
    SELECT b.nama_barang, b.satuan, SUM(dt.jumlah) as total_qty, SUM(dt.subtotal) as total_nilai, t.tipe
    FROM detail_transaksi dt
    JOIN barang b ON dt.barang_id=b.id
    JOIN transaksi t ON dt.transaksi_id=t.id
    WHERE t.tanggal BETWEEN ? AND ?
    " . ($tipe ? "AND t.tipe=?" : "") . "
    GROUP BY b.id, t.tipe ORDER BY total_qty DESC LIMIT 10
";
$topBarang = $pdo->prepare($topSql);
$topBarang->execute($tipe ? [$dari,$sampai,$tipe] : [$dari,$sampai]);
$tops = $topBarang->fetchAll();

// Stok saat ini
$stokBarang = $pdo->query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id=k.id ORDER BY b.nama_barang")->fetchAll();

include '../includes/header.php';
?>

<!-- FILTER -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.78rem">Dari</label>
                <input type="date" name="dari" class="form-control" value="<?= $dari ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.78rem">Sampai</label>
                <input type="date" name="sampai" class="form-control" value="<?= $sampai ?>">
            </div>
            <div class="form-group" style="margin:0;min-width:130px">
                <label class="form-label" style="font-size:.78rem">Tipe</label>
                <select name="tipe" class="form-control">
                    <option value="">Semua</option>
                    <option value="masuk"  <?= $tipe==='masuk'?'selected':'' ?>>Masuk</option>
                    <option value="keluar" <?= $tipe==='keluar'?'selected':'' ?>>Keluar</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
            <div class="export-bar no-print">
                <a href="exports/export_excel.php?dari=<?= $dari ?>&sampai=<?= $sampai ?>&tipe=<?= urlencode($tipe) ?>" class="btn btn-excel btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="exports/export_pdf.php?dari=<?= $dari ?>&sampai=<?= $sampai ?>&tipe=<?= urlencode($tipe) ?>" class="btn btn-pdf-dl btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                <button type="button" onclick="window.print()" class="btn btn-print btn-sm"><i class="fas fa-print"></i> Print</button>
            </div>
        </form>
    </div>
</div>

<!-- SUMMARY CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-arrow-down"></i></div>
        <div>
            <div class="stat-value"><?= $sumMasuk['cnt'] ?></div>
            <div class="stat-label">Transaksi Masuk</div>
            <div style="font-size:.78rem;color:var(--success);font-weight:600"><?= formatRupiah($sumMasuk['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-arrow-up"></i></div>
        <div>
            <div class="stat-value"><?= $sumKeluar['cnt'] ?></div>
            <div class="stat-label">Transaksi Keluar</div>
            <div style="font-size:.78rem;color:#3b82f6;font-weight:600"><?= formatRupiah($sumKeluar['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $selisih >= 0 ? 'green' : 'red' ?>"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatRupiah(abs($selisih)) ?></div>
            <div class="stat-label">Selisih Nilai (Keluar - Masuk)</div>
            <div style="font-size:.75rem;color:<?= $selisih >= 0 ? '#10b981' : '#ef4444' ?>;font-weight:600">
                <?= $selisih >= 0 ? '▲ Positif' : '▼ Negatif' ?>
                <span style="color:#9ca3af;font-weight:400"> — periode yang sama</span>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- TOP BARANG -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i> Top Barang Transaksi</div></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Barang</th><th>Tipe</th><th>Qty</th><th>Nilai</th></tr></thead>
            <tbody>
            <?php if ($tops): foreach ($tops as $t): ?>
            <tr>
                <td style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($t['nama_barang']) ?></td>
                <td><?= $t['tipe']==='masuk' ? '<span class="badge badge-success">Masuk</span>' : '<span class="badge badge-info">Keluar</span>' ?></td>
                <td><?= number_format($t['total_qty']) ?> <?= htmlspecialchars($t['satuan']) ?></td>
                <td style="font-weight:600"><?= formatRupiah($t['total_nilai']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-chart-bar"></i><h3>Tidak ada data</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- STOK SAAT INI -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-boxes"></i> Stok Barang Saat Ini</div></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Barang</th><th>Kategori</th><th>Stok</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($stokBarang as $b):
                $status = getStokStatus($b['stok'], $b['stok_minimum']); ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($b['nama_barang']) ?></div>
                    <div style="font-size:.72rem;color:var(--gray-400)"><?= htmlspecialchars($b['kode_barang']) ?></div>
                </td>
                <td style="font-size:.82rem"><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
                <td style="font-weight:700"><?= $b['stok'] ?> <?= htmlspecialchars($b['satuan']) ?></td>
                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php include '../includes/footer.php'; ?>