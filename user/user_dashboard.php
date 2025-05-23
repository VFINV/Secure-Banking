<?php
session_start(); 
// Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database Connection
$serverName   = "localhost";
$databaseName = "Secure Banking";
$dbUsername   = ""; // Provide your DB username
$dbPassword   = ""; // Provide your DB password

try {
    $pdo = new PDO(
        "sqlsrv:server=$serverName;Database=$databaseName",
        $dbUsername,
        $dbPassword
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<script>console.log('Server Connected.');</script>";
} catch (PDOException $ex) {
    die("Server Not Connected: " . $ex->getMessage());
}

function decryptData($data, $key) {
    $cipher = "AES-128-CTR";
    $data = base64_decode($data);
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
}



// Retrieve logged-in user's ID and details from session.
$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Unknown';
$roleName = $_SESSION['role_name'] ?? 'User';

// ----------------------------------------------------------------
// Define functions with audit logging integration:

function depositFunds($pdo, $accountId, $amount) {
    try {
        // Update the Account record: add deposit amount to balance.
        $sql = "UPDATE Account 
                SET Acc_Balance = Acc_Balance + :amount 
                WHERE AccountID = :accountId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':amount'    => $amount,
            ':accountId' => $accountId
        ]);
        
        // Record the deposit in the AccountTransfer table.
        // Use unique placeholders for duplicate values.
        $sqlTransfer = "INSERT INTO AccountTransfer 
                        (SenderAccountID, ReceiverAccountID, AmountTransferred, AmountReceived, Deposit, Withdrawal, Action_Date)
                        VALUES (NULL, :accountId, 0, :amount1, :amount2, 0, GETDATE())";
        $stmtTransfer = $pdo->prepare($sqlTransfer);
        $stmtTransfer->execute([
            ':accountId' => $accountId,
            ':amount1'   => $amount,
            ':amount2'   => $amount
        ]);
        
        return "Deposit successful.";
    } catch (PDOException $ex) {
        return "Deposit failed: " . $ex->getMessage();
    }
}

function withdrawFunds($pdo, $accountId, $amount) {
    try {
        // Check the current account balance.
        $sqlCheck = "SELECT Acc_Balance FROM Account WHERE AccountID = :accountId";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':accountId' => $accountId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return "Account not found.";
        }
        if ($row['Acc_Balance'] < $amount) {
            return "Insufficient funds for withdrawal.";
        }
        
        // Update the Account record: subtract withdrawal amount.
        $sql = "UPDATE Account 
                SET Acc_Balance = Acc_Balance - :amount 
                WHERE AccountID = :accountId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':amount'    => $amount,
            ':accountId' => $accountId
        ]);
        
        // Record the withdrawal in the AccountTransfer table.
        // For a withdrawal, ReceiverAccountID is NULL.
        $sqlTransfer = "INSERT INTO AccountTransfer 
                        (SenderAccountID, ReceiverAccountID, AmountTransferred, AmountReceived, Deposit, Withdrawal, Action_Date)
                        VALUES (:accountId, NULL, :amount1, 0,0, :amount2, GETDATE())";
        $stmtTransfer = $pdo->prepare($sqlTransfer);
        $stmtTransfer->execute([
            ':accountId' => $accountId,
            ':amount1'   => $amount,
            ':amount2'   => $amount
        ]);
        
        return "Withdrawal successful.";
    } catch (PDOException $ex) {
        return "Withdrawal failed: " . $ex->getMessage();
    }
}

function transferFunds($pdo, $senderAccountId, $receiverAccountId, $amount) {
    try {
        $pdo->beginTransaction();

        // Check sender's balance.
        $sqlCheck = "SELECT Acc_Balance FROM Account WHERE AccountID = :senderAccountId";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':senderAccountId' => $senderAccountId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return "Sender account not found.";
        }
        if ($row['Acc_Balance'] < $amount) {
            $pdo->rollBack();
            return "Insufficient funds for transfer.";
        }

        // Withdraw from sender.
        $sqlWithdraw = "UPDATE Account 
                        SET Acc_Balance = Acc_Balance - :amount 
                        WHERE AccountID = :senderAccountId";
        $stmtWithdraw = $pdo->prepare($sqlWithdraw);
        $stmtWithdraw->execute([
            ':amount'           => $amount,
            ':senderAccountId'  => $senderAccountId
        ]);

        // Deposit to receiver.
        $sqlDeposit = "UPDATE Account 
                       SET Acc_Balance = Acc_Balance + :amount 
                       WHERE AccountID = :receiverAccountId";
        $stmtDeposit = $pdo->prepare($sqlDeposit);
        $stmtDeposit->execute([
            ':amount'            => $amount,
            ':receiverAccountId' => $receiverAccountId
        ]);

        // Record the transfer in the AccountTransfer table.
        // Here we assume:
        //   - AmountTransferred: the amount deducted from the sender.
        //   - AmountReceived: the amount credited to the receiver (equal to the transfer amount).
        //   - Deposit and Withdrawal are set to 0 for a transfer record.
        $sqlTransfer = "INSERT INTO AccountTransfer 
                        (SenderAccountID, ReceiverAccountID, AmountTransferred, AmountReceived, Deposit, Withdrawal, Action_Date)
                        VALUES (:senderAccountId, :receiverAccountId, :amountTransferred, :amountReceived, :deposit, :withdrawal, GETDATE())";
        $stmtTransfer = $pdo->prepare($sqlTransfer);
        $stmtTransfer->execute([
            ':senderAccountId'   => $senderAccountId,
            ':receiverAccountId' => $receiverAccountId,
            ':amountTransferred' => $amount,
            ':amountReceived'    => $amount,
            ':deposit'           => 0,
            ':withdrawal'        => 0
        ]);

        $pdo->commit();
        return "Transfer successful.";
    } catch (PDOException $ex) {
        $pdo->rollBack();
        return "Transfer failed: " . $ex->getMessage();
    }
}
// ----------------------------------------------------------------

