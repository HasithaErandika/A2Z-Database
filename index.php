<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "localhost";
    $username = $_POST['username'];
    $password = $_POST['password'];
    $dbname = "suramalr_operational_db";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['dbname'] = $dbname;
        header("Location: tables.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqk1w27APbCZZp+trN3v8TpgAm16FB46Z+9xjbBJCGSdOdQoNLwOp8aAgBxSsQfjJxFoq6+A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="styles.css" rel="stylesheet"> 
</head>
<body>
    <?php include 'header.php'; ?> <!— Include header -->

    <div class="container login-container">
        <div class="card login-card p-4">
            <div class="card-body">
                <h2 class="card-title text-center">Database Login</h2>

                <?php 
                session_start();

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $servername = "localhost";
                    $username = $_POST['username'];
                    $password = $_POST['password'];
                    $dbname = "suramalr_operational_db";

                    // **Important: Use prepared statements for security**
                    $conn = new mysqli($servername, $username, $password, $dbname);

                    if ($conn->connect_error) {
                        $error = "Connection failed: " . $conn->connect_error;
                        // **Log the error for debugging**
                        error_log("Database connection failed: " . $conn->connect_error);
                    } else {
                        // **Hash the password before storing it in the session**
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $_SESSION['username'] = $username;
                        $_SESSION['password_hash'] = $hashedPassword; // Store the hash
                        $_SESSION['dbname'] = $dbname;
                        header("Location: tables.php");
                        exit();
                    }
                }

                if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Connect</button>
                </form>
            </div>
        </div>
    </div>

  <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>