<?php
// session_start(); remember state for web apps,
session_start(); 
// Ensure the user is logged in.
if (!isset($_SESSION['role_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';

// Check if the logged in user is Admin (assuming RoleID 1 represents Admin)
// if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
//     header("Location: ../login.php"); // redirect if not admin
//     exit();
// }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Secure Banking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .dashboard-container {
            max-width: 600px;
            margin: 60px auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            color: #004080;
            margin-bottom: 30px;
        }

        .nav-link-btn {
            display: block;
            margin: 12px 0;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            background-color: #004080;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .nav-link-btn:hover {
            background-color: #002e5f;
        }

        .logout-link {
            display: inline-block;
            margin-top: 30px;
            text-decoration: none;
            color: #004080;
        }

        .logout-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <h1>Admin Dashboard</h1>

        <a class="nav-link-btn" href="admin_create_role.php"> Create Role</a>
        <a class="nav-link-btn" href="admin_manage_users.php"> Manage Users</a>
        <a class="nav-link-btn" href="admin_view_audit_logs.php"> View Audit Logs</a>
        <a class="btn btn-outline-danger" href="../login.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        <!-- <a class="logout-link" href="../login.php">🔒 Logout</a> -->
    </div>

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