// Process form submissions (if any)
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve the account ID associated to the logged in user.
    $sqlFetchAccount = "SELECT AccountID FROM Account WHERE UserID = :userId";
    $stmtFetch = $pdo->prepare($sqlFetchAccount);
    $stmtFetch->execute([':userId' => $userId]);
    $accountRow = $stmtFetch->fetch(PDO::FETCH_ASSOC);
    
    if (!$accountRow) {
        $message = "Account not found for user.";
    } else {
        $accountId = $accountRow['AccountID'];
        
        // Process Deposit 
        if (isset($_POST['deposit'])) {
            $depositAmount = floatval($_POST['deposit_amount'] ?? 0);
            if ($depositAmount > 0) {
                $message = depositFunds($pdo, $accountId, $depositAmount, $userId, $userName, $roleName);
            } else {
                $message = "Please enter a valid deposit amount.";
            }
        }
        
        // Process Withdrawal 
        if (isset($_POST['withdraw'])) {
            $withdrawAmount = floatval($_POST['withdraw_amount'] ?? 0);
            if ($withdrawAmount > 0) {
                $message = withdrawFunds($pdo, $accountId, $withdrawAmount, $userId, $userName, $roleName);
            } else {
                $message = "Please enter a valid withdrawal amount.";
            }
        }
        
        // Process Transfer 
        if (isset($_POST['transfer'])) {
            $transferAmount = floatval($_POST['transfer_amount'] ?? 0);
            $receiverAccountId = intval($_POST['receiver_account_id'] ?? 0);
            if ($transferAmount > 0 && $receiverAccountId > 0) {
                if ($accountId == $receiverAccountId) {
                    $message = "Cannot transfer to the same account.";
                } else {
                    $message = transferFunds($pdo, $accountId, $receiverAccountId, $transferAmount, $userId, $userName, $roleName);
                }
            } else {
                $message = "Please enter valid transfer amount and receiver account ID.";
            }
        }
    }
}

// Re-fetch joined user and account details after any transactions.
$sql = "SELECT 
            U.UserID, 
            U.User_Name, 
            U.User_Email, 
            A.AccountID, 
            A.Acc_Number,
            A.Acc_Balance
        FROM [User] U
        INNER JOIN Account A ON U.UserID = A.UserID
        WHERE U.UserID = :userId";
$stmt = $pdo->prepare($sql);
$stmt->execute([':userId' => $userId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$encryptionKey = substr(hash('sha256', $userName), 0, 16); // 16-byte AES key
$decryptedAccNumber = decryptData($data['Acc_Number'], $encryptionKey);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }
        .dashboard-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            border-bottom: 2px solid #004080;
            padding-bottom: 5px;
            margin-bottom: 20px;
            color: #004080;
        }
        .btn-bank {
            background-color: #004080;
            color: white;
        }
        .btn-bank:hover {
            background-color: #002e5f;
        }
        a {
            color: #004080;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .nav-links {
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <h2 class="text-center mb-4">User Dashboard</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($data): ?>
        <p><strong>Account ID:</strong> <?php echo htmlspecialchars($data['AccountID']); ?></p>
        <p><strong>Account Number:</strong> <?php echo htmlspecialchars($decryptedAccNumber); ?></p>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($data['User_Name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($data['User_Email']); ?></p>
        <p><strong>Balance:</strong> <?php echo htmlspecialchars($data['Acc_Balance']); ?></p>
        <?php else: ?>
            <p>No account information found for your user ID (<?php echo htmlspecialchars($userId); ?>).</p>
        <?php endif; ?>

        <div class="mt-4">
            <h4 class="section-title">Deposit Funds</h4>
            <form method="post" action="" class="row g-3">
                <div class="col-md-8">
                    <input type="number" step="0.01" class="form-control" name="deposit_amount" id="deposit_amount" placeholder="Enter amount" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="deposit" value="Deposit" class="btn btn-bank w-100">Deposit</button>
                </div>
            </form>
        </div>

        <div class="mt-4">
            <h4 class="section-title">Withdraw Funds</h4>
            <form method="post" action="" class="row g-3">
                <div class="col-md-8">
                    <input type="number" step="0.01" class="form-control" name="withdraw_amount" id="withdraw_amount" placeholder="Enter amount" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="withdraw" value="Withdraw" class="btn btn-bank w-100">Withdraw</button>
                </div>
            </form>
        </div>

        <div class="mt-4">
            <h4 class="section-title">Transfer Funds</h4>
            <form method="post" action="" class="row g-3">
                <div class="col-md-6">
                <input type="text" pattern="\d*" inputmode="numeric" class="form-control" name="receiver_account_id" id="receiver_account_id" placeholder="Receiver Account ID" required>

                </div>
                <div class="col-md-6">
                    <input type="number" step="0.01" class="form-control" name="transfer_amount" id="transfer_amount" placeholder="Amount to Transfer" required>
                </div>
                <div class="col-md-12">
                    <button type="submit" name="transfer" value="Transfer" class="btn btn-bank w-100">Transfer</button>
                </div>
            </form>
        </div>

        <div class="nav-links mt-4">
            <a href="user_view_own_trans.php">View Transaction History</a> 
            <a class="btn btn-outline-danger" href="../login.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
            <!-- <a href="../login.php">🔒 Logout</a> -->
        </div>
    </div>

    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
