<?php
session_start(); // stores the data like login details for queries in other code

// Start of SSMS connection code
$serverName = "localhost";
$databaseName = "Secure Banking";
$username = "";
$password = "";


try {
    $pdo = new PDO(
        "sqlsrv:server=$serverName;Database=$databaseName",
        $username,
        $password
    );
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<script>console.log('Server Connected.');</script>";
} catch (PDOException $ex) {
    die("Server Not Connected: " . $ex->getMessage());
}
// End of SSMS connection code

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and sanitize input.
    $userName  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (empty($userName) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Retrieve the user record based on the email.
        $sql = "SELECT UserID, User_Name, User_Password, RoleID FROM [User] WHERE User_Name = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $userName]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify the password.
        if ($user && password_verify($password, $user['User_Password'])) {
            // Set session variables or any other login-related logic.
            $_SESSION['user_id']   = $user['UserID'];
            $_SESSION['user_name'] = $user['User_Name'];
            $_SESSION['role_id']   = $user['RoleID'];

            // Second query: get the role name based on RoleID.
            $sqlRole = "SELECT Role_Name FROM Role WHERE RoleID = :roleid";
            $stmtRole = $pdo->prepare($sqlRole);
            $stmtRole->execute([':roleid' => $user['RoleID']]);
            $role = $stmtRole->fetch(PDO::FETCH_ASSOC);

            // Combine the results.
            $user['Role_Name'] = $role ? $role['Role_Name'] : 'Unknown';


            // Log the successful logon into the Audit_Log table.
            $sqlAudit = "INSERT INTO Audit_Log (UserID, User_Name, Role_Name, Action_Type, Action_Date, Status, Message)
                        VALUES (:userID, :userName, :roleName, 'Login', GETDATE(), 'Success', 'Login Attempt Successful')";
            $stmtAudit = $pdo->prepare($sqlAudit);
            $stmtAudit->execute([
                ':userID'   => $user['UserID'],
                ':userName' => $user['User_Name'],
                ':roleName' => $user['Role_Name']
            ]);

            // Redirect to a dashboard according to RoleID
            if ($_SESSION['role_id'] == 1) {
                header("Location: ../Secure Banking/admin/admin_dashboard.php");
                exit;
            } else if ($_SESSION['role_id'] == 2) {
                header("Location: ../Secure Banking/user/user_dashboard.php");
                exit;
            } else if ($_SESSION['role_id'] == 3) {
                header("Location: ../Secure Banking/manager/manager_dashboard.php");
                exit;
            } else {
                echo "Unknown role. Access denied.";
                exit;
            }

            // exit();
        } else {
            // Retrieve the user record based on username.
            $sql = "SELECT UserID, User_Name, User_Password, RoleID FROM [User] WHERE User_Name = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $userName]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If a user is found then fetch role name; otherwise, set defaults.
            if ($user) {
                $sqlRole = "SELECT Role_Name FROM Role WHERE RoleID = :roleid";
                $stmtRole = $pdo->prepare($sqlRole);
                $stmtRole->execute([':roleid' => $user['RoleID']]);
                $role = $stmtRole->fetch(PDO::FETCH_ASSOC);
                $user['Role_Name'] = $role ? $role['Role_Name'] : 'Unknown';
            } else {
                // Username not found: record the typed in username and default values
                $user = [
                    'UserID'    => null,          // or use a dummy value if required by FK constraints
                    'User_Name' => $userName,       // use the typed username
                    'Role_Name' => 'Unknown'
                ];
            }

            // Now set the audit variables
            $auditUserID   = $user['UserID'];     // will be null if user isn't found
            $auditUserName = $user['User_Name'];  // always the value typed in if not found
            $auditRoleName = $user['Role_Name']; // defaults to 'Unknown' if user is not found

            $sqlAudit_Failure = "INSERT INTO Audit_Log (UserID, User_Name, Role_Name, Action_Type, Action_Date, Status, Message)
                        VALUES (:userID, :userName, :roleName, 'Login', GETDATE(), 'Fail', 'Login Attempt Failed')";
            $stmtAudit_Failure = $pdo->prepare($sqlAudit_Failure);
            $stmtAudit_Failure->execute([
                ':userID'   => $auditUserID, // directctly use the value
                ':userName' => $auditUserName,
                ':roleName' => $auditRoleName
            ]);

            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .login-container {
            max-width: 450px;
            margin: 60px auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #004080;
            margin-bottom: 10px;
        }

        h2 {
            text-align: center;
            color: #004080;
            margin-bottom: 25px;
        }

        .btn-login {
            background-color: #004080;
            color: white;
        }

        .btn-login:hover {
            background-color: #002e5f;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            text-decoration: none;
            color: #004080;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>Secure Banking</h1>
        <h2>Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-login w-100">Login</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
