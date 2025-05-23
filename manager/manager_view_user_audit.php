<?php
session_start();

if (!isset($_SESSION['role_id'])) {
    header("Location: ../login.php");
    exit();
}


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


// Option 1: Only show logs of users (Role_Name = 'User')
// $sql = "SELECT * FROM Audit_Log WHERE Role_Name = 'User' ORDER BY Action_Date DESC";

// Option 2: Show all audit logs (remove the WHERE clause if needed)
$sql = "SELECT * FROM Audit_Log ORDER BY Action_Date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Audit Logs - Secure Banking</title>
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
            margin-bottom: 30px;
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
        <h1>User Audit Logs</h1>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User ID</th>
                        <th>User Name</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Action Date</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['LogID']); ?></td>
                                <td><?php echo htmlspecialchars($log['UserID']); ?></td>
                                <td><?php echo htmlspecialchars($log['User_Name']); ?></td>
                                <td><?php echo htmlspecialchars($log['Role_Name']); ?></td>
                                <td><?php echo htmlspecialchars($log['Action_Type']); ?></td>
                                <td><?php echo htmlspecialchars($log['Action_Date']); ?></td>
                                <td><?php echo htmlspecialchars($log['Status']); ?></td>
                                <td><?php echo htmlspecialchars($log['Message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-muted">No audit logs found.</td>
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