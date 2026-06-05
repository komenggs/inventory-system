<?php
require_once '../config.php';
requireLogin();
$pageTitle = 'Kategori';
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'];

    if ($action === 'tambah') {
        $nama = sanitize($_POST['nama_kategori']);
        $desk = sanitize($_POST['deskripsi'] ?? '');
        if (empty($nama)) {
            $msg = 'Nama kategori wajib diisi.'; $msgType = 'danger';
        } else {
            $pdo->prepare("INSERT INTO kategori (nama_kategori,deskripsi) VALUES (?,?)")->execute([$nama,$desk]);
            $msg = 'Kategori berhasil ditambahkan!'; $msgType = 'success';
        }
    } elseif ($action === 'edit') {
        $id   = (int)$_POST['id'];
        $nama = sanitize($_POST['nama_kategori']);
        $desk = sanitize($_POST['deskripsi'] ?? '');
        $pdo->prepare("UPDATE kategori SET nama_kategori=?,deskripsi=? WHERE id=?")->execute([$nama,$desk,$id]);
        $msg = 'Kategori berhasil diperbarui!'; $msgType = 'success';
    } elseif ($action === 'hapus' && isAdmin()) {
        $id = (int)$_POST['id'];
        // Cek apakah kategori masih digunakan
        $jml = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE kategori_id=?");
        $jml->execute([$id]);
        if ($jml->fetchColumn() > 0) {
            $msg = 'Kategori tidak bisa dihapus karena masih digunakan oleh barang.'; $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM kategori WHERE id=?")->execute([$id]);
            $msg = 'Kategori berhasil dihapus.'; $msgType = 'success';
        }
    }
}

$kategoris = $pdo->query("
    SELECT k.*, COUNT(b.id) as jml_barang
    FROM kategori k LEFT JOIN barang b ON k.id = b.kategori_id
    GROUP BY k.id ORDER BY k.nama_kategori
")->fetchAll();

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tags"></i> Kelola Kategori</div>
        <button class="btn btn-primary" data-modal="modalTambah"><i class="fas fa-plus"></i> Tambah</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Nama Kategori</th><th>Deskripsi</th><th>Jumlah Barang</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if ($kategoris): $no=1; foreach ($kategoris as $k): ?>
            <tr>
                <td style="color:var(--gray-400)"><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                <td style="color:var(--gray-500)"><?= htmlspecialchars($k['deskripsi'] ?? '-') ?></td>
                <td><span class="badge badge-info"><?= $k['jml_barang'] ?> barang</span></td>
                <td>
                    <div style="display:flex;gap:4px">
                        <button class="btn btn-warning btn-sm btn-icon btn-edit-kategori"
                            data-id="<?= $k['id'] ?>"
                            data-nama="<?= htmlspecialchars($k['nama_kategori'],ENT_QUOTES) ?>"
                            data-deskripsi="<?= htmlspecialchars($k['deskripsi']??'',ENT_QUOTES) ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php if (isAdmin()): ?>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon btn-delete"
                                data-nama="kategori <?= htmlspecialchars($k['nama_kategori'],ENT_QUOTES) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5"><div class="empty-state"><i class="fas fa-tags"></i><h3>Belum ada kategori</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">Tambah Kategori</div><button class="modal-close">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nama Kategori <span class="req">*</span></label>
                    <input type="text" name="nama_kategori" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-overlay" id="modalEditKategori">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">Edit Kategori</div><button class="modal-close">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editKatId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori" id="editKatNama" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="editKatDesk" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).on('click', '.btn-edit-kategori', function(){
    $('#editKatId').val($(this).data('id'));
    $('#editKatNama').val($(this).data('nama'));
    $('#editKatDesk').val($(this).data('deskripsi'));
    $('#modalEditKategori').addClass('active');
});
</script>

<?php include '../includes/footer.php'; ?>