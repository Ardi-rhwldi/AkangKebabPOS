<?php
/**
 * Halaman Manajemen Supplier (Pemasok)
 * Hanya dapat diakses oleh user dengan role 'admin'.
 */

require_once 'config.php';
// Menerapkan Access Control: Hanya Admin yang boleh mengakses
check_access(['admin']); 

$message = '';
$message_type = '';

// ==========================================
// 1. PROSES CRUD (Create, Update, Delete)
// ==========================================

// Proses tambah supplier
if (isset($_POST['add_supplier'])) {
    $name = escape_string($conn, $_POST['name']);
    $contact_person = escape_string($conn, $_POST['contact_person']);
    $phone = escape_string($conn, $_POST['phone']);
    $address = escape_string($conn, $_POST['address']);

    // Query INSERT
    $query = "INSERT INTO suppliers (name, contact_person, phone, address) VALUES ('$name', '$contact_person', '$phone', '$address')";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Supplier berhasil ditambahkan!';
        $message_type = 'success';
    } else {
        $message = 'Gagal menambahkan supplier: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Proses edit supplier
if (isset($_POST['edit_supplier'])) {
    $id = intval($_POST['id']);
    $name = escape_string($conn, $_POST['name']);
    $contact_person = escape_string($conn, $_POST['contact_person']);
    $phone = escape_string($conn, $_POST['phone']);
    $address = escape_string($conn, $_POST['address']);

    // Query UPDATE
    $query = "UPDATE suppliers SET name='$name', contact_person='$contact_person', phone='$phone', address='$address' WHERE id=$id";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Supplier berhasil diupdate!';
        $message_type = 'success';
        // Hapus parameter 'edit' agar form kembali ke mode 'Tambah'
        unset($_GET['edit']); 
    } else {
        $message = 'Gagal mengupdate supplier: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Proses hapus supplier
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $query = "DELETE FROM suppliers WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $message = 'Supplier berhasil dihapus!';
        $message_type = 'success';
    } else {
        $message = 'Gagal menghapus supplier. Pastikan tidak ada data yang terikat dengan supplier ini (misalnya data pembelian): ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Ambil data supplier untuk edit (jika ada)
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $query_edit = "SELECT * FROM suppliers WHERE id=$id";
    $result_edit = mysqli_query($conn, $query_edit);
    $edit_supplier = mysqli_fetch_assoc($result_edit);
}

// Filter dan Pencarian
$search_keyword = isset($_GET['search']) ? escape_string($conn, $_GET['search']) : '';

$query_suppliers = "SELECT * FROM suppliers";
$conditions = [];

if (!empty($search_keyword)) {
    // Cari berdasarkan Nama Supplier atau Contact Person
    $conditions[] = "name LIKE '%$search_keyword%' OR contact_person LIKE '%$search_keyword%' OR phone LIKE '%$search_keyword%'";
}

if (count($conditions) > 0) {
    $query_suppliers .= " WHERE " . implode(' AND ', $conditions);
}

$query_suppliers .= " ORDER BY id DESC";
$result_suppliers = mysqli_query($conn, $query_suppliers);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Gaya kustom dari inventory.php */
        .navbar-orange {
            background-color: #ff8c00; 
        }
        .btn-orange {
            background-color: #ff8c00;
            border-color: #ff8c00;
            color: white;
        }
        .btn-orange:hover {
            background-color: #e67e00;
            border-color: #e67e00;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-orange">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="index.php">🥙 Outlet Akang Kebab</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link text-white" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="inventory.php">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link text-white active" href="suppliers.php">Supplier</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="pos.php">POS</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="sales_report.php">Laporan</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3 class="mb-4">🤝 Supplier Management</h3>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <?php if ($message_type == 'success' && !isset($_GET['edit'])): ?>
            <script>
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            </script>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-orange text-white">
                        <h5 class="mb-0"><?php echo $edit_supplier ? 'Edit Supplier' : 'Tambah Supplier Baru'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="suppliers.php">
                            <?php if ($edit_supplier): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_supplier['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Perusahaan/Supplier</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                        value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Nama Kontak</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                        value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['contact_person']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telepon/HP</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                        value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['phone']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Alamat</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo $edit_supplier ? htmlspecialchars($edit_supplier['address']) : ''; ?></textarea>
                            </div>
                            
                            <?php if ($edit_supplier): ?>
                                <button type="submit" name="edit_supplier" class="btn btn-orange w-100">Update Supplier</button>
                                <a href="suppliers.php" class="btn btn-secondary w-100 mt-2">Batal</a>
                            <?php else: ?>
                                <button type="submit" name="add_supplier" class="btn btn-orange w-100">Tambah Supplier</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header bg-orange text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Daftar Supplier</h5>
                        <span class="badge bg-light text-dark"><?php echo mysqli_num_rows($result_suppliers); ?> Supplier</span>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-2 mb-3">
                            <div class="col-md-10">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Cari nama supplier, kontak, atau telepon..." 
                                            value="<?php echo htmlspecialchars($search_keyword); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                </div>
                            </div>
                            <?php if(!empty($search_keyword)): ?>
                                <div class="col-12 text-end">
                                    <a href="suppliers.php" class="btn btn-outline-secondary btn-sm">Reset Pencarian</a>
                                </div>
                            <?php endif; ?>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Supplier</th>
                                        <th>Kontak (Telp)</th>
                                        <th>Alamat</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result_suppliers) > 0): ?>
                                        <?php while ($supplier = mysqli_fetch_assoc($result_suppliers)): ?>
                                            <tr>
                                                <td><?php echo $supplier['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars($supplier['contact_person']); ?> 
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($supplier['phone']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                                                <td class="text-center">
                                                    <a href="?edit=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                                    <a href="?delete=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Yakin ingin menghapus supplier <?php echo htmlspecialchars($supplier['name']); ?>? Tindakan ini tidak dapat dibatalkan.')" title="Hapus"><i class="bi bi-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-truck fs-1 d-block mb-2"></i>Data supplier tidak ditemukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Memastikan halaman me-refresh setelah back button ditekan
        window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
            }
        });
    </script>
</body>
</html>