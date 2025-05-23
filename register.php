<?php
// Start of SSMS connection code
$serverName = "localhost";
$databaseName = "Secure Banking";
$username = "";
$password = "";

// AES helper functions
function encryptData($data, $key) {
    $cipher = "AES-128-CTR";
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($iv . $encrypted); // prepend IV for decryption
}


try {
    // Connect to the database
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $userName  = trim($_POST['username'] ?? '');
    $userEmail = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $accountNumber = trim($_POST['account_number'] ?? '');
    $roleInput = trim($_POST['role'] = 'User'); // Set to user only

    // Basic validation
    if (empty($userName) || empty($userEmail) || empty($password) || empty($accountNumber)) {
        $error = "Please fill in all required fields.";
    } else {
        // Validate the account number format (example: must be numeric)
        if (!ctype_digit($accountNumber)) {
            $error = "Account number must be numeric.";
        }

        // Map the input role to RoleID
        if ($roleInput === 'User') {
            $roleID = 2;
        } else {
            $error = "Invalid user role selected.";
        }
    }

    if (empty($error)) {
        // Securely hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            // Insert user record
            $sql = "INSERT INTO [User] (User_Name, User_Email, User_Password, RoleID) VALUES (:username, :email, :userpassword, :roleid)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username'     => $userName,
                ':email'        => $userEmail,
                ':userpassword' => $hashedPassword,
                ':roleid'       => $roleID
            ]);

            $userID = $pdo->lastInsertId(); // Get the last insert ID

            // Derive encryption key from hashed password
            $encryptionKey = substr(hash('sha256', $userName), 0, 16); // 16-byte AES key

            // Encrypt the account number
            $encryptedAccountNumber = encryptData($accountNumber, $encryptionKey);

            // Insert account record
            $sqlAccount = "INSERT INTO Account (UserID, Acc_Number, Acc_Balance) VALUES (:userId, :accountNumber, 0)";
            $stmtAccount = $pdo->prepare($sqlAccount);
            $stmtAccount->execute([
                ':userId'       => $userID,
                ':accountNumber' => $encryptedAccountNumber
            ]);

            $success = "Registration successful!";
        } catch (PDOException $ex) {
            $error = "Error in registration: " . $ex->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .register-container {
            max-width: 500px;
            margin: 60px auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1, h2 {
            text-align: center;
            color: #004080;
        }

        .btn-register {
            background-color: #004080;
            color: white;
        }

        .btn-register:hover {
            background-color: #002e5f;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            text-decoration: none;
            color: #004080;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .strength-meter {
            height: 8px;
            border-radius: 5px;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 1.2rem;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h1>Secure Banking</h1>
        <h2>Register an Account</h2>

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
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <i class="bi bi-eye toggle-password" onclick="togglePassword(this)"></i>
                </div>
                <div class="progress mt-2">
                    <div id="strengthBar" class="strength-meter bg-danger" style="width: 0%;"></div>
                </div>
                <small id="strengthText" class="text-muted">Enter a password</small>
            </div>

            <div class="mb-3">
                <label for="account_number" class="form-label">Account Number (10 digits)</label>
                <input type="text" id="account_number" name="account_number" class="form-control" required>
            </div>

            <input type="hidden" name="role" value="User">

            <button type="submit" class="btn btn-register w-100">Register</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <!-- Script -->
    <script>
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

        function togglePassword(icon) {
            const input = document.getElementById("password");
            const isVisible = input.type === "text";
            input.type = isVisible ? "password" : "text";
            icon.classList.toggle("bi-eye");
            icon.classList.toggle("bi-eye-slash");
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("password").addEventListener("input", updateStrengthMeter);
        });
    </script>
</body>

</html>