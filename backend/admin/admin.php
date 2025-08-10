<?php
session_start();

// Auto logout logic
$timeout_duration = 1200; //20 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /login?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check login
if (!isset($_SESSION["admin"])) {
    header("Location: /login");
    exit();
}
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: /logout");
    exit();
}

// DB Connection
$conn = new mysqli("localhost", "root", "root", "icspl1");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch submissions
$sql = "SELECT id, name, phone_number, gmail, services, sectors, additional_message FROM users";
$result = $conn->query($sql);

// Pagination for login logs
$logs_per_page = 10;
$current_page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
$offset = ($current_page - 1) * $logs_per_page;

// Fetch login logs with pagination
$sql_logs = "SELECT SQL_CALC_FOUND_ROWS id, email, login_time, ip_address, user_agent 
             FROM admin_login_logs12 
             ORDER BY login_time DESC 
             LIMIT $offset, $logs_per_page";
$result_logs = $conn->query($sql_logs);

// Get total logs count
$total_logs = $conn->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_pages = ceil($total_logs / $logs_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background-color: #1e293b;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #334155;
            flex-wrap: wrap;
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .admin-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #94a3b8;
        }
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #f8fafc;
            text-decoration: none;
            font-weight: 600;
            position: relative;
            padding: 10px 6px;
            transition: color 0.3s ease;
        }
        .nav-links a::after {
            content: "";
            position: absolute;
            width: 0%;
            height: 3px;
            bottom: 0;
            left: 0;
            background-color: #3b82f6;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        .nav-links a:hover {
            color: #38bdf8;
        }
        .nav-links a:hover::after {
            width: 100%;
        }
        .nav-links .logout-btn {
            background-color: #ef4444;
            color: white !important;
            padding: 10px 16px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .nav-links .logout-btn:hover {
            background-color: #dc2626;
        }
        h1, h2 {
            text-align: center;
            margin: 40px 0 20px;
            font-size: 32px;
        }
        .search-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            padding: 0 20px;
        }
        #searchInput, #logDatePicker {
            padding: 12px 16px;
            width: 340px;
            border-radius: 10px;
            border: none;
            font-size: 16px;
            background-color: #1e293b;
            color: white;
            transition: all 0.3s;
            margin: 10px;
        }
        #searchInput:focus, #logDatePicker:focus {
            outline: none;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        .table-container {
            width: 98%;
            max-width: 1400px;
            margin: 0 auto 40px;
            background-color: #1e293b;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        th {
            background-color: #0f172a;
            font-weight: 600;
            text-transform: uppercase;
            color: #38bdf8;
        }
        tr:hover td {
            background-color: #334155;
        }
        td {
            color: #f1f5f9;
        }
        /* Previous styles remain the same... */

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .pagination a {
            background-color: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
        }
        
        .pagination a:hover {
            background-color: #334155;
        }
        
        .pagination .current {
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
        }
        
        .pagination .disabled {
            color: #64748b;
            pointer-events: none;
        }
        
        .logs-per-page {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
            padding-right: 20px;
        }
        
        .logs-per-page select {
            padding: 8px;
            border-radius: 5px;
            background-color: #1e293b;
            color: white;
            border: 1px solid #334155;
        }

        @media screen and (max-width: 768px) {
            .nav-links {
                justify-content: center;
                gap: 12px;
            }
            h1, h2 { font-size: 24px; }
            .admin-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        @media screen and (max-width: 480px) {
            table {
                font-size: 14px;
                min-width: 100%;
            }
            th, td {
                padding: 10px;
            }
            #searchInput, #logDatePicker {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="admin-info">
        <img src="https://img.freepik.com/premium-photo/3d-sales-manager-character-leading-with-animated-ambition_893571-11254.jpg" alt="Admin">
        <strong>Welcome, <?php echo htmlspecialchars($_SESSION["admin"] ?? 'Admin'); ?></strong>
    </div>
    <nav class="nav-links">
        <a href="/admin">Dashboard</a>
        <a href="/upload">Upload Files</a>
        <a href="/users">User List</a>
        <a href="/admin-users">Admin Users</a> <!-- ✅ Added Admin Users link -->
        <a href="?logout=true" class="logout-btn">Logout</a>
    </nav>
</div>

<h1>📥 Contact Submissions</h1>
<div class="search-container">
    <input type="text" id="searchInput" placeholder="Search email or name...">
</div>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Services</th>
                <th>Sectors</th>
                <th>Additional Message</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>" . htmlspecialchars($row["id"]) . "</td>
                            <td>" . htmlspecialchars($row["name"]) . "</td>
                            <td>" . htmlspecialchars($row["phone_number"]) . "</td>
                            <td>" . htmlspecialchars($row["gmail"]) . "</td>
                            <td>" . htmlspecialchars($row["services"]) . "</td>
                            <td>" . htmlspecialchars($row["sectors"]) . "</td>
                            <td>" . htmlspecialchars($row["additional_message"]) . "</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='7' style='text-align:center;'>No submissions found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<h2>Admin Login Logs</h2>
<div class="search-container">
    <input type="date" id="logDatePicker" />
    <div class="logs-per-page">
        <select id="logsPerPage" onchange="updateLogsPerPage()">
            <option value="10" <?= $logs_per_page == 10 ? 'selected' : '' ?>>10 per page</option>
            <option value="25" <?= $logs_per_page == 25 ? 'selected' : '' ?>>25 per page</option>
            <option value="50" <?= $logs_per_page == 50 ? 'selected' : '' ?>>50 per page</option>
            <option value="100" <?= $logs_per_page == 100 ? 'selected' : '' ?>>100 per page</option>
        </select>
    </div>
</div>
<div class="table-container">
    <table id="logTable">
        <thead>
            <tr>
                <th>Log ID</th>
                <th>Admin Email</th>
                <th>Login Time</th>
                <th>IP Address</th>
                <th>User Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result_logs && $result_logs->num_rows > 0) {
                while ($log = $result_logs->fetch_assoc()) {
                    echo "<tr>
                            <td>" . htmlspecialchars($log["id"]) . "</td>
                            <td>" . htmlspecialchars($log["email"]) . "</td>
                            <td>" . htmlspecialchars($log["login_time"]) . "</td>
                            <td>" . htmlspecialchars($log["ip_address"]) . "</td>
                            <td>" . htmlspecialchars($log["user_agent"]) . "</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center;'>No login logs found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?log_page=1">« First</a>
            <a href="?log_page=<?= $current_page - 1 ?>">‹ Previous</a>
        <?php else: ?>
            <span class="disabled">« First</span>
            <span class="disabled">‹ Previous</span>
        <?php endif; ?>
        
        <?php
        // Show page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            echo '<span>...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <?php if ($i == $current_page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?log_page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor;
        
        if ($end_page < $total_pages) {
            echo '<span>...</span>';
        }
        ?>
        
        <?php if ($current_page < $total_pages): ?>
            <a href="?log_page=<?= $current_page + 1 ?>">Next ›</a>
            <a href="?log_page=<?= $total_pages ?>">Last »</a>
        <?php else: ?>
            <span class="disabled">Next ›</span>
            <span class="disabled">Last »</span>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 10px; color: #94a3b8;">
        Showing <?= ($offset + 1) ?> to <?= min($offset + $logs_per_page, $total_logs) ?> of <?= $total_logs ?> entries
    </div>
</div>


<script>
    document.getElementById("searchInput").addEventListener("keyup", function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("table tbody tr");

        rows.forEach(row => {
            const name = row.cells[1]?.textContent.toLowerCase() || "";
            const email = row.cells[3]?.textContent.toLowerCase() || "";
            row.style.display = (name.includes(filter) || email.includes(filter)) ? "" : "none";
        });
    });

    document.getElementById("logDatePicker").addEventListener("change", function () {
        const selectedDate = this.value;
        const rows = document.querySelectorAll("#logTable tbody tr");

        rows.forEach(row => {
            const loginTime = row.cells[2]?.textContent || "";
            const logDate = loginTime.split(" ")[0];
            row.style.display = (!selectedDate || logDate === selectedDate) ? "" : "none";
        });
    });
        // Previous JavaScript remains the same...

    function updateLogsPerPage() {
        const perPage = document.getElementById('logsPerPage').value;
        window.location.href = `?log_page=1&per_page=${perPage}`;
    }

    document.getElementById("logDatePicker").addEventListener("change", function () {
        const selectedDate = this.value;
        const rows = document.querySelectorAll("#logTable tbody tr");

        rows.forEach(row => {
            const loginTime = row.cells[2]?.textContent || "";
            const logDate = loginTime.split(" ")[0];
            row.style.display = (!selectedDate || logDate === selectedDate) ? "" : "none";
        });
        
        // Hide pagination when filtering by date
        document.querySelector('.pagination').style.display = selectedDate ? 'none' : 'flex';
    });
</script>

</body>
</html>
