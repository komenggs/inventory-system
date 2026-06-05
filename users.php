<?php
require_once '../../config.php';
requireAdmin();
$pageTitle = 'Kelola User';
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama = sanitize($_POST['nama']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email'] ?? '');
        $role = in_array($_POST['role'], ['admin','staff']) ? $_POST['role'] : 'staff';
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO users (nama,username,password,role,email) VALUES (?,?,?,?,?)")
                ->execute([$nama,$username,$password,$role,$email]);
            $msg = 'User berhasil ditambahkan!'; $msgType = 'success';
        } catch (PDOException $e) {
            $msg = 'Error: Username sudah digunakan.'; $msgType = 'danger';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $nama = sanitize($_POST['nama']);
        $email = sanitize($_POST['email'] ?? '');
        $role = in_array($_POST['role'], ['admin','staff']) ? $_POST['role'] : 'staff';
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET nama=?,role=?,email=?,password=? WHERE id=?")
                ->execute([$nama,$role,$email,$pass,$id]);
        } else {
            $pdo->prepare("UPDATE users SET nama=?,role=?,email=? WHERE id=?")
                ->execute([$nama,$role,$email,$id]);
        }
        $msg = 'User diperbarui!'; $msgType = 'success';
    } elseif ($action === 'hapus') {
        $id = (int)$_POST['id'];
        if ($id == $_SESSION['user_id']) {
            $msg = 'Tidak bisa menghapus akun sendiri.'; $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $msg = 'User dihapus.'; $msgType = 'success';
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, nama")->fetchAll();
include '../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users-gear"></i> Kelola User</div>
        <button class="btn btn-primary" data-modal="modalTambahUser"><i class="fas fa-user-plus"></i> Tambah User</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $no=1; foreach ($users as $u): ?>
            <tr>
                <td style="color:var(--gray-400)"><?= $no++ ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:32px;height:32px;background:<?= $u['role']==='admin'?'#fef3c7':'#dbeafe' ?>;border-radius:50%;display:grid;place-items:center;font-weight:700;color:<?= $u['role']==='admin'?'#d97706':'#1d4ed8' ?>;font-size:.82rem">
                            <?= strtoupper(substr($u['nama'],0,1)) ?>
                        </div>
                        <span style="font-weight:600"><?= htmlspecialchars($u['nama']) ?></span>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                        <span class="badge badge-success" style="font-size:.65rem">Anda</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?= $u['username'] ?></code></td>
                <td style="color:var(--gray-500)"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                <td>
                    <span class="badge <?= $u['role']==='admin'?'badge-warning':'badge-info' ?>">
                        <i class="fas fa-<?= $u['role']==='admin'?'crown':'user' ?>"></i>
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td style="font-size:.8rem;color:var(--gray-400)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:4px">
                        <button class="btn btn-warning btn-sm btn-icon btn-edit-user"
                            data-id="<?= $u['id'] ?>"
                            data-nama="<?= htmlspecialchars($u['nama'],ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($u['email']??'',ENT_QUOTES) ?>"
                            data-role="<?= $u['role'] ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon btn-delete" data-nama="user <?= htmlspecialchars($u['nama'],ENT_QUOTES) ?>"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH USER -->
<div class="modal-overlay" id="modalTambahUser">
    <div class="modal">
        <div class="modal-header"><div class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary)"></i> Tambah User</div><button class="modal-close">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nama Lengkap <span class="req">*</span></label><input type="text" name="nama" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Username <span class="req">*</span></label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Role <span class="req">*</span></label>
                        <select name="role" class="form-control">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Password <span class="req">*</span></label><input type="password" name="password" class="form-control" required minlength="6"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div class="modal-overlay" id="modalEditUser">
    <div class="modal">
        <div class="modal-header"><div class="modal-title"><i class="fas fa-user-pen" style="color:var(--warning)"></i> Edit User</div><button class="modal-close">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="nama" id="editUserNama" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="editUserEmail" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Role</label>
                        <select name="role" id="editUserRole" class="form-control">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru <span style="color:var(--gray-400);font-weight:400">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" class="form-control" minlength="6" placeholder="Minimal 6 karakter">
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
$(document).on('click', '.btn-edit-user', function(){
    $('#editUserId').val($(this).data('id'));
    $('#editUserNama').val($(this).data('nama'));
    $('#editUserEmail').val($(this).data('email'));
    $('#editUserRole').val($(this).data('role'));
    $('#modalEditUser').addClass('active');
});
</script>

<?php include '../../includes/footer.php'; ?>