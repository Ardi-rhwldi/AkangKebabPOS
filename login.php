<?php

require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'kasir') {
        redirect('pos.php');
    } else {
        redirect('index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = escape_string($conn, $_POST['username']);
    $password = $_POST['password']; 

    $query = "SELECT id, username, password, role FROM users WHERE username = '$username'";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; 
            
            if ($user['role'] == 'kasir') {
                redirect('pos.php');
            } else {
                redirect('index.php'); 
            }

        } else {
            $error = 'Username atau password salah!';
        }
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .text-orange { color: #f97316 !important; }
        .btn-orange {
            background-color: #f97316;
            border-color: #f97316;
            color: white;
        }
        .btn-orange:hover {
            background-color: #ea580c;
            border-color: #ea580c;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-orange fw-bold">🥙 Outlet Akang Kebab</h2>
                            <p class="text-muted">Sistem POS & Inventory</p>
                        </div>
                        
                        <form method="POST" action="">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-orange w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>