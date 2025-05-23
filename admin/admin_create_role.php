
<?php
session_start();

if (!isset($_SESSION['role_id'])) {
    header("Location: ../login.php");
    exit();
}


require_once '../db_connect.php';

try {
    // put database data in a variable
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

function encryptData($data, $key) {
    $cipher = "AES-128-CTR";
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($iv . $encrypted); // prepend IV for decryption
}

function decryptData($data, $key) {
    $cipher = "AES-128-CTR";
    $data = base64_decode($data);
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $userName  = trim($_POST['username'] ?? '');
    $userEmail = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $roleInput = trim($_POST['role'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');

    // Basic validation
    if (empty($userName) || empty($userEmail) || empty($password) || empty($roleInput)) {
        $error = "Please fill in all required fields.";
    } else {
        // Map the input role to RoleID. In your design, Admin is RoleID 1 and User is RoleID 2.
        // For security, consider not allowing the public to register as Admin.
        if ($roleInput === 'Admin') {
            $roleID = 1;
        } elseif ($roleInput === 'User') {
            $roleID = 2;
        } elseif ($roleInput === 'Manager') {
            $roleID = 3;
        } 
        else {
            $error = "Invalid user role selected.";
        }
         // Validate the account number ONLY if the selected role is User
        if ($roleID == 2 && empty($accountNumber)) {
            $error = "Please enter an account number.";
        } elseif ($roleID == 2 && !ctype_digit($accountNumber)) {
            $error = "Account number must be numeric.";
        }

    }

    if (empty($error)) {
        // Securely hash the password using PHP's built-in function.
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert the record using a prepared statement to safeguard against SQL injection.
        $sql = "INSERT INTO [User] (User_Name, User_Email, User_Password, RoleID) VALUES (:username, :email, :userpassword, :roleid)";
        $stmt = $pdo->prepare($sql);

       
        try {
            $stmt->execute([
                ':username'     => $userName,
                ':email'        => $userEmail,
                ':userpassword' => $hashedPassword,
                ':roleid'       => $roleID
            ]);
            $userID = $pdo->lastInsertId(); // Get the last inserted ID

            // Derive encryption key from hashed password
            $encryptionKey = substr(hash('sha256', $userName), 0, 16); // 16-byte AES key

            // Encrypt the account number
            $encryptedAccountNumber = encryptData($accountNumber, $encryptionKey);

            if ($roleID == 2) {
                $sqlAccount = "INSERT INTO Account (UserID, Acc_Number, Acc_Balance) VALUES (:userId, :accountNumber, 0)";
                $stmtAccount = $pdo->prepare($sqlAccount);
                $stmtAccount->execute([
                    ':userId'       => $userID,
                    ':accountNumber' => $encryptedAccountNumber
                ]);
            }

            
            $auditSQL = "INSERT INTO Audit_Log (UserID, User_Name, Role_Name, Action_Type, Action_Date, Status, Message)
                         VALUES (:userid, :username, :rolename, :actiontype, :actiondate, :status, :message)";
            $stmtAudit = $pdo->prepare($auditSQL);
            $stmtAudit->execute([
                ':userid'     => $userID,
                ':username'   => $userName,
                ':rolename'   => $roleInput,
                ':actiontype' => 'Registration',
                ':actiondate' => date('Y-m-d H:i:s'),
                ':status'     => 'Success',
                ':message'    => 'User registered successfully.'
            ]);

            $success = "Role Registration successful";
            
        } catch (PDOException $ex) {
            // Depending on your application, you might want to force more detailed error handling.
            $error = "Role Registration failed" . $ex->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Role - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .form-container {
            max-width: 600px;
            margin: 60px auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #004080;
            margin-bottom: 30px;
            text-align: center;
        }

        .btn-register {
            background-color: #004080;
            color: white;
        }

        .btn-register:hover {
            background-color: #002e5f;
        }

        .back-link {
            display: block;
            margin-top: 30px;
            text-align: center;
            text-decoration: none;
            color: #004080;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .strength-meter {
            height: 8px;
            border-radius: 5px;
        }
    </style>

    <script>
        function toggleAccountField() {
            const roleSelect = document.getElementById("role");
            const accountField = document.getElementById("account_number_container");
            accountField.style.display = roleSelect.value === "User" ? "block" : "none";
        }

        function evaluatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[\W_]/.test(password)) strength++;
            return strength;
        }

        function updateStrengthMeter() {
            const password = document.getElementById("password").value;
            const meter = document.getElementById("strengthBar");
            const label = document.getElementById("strengthText");
            const strength = evaluatePasswordStrength(password);

            let meterClass = "bg-danger";
            let text = "Weak";

            if (strength >= 4) {
                meterClass = "bg-success";
                text = "Strong";
            } else if (strength >= 3) {
                meterClass = "bg-warning";
                text = "Medium";
            }

            meter.style.width = `${(strength / 5) * 100}%`;
            meter.className = `strength-meter ${meterClass}`;
            label.textContent = text;
        }

        document.addEventListener("DOMContentLoaded", () => {
            toggleAccountField();
            document.getElementById("password").addEventListener("input", updateStrengthMeter);
        });
    </script>
</head>

<body>
    <div class="form-container">
        <h1>Create Role</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Full Name</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div class="progress mt-2">
                    <div id="strengthBar" class="strength-meter" style="width: 0%;"></div>
                </div>
                <small id="strengthText" class="text-muted">Enter a password</small>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Select Role</label>
                <select name="role" id="role" class="form-select" required onchange="toggleAccountField()">
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                    <option value="Manager">Manager</option>
                </select>
            </div>

            <div class="mb-3" id="account_number_container" style="display:none;">
                <label for="account_number" class="form-label">Account Number (10 digits)</label>
                <input type="text" id="account_number" name="account_number" class="form-control">
            </div>

            <button type="submit" class="btn btn-register w-100">Register</button>
        </form>

        <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
