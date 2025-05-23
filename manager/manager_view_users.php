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
    $pdo = new PDO(
        "sqlsrv:server=$serverName;Database=$databaseName",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<script>console.log('Server Connected.');</script>";
} catch (PDOException $ex) {
    die("Server Not Connected: " . $ex->getMessage());
}

// get role and use predicate 
$role_id = $_SESSION['role_id'];
$query = "SELECT AllowedColumns FROM dbo.GetRoleFilter(:role_id)";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
$stmt->execute();

$result = $stmt->fetch(PDO::FETCH_ASSOC);
$allowedColumns = $result['AllowedColumns'] ?? 'U.UserID, U.User_Name';  // Fallback if function fails

// Call predicate
$sql = "SELECT $allowedColumns FROM [User] U 
        JOIN Role R ON U.RoleID = R.RoleID 
        LEFT JOIN Account A ON U.UserID = A.UserID";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users - Secure Banking</title>
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
            text-align: center;
        }

        .table tbody td {
            text-align: center;
        }

        .btn-action {
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 5px;
            text-decoration: none;
            color: white;
        }

        .btn-deactivate {
            background-color: #c0392b;
        }

        .btn-deactivate:hover {
            background-color: #a93226;
        }

        .btn-activate {
            background-color: #27ae60;
        }

        .btn-activate:hover {
            background-color: #1e8449;
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
        <h1>Manage Users</h1>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <?php if (!empty($users)): ?>
                            <?php foreach (array_keys($users[0]) as $col): ?>
                                <th><?php echo htmlspecialchars($col); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <?php foreach ($user as $value): ?>
                                <td><?php echo htmlspecialchars($value); ?></td>
                            <?php endforeach; ?>
                            <td>
                                <?php if (isset($user['Status']) && $user['Status'] === 'Active'): ?>
                                    <a href="toggle_user_status.php?id=<?php echo $user['UserID']; ?>&status=Deactivated"
                                       class="btn-action btn-deactivate"
                                       onclick="return confirm('Deactivate this user?');">Deactivate</a>
                                <?php elseif (isset($user['Status'])): ?>
                                    <a href="toggle_user_status.php?id=<?php echo $user['UserID']; ?>&status=Active"
                                       class="btn-action btn-activate"
                                       onclick="return confirm('Reactivate this user?');">Activate</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>