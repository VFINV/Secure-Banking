<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Start of SSMS connection code
$serverName = "localhost";
$databaseName = "Secure Banking";
$username = "";
$password = "";


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
// end of SSMS connection code

$userId = $_SESSION['user_id'];

// Retrieve the account associated with the logged-in user
$sqlAccount = "SELECT AccountID FROM Account WHERE UserID = :userId";
$stmtAccount = $pdo->prepare($sqlAccount);
$stmtAccount->execute([':userId' => $userId]);
$accountRow = $stmtAccount->fetch(PDO::FETCH_ASSOC);

if ($accountRow) {
    $accountId = $accountRow['AccountID'];

    // Now query AccountTransfer using the correct AccountID
    $sqlAudit = "SELECT * FROM AccountTransfer 
                 WHERE SenderAccountID = :senderId OR ReceiverAccountID = :receiverId 
                 ORDER BY Action_Date DESC";

    $stmtAudit = $pdo->prepare($sqlAudit);
    $stmtAudit->execute([
        ':senderId' => $accountId,
        ':receiverId' => $accountId
    ]);

    $transactions = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
} else {
    $transactions = []; // No account found for the logged-in user
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Transfer History - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .container {
            max-width: 1000px;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #004080;
            margin-bottom: 30px;
        }

        .table thead th {
            background-color: #004080;
            color: white;
        }

        .btn-bank {
            background-color: #004080;
            color: white;
        }

        .btn-bank:hover {
            background-color: #002e5f;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Transfer History</h1>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>Sender Account</th>
                        <th>Receiver Account</th>
                        <th>Amount Transferred</th>
                        <th>Amount Received</th>
                        <th>Deposit</th>
                        <th>Withdrawal</th>
                        <th>Action Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['TransferID']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['SenderAccountID'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['ReceiverAccountID'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['AmountTransferred']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['AmountReceived']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['Deposit']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['Withdrawal']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['Action_Date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-muted">No transfer records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <a href="user_dashboard.php" class="btn btn-bank">← Back to Dashboard</a>
        </div>
    </div>

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
