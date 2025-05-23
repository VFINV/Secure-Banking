<?php

session_start(); 
require_once '../db_connect.php';

try {
    $pdo = new PDO("sqlsrv:server=$serverName;Database=$databaseName", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $ex) {
    die("Database connection failed: " . $ex->getMessage());
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $userID = (int)$_GET['id'];
    $newStatus = ($_GET['status'] === 'Active') ? 'Active' : 'Deactivated';

    $sql = "UPDATE [User] SET Status = :status WHERE UserID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $userID
    ]);

    // Fetch user details for audit log
    $userQuery = "SELECT U.User_Name, R.Role_Name FROM [User] U JOIN Role R ON U.RoleID = R.RoleID WHERE U.UserID = :id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':id' => $userID]);
    $userDetails = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($userDetails) {
        $auditSql = "INSERT INTO Audit_Log (UserID, User_Name, Role_Name, Action_Type, Action_Date, Status, Message)
                     VALUES (:userid, :username, :rolename, :action, GETDATE(), :status, :message)";
        $auditStmt = $pdo->prepare($auditSql);
        $auditStmt->execute([
            ':userid'   => $userID,
            ':username' => $userDetails['User_Name'],
            ':rolename' => $userDetails['Role_Name'],
            ':action'   => 'Status Change',
            ':status'   => $newStatus,
            ':message'  => "User status changed to $newStatus"
        ]);
    }
}


// Redirect back to the manage users page
header("Location: admin_manage_users.php");
exit;