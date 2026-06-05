<?php
require_once '../config.php';
requireLogin();
$pageTitle = 'Data Barang';

$msg = ''; $msgType = '';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $kode        = sanitize($_POST['kode_barang']);
        $nama        = sanitize($_POST['nama_barang']);
        $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
        $satuan      = sanitize($_POST['satuan']);
        $stok        = max(0, (int)$_POST['stok']);
        $stok_min    = max(0, (int)$_POST['stok_minimum']);
        $harga_beli  = max(0, (float)str_replace(['.', ','], ['', '.'], $_POST['harga_beli']));
        $harga_jual  = max(0, (float)str_replace(['.', ','], ['', '.'], $_POST['harga_jual']));
        $deskripsi   = sanitize($_POST['deskripsi'] ?? '');

        if (empty($kode) || empty($nama)) {
            $msg = 'Kode dan nama barang wajib diisi.'; $msgType = 'danger';
        } elseif ($action === 'tambah') {
            try {
                $stmt = $pdo->prepare("INSERT INTO barang (kode_barang,nama_barang,kategori_id,satuan,stok,stok_minimum,harga_beli,harga_jual,deskripsi) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$kode,$nama,$kategori_id,$satuan,$stok,$stok_min,$harga_beli,$harga_jual,$deskripsi]);
                $msg = 'Barang berhasil ditambahkan!'; $msgType = 'success';
            } catch (PDOException $e) {
                $msg = 'Error: Kode barang sudah digunakan.'; $msgType = 'danger';
            }
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE barang SET kode_barang=?,nama_barang=?,kategori_id=?,satuan=?,stok=?,stok_minimum=?,harga_beli=?,harga_jual=?,deskripsi=? WHERE id=?");
            $stmt->execute([$kode,$nama,$kategori_id,$satuan,$stok,$stok_min,$harga_beli,$harga_jual,$deskripsi,$id]);
            $msg = 'Barang berhasil diperbarui!'; $msgType = 'success';
        }
    }

    if ($action === 'hapus' && isAdmin()) {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM barang WHERE id=?")->execute([$id]);
        $msg = 'Barang berhasil dihapus.'; $msgType = 'success';
    }
}

