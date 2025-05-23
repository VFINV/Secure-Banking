<?php
session_start();

if (!isset($_SESSION['role_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

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
// End of SSMS connection code

// Query 1: Fetch all accounts with associated user name and balance.
$sqlAccounts = "SELECT A.AccountID, U.User_Name, A.Acc_Balance
                FROM Account A 
                JOIN [User] U ON A.UserID = U.UserID";

$stmtAccounts = $pdo->prepare($sqlAccounts);
$stmtAccounts->execute();
$accounts = $stmtAccounts->fetchAll(PDO::FETCH_ASSOC);

// Query 2: Fetch all transfers along with sender and receiver names.
$sqlTransfers = "SELECT AT.TransferID, 
                        S.User_Name AS Sender, 
                        R.User_Name AS Receiver, 
                        AT.AmountTransferred, 
                        AT.AmountReceived, 
                        AT.Deposit,
                        AT.Withdrawal, 
                        AT.Action_Date
                 FROM AccountTransfer AT
                 LEFT JOIN Account A1 ON AT.SenderAccountID = A1.AccountID
                 LEFT JOIN Account A2 ON AT.ReceiverAccountID = A2.AccountID
                 LEFT JOIN [User] S ON A1.UserID = S.UserID
                 LEFT JOIN [User] R ON A2.UserID = R.UserID
                 ORDER BY AT.Action_Date DESC";

$stmtTransfers = $pdo->prepare($sqlTransfers);
$stmtTransfers->execute();
$transfers = $stmtTransfers->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>All Transactions - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .container {
            max-width: 1100px;
            margin: 50px auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #004080;
            margin-bottom: 20px;
            border-bottom: 2px solid #004080;
            padding-bottom: 10px;
        }

        .table thead th {
            background-color: #004080;
            color: white;
        }

        .btn-back {
            background-color: #004080;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 30px;
        }

        .btn-back:hover {
            background-color: #002e5f;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Accounts</h1>
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>User</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($accounts): ?>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['AccountID']); ?></td>
                                <td><?php echo htmlspecialchars($account['User_Name']); ?></td>
                                <td><?php echo htmlspecialchars($account['Acc_Balance']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-muted">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h1>Transfers</h1>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>Sender</th>
                        <th>Receiver</th>
                        <th>Amount Transferred</th>
                        <th>Amount Received</th>
                        <th>Deposit</th>
                        <th>Withdrawal</th>
                        <th>Action Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transfers): ?>
                        <?php foreach ($transfers as $transfer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transfer['TransferID']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['Sender'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['Receiver'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['AmountTransferred']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['AmountReceived']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['Deposit']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['Withdrawal']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['Action_Date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-muted">No transfers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