// ===== FETCH DATA =====
$search  = sanitize($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = $search ? "WHERE b.nama_barang LIKE ? OR b.kode_barang LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM barang b $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT b.*, k.nama_kategori
    FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id
    $where ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$barangs = $stmt->fetchAll();

$kategoris = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= $msg ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-boxes"></i> Daftar Barang</div>
        <div class="toolbar">
            <form method="GET" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="form-control" placeholder="Cari nama / kode..." value="<?= htmlspecialchars($search) ?>">
            </form>
            <button class="btn btn-primary" data-modal="modalTambah">
                <i class="fas fa-plus"></i> Tambah Barang
            </button>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th>
                    <th>Satuan</th><th>Stok</th><th>Harga Jual</th><th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($barangs): $no = $offset + 1; foreach ($barangs as $b):
                $status = getStokStatus($b['stok'], $b['stok_minimum']);
            ?>
            <tr>
                <td style="color:var(--gray-400)"><?= $no++ ?></td>
                <td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px;font-size:.78rem"><?= htmlspecialchars($b['kode_barang']) ?></code></td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($b['nama_barang']) ?></div>
                    <?php if ($b['deskripsi']): ?>
                    <div style="font-size:.75rem;color:var(--gray-400)"><?= htmlspecialchars(substr($b['deskripsi'],0,50)) ?>...</div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
                <td><?= htmlspecialchars($b['satuan']) ?></td>
                <td>
                    <span style="font-weight:700;font-size:1rem;color:<?= $b['stok']==0?'#ef4444':($b['stok']<=$b['stok_minimum']?'#f59e0b':'#374151') ?>"><?= $b['stok'] ?></span>
                    <span style="font-size:.72rem;color:var(--gray-400)"> / min <?= $b['stok_minimum'] ?></span>
                </td>
                <td><?= formatRupiah($b['harga_jual']) ?></td>
                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                <td>
                    <div style="display:flex;gap:4px">
                        <button class="btn btn-warning btn-sm btn-icon btn-edit"
                            data-id="<?= $b['id'] ?>"
                            data-kode="<?= htmlspecialchars($b['kode_barang'], ENT_QUOTES) ?>"
                            data-nama="<?= htmlspecialchars($b['nama_barang'], ENT_QUOTES) ?>"
                            data-kategori="<?= $b['kategori_id'] ?>"
                            data-satuan="<?= htmlspecialchars($b['satuan'], ENT_QUOTES) ?>"
                            data-stok="<?= $b['stok'] ?>"
                            data-stokmin="<?= $b['stok_minimum'] ?>"
                            data-hargabeli="<?= $b['harga_beli'] ?>"
                            data-hargajual="<?= $b['harga_jual'] ?>"
                            data-deskripsi="<?= htmlspecialchars($b['deskripsi'] ?? '', ENT_QUOTES) ?>"
                            title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php if (isAdmin()): ?>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon btn-delete"
                                data-nama="<?= htmlspecialchars($b['nama_barang'], ENT_QUOTES) ?>" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9">
                <div class="empty-state">
                    <i class="fas fa-boxes"></i>
                    <h3>Tidak ada barang<?= $search ? " untuk \"" . htmlspecialchars($search) . "\"" : "" ?></h3>
                    <p>Tambahkan barang pertama Anda</p>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:12px 20px;border-top:1px solid var(--gray-100)">
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Tambah Barang</div>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Barang <span class="req">*</span></label>
                        <input type="text" name="kode_barang" class="form-control" placeholder="BRG-001" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan <span class="req">*</span></label>
                        <select name="satuan" class="form-control" required>
                            <option>pcs</option><option>unit</option><option>kg</option>
                            <option>liter</option><option>rim</option><option>lusin</option><option>dus</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Barang <span class="req">*</span></label>
                    <input type="text" name="nama_barang" class="form-control" placeholder="Nama barang..." required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-control">
                            <option value="">— Pilih Kategori —</option>
                            <?php foreach ($kategoris as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stok Awal</label>
                        <input type="number" name="stok" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stok Minimum</label>
                        <input type="number" name="stok_minimum" class="form-control" value="5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Beli</label>
                        <input type="number" name="harga_beli" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga Jual</label>
                    <input type="number" name="harga_jual" class="form-control" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" placeholder="Deskripsi opsional..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-pen" style="color:var(--warning)"></i> Edit Barang</div>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Barang <span class="req">*</span></label>
                        <input type="text" name="kode_barang" id="editKode" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan</label>
                        <select name="satuan" id="editSatuan" class="form-control">
                            <option>pcs</option><option>unit</option><option>kg</option>
                            <option>liter</option><option>rim</option><option>lusin</option><option>dus</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Barang <span class="req">*</span></label>
                    <input type="text" name="nama_barang" id="editNama" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" id="editKategori" class="form-control">
                            <option value="">— Pilih Kategori —</option>
                            <?php foreach ($kategoris as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" id="editStok" class="form-control" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stok Minimum</label>
                        <input type="number" name="stok_minimum" id="editStokMin" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Beli</label>
                        <input type="number" name="harga_beli" id="editHargaBeli" class="form-control" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga Jual</label>
                    <input type="number" name="harga_jual" id="editHargaJual" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="editDeskripsi" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).on('click', '.btn-edit', function() {
    $('#editId').val($(this).data('id'));
    $('#editKode').val($(this).data('kode'));
    $('#editNama').val($(this).data('nama'));
    $('#editKategori').val($(this).data('kategori'));
    $('#editSatuan').val($(this).data('satuan'));
    $('#editStok').val($(this).data('stok'));
    $('#editStokMin').val($(this).data('stokmin'));
    $('#editHargaBeli').val($(this).data('hargabeli'));
    $('#editHargaJual').val($(this).data('hargajual'));
    $('#editDeskripsi').val($(this).data('deskripsi'));
    $('#modalEdit').addClass('active');
});
</script>

<?php include '../includes/footer.php'; ?>