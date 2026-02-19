<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// Handle login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $login_error = 'Username and password are required!';
    } else {
        $user = $db->querySingle("SELECT id, username, password, role FROM users WHERE username = '" . $db->escapeString($username) . "'", true);
        
        if ($user && password_verify($password, $user['password']) && in_array($user['role'], ['admin', 'superadmin'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            header('Location: admin.php');
            exit;
        } else {
            $login_error = 'Invalid username or password, or user is not an admin!';
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Get all tables dari database
function getAllTables($db) {
    $tables = [];
    $res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    return $tables;
}

// Get table columns
function getTableColumns($db, $table) {
    $columns = [];
    $res = $db->query("PRAGMA table_info($table)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    return $columns;
}

// Smart search di table
function smartSearch($db, $table, $search = '') {
    $columns = getTableColumns($db, $table);
    if (empty($columns)) return [];
    
    $searchCols = [];
    foreach ($columns as $col) {
        $lower = strtolower($col);
        if (in_array($lower, ['username', 'email', 'content', 'message', 'text', 'title', 'name'])) {
            $searchCols[] = $col;
        }
    }
    if (empty($searchCols)) $searchCols[] = $columns[0];
    
    $where = '';
    if (!empty($search)) {
        $conditions = array_map(function($col) use ($db, $search) {
            return "$col LIKE " . $db->escapeString('%' . $search . '%');
        }, $searchCols);
        $where = ' WHERE ' . implode(' OR ', $conditions);
    }
    
    $query = "SELECT * FROM $table $where ORDER BY rowid DESC LIMIT 50";
    $result = $db->query($query);
    
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    return $data;
}

// Handle API requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }
    
    $api = $_GET['api'];
    
    if ($api === 'stats') {
        $stats = [];
        foreach (getAllTables($db) as $table) {
            $count = $db->querySingle("SELECT COUNT(*) FROM $table") ?: 0;
            $stats[$table] = $count;
        }
        echo json_encode($stats);
        exit;
    }
    
    if ($api === 'tables') {
        echo json_encode(['tables' => getAllTables($db)]);
        exit;
    }
    
    if ($api === 'search') {
        $table = $_GET['table'] ?? '';
        $search = $_GET['search'] ?? '';
        if (empty($table)) {
            echo json_encode([]);
        } else {
            echo json_encode(smartSearch($db, $table, $search));
        }
        exit;
    }
    
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $table = $_POST['table'] ?? '';
        $id = $_POST['id'] ?? '';
        
        if ($table && $id) {
            $cols = getTableColumns($db, $table);
            $idCol = in_array('id', $cols) ? 'id' : 'rowid';
            $db->exec("DELETE FROM $table WHERE $idCol = " . (int)$id);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// LOGIN PAGE
if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PostAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
        }

        .error-msg {
            background: #fee;
            color: #d32;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 25px;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .form-group input {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Admin Panel</h1>
                <p>PostAja Administration</p>
            </div>

            <?php if ($login_error): ?>
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="login-btn">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// ADMIN DASHBOARD PAGE
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PostAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f5f7fa;
            color: #333;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-logo {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .sidebar-item {
            padding: 12px 14px;
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            user-select: none;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.25);
            border-left: 4px solid white;
            padding-left: 10px;
        }

        .sidebar-item i {
            width: 20px;
        }

        .logout-btn {
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .user-info {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Tabs */
        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            overflow-x: auto;
            padding-bottom: 0;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            padding: 12px 18px;
            border: none;
            background: none;
            color: #999;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
            font-size: 0.95rem;
        }

        .tab-btn:hover {
            color: #333;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-top: 3px solid #667eea;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #999;
            text-transform: capitalize;
        }

        /* Search Box */
        .search-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-section input {
            flex: 1;
            min-width: 200px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .search-section input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-section button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .search-section button:hover {
            background: #764ba2;
        }

        /* Data List */
        .data-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 700px;
            overflow-y: auto;
        }

        .data-row {
            background: white;
            padding: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            flex-wrap: wrap;
            gap: 10px;
        }

        .data-row:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .data-info {
            flex: 1;
            min-width: 200px;
        }

        .data-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .data-meta {
            font-size: 0.85rem;
            color: #999;
        }

        .data-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete {
            background: #ff6b6b;
            color: white;
        }

        .btn-delete:hover {
            background: #fa5252;
        }

        /* Loading & Empty State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-box h3 {
            margin-bottom: 20px;
            font-weight: 700;
        }

        .modal-box p {
            margin-bottom: 20px;
            color: #666;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
        }

        .modal-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-btn-confirm {
            background: #ff6b6b;
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #fa5252;
        }

        .modal-btn-cancel {
            background: #ddd;
            color: #333;
        }

        .modal-btn-cancel:hover {
            background: #bbb;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
                padding: 15px;
            }

            .main-content {
                margin-left: 200px;
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                flex-direction: row;
                align-items: center;
                margin-bottom: 20px;
            }

            .sidebar-logo {
                margin-bottom: 0;
                flex: 1;
            }

            .sidebar-menu {
                display: none;
            }

            .logout-btn {
                margin: 0;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header h1 {
                font-size: 1.3rem;
            }

            .user-info {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .data-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .data-actions {
                width: 100%;
            }

            .action-btn {
                flex: 1;
                text-align: center;
            }

            .search-section input {
                min-width: 100%;
            }

            .tabs-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .tab-btn {
                padding: 10px 14px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                flex-direction: column;
                height: auto;
                padding: 15px 10px;
            }

            .sidebar-logo {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }

            .sidebar-logo i {
                display: none;
            }

            .main-content {
                padding: 15px 10px;
            }

            .header h1 {
                font-size: 1.1rem;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 1.8rem;
            }

            .stat-label {
                font-size: 0.8rem;
            }

            .data-row {
                padding: 12px;
            }

            .tab-btn {
                padding: 10px 12px;
                font-size: 0.8rem;
            }

            .modal-box {
                width: 95%;
                padding: 20px;
            }
        }

        /* Scrollbar styling */
        .data-container::-webkit-scrollbar,
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .data-container::-webkit-scrollbar-track,
        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .data-container::-webkit-scrollbar-thumb,
        .sidebar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .data-container::-webkit-scrollbar-thumb:hover,
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <i class="bi bi-shield-lock"></i>
                Admin
            </div>
            <div class="sidebar-menu" id="sidebarMenu"></div>
            <a href="?logout=1" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($_SESSION['admin_username']) ?>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs-container" id="tabsNav">
                <button class="tab-btn active" onclick="switchTab('dashboard')">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </button>
            </div>

            <!-- Dashboard Tab -->
            <div id="tab-dashboard" class="tab-content active">
                <div class="stats-grid" id="statsContainer">
                    <div class="loading"><i class="bi bi-hourglass-split"></i> Loading...</div>
                </div>
            </div>

            <!-- Data Tabs -->
            <div id="dataPanes"></div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box">
            <h3><i class="bi bi-exclamation-triangle"></i> Hapus Data</h3>
            <p id="deleteMessage"></p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-confirm" onclick="confirmDelete()">Hapus</button>
                <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Batal</button>
            </div>
        </div>
    </div>

    <script>
        let allTables = [];
        let pendingDelete = null;

        window.addEventListener('DOMContentLoaded', () => {
            loadTables();
            loadStats();
        });

        function loadTables() {
            fetch('admin.php?api=tables')
                .then(r => r.json())
                .then(data => {
                    allTables = data.tables || [];
                    renderTabs();
                    renderDataPanes();
                })
                .catch(err => console.error('Error loading tables:', err));
        }

        function renderTabs() {
            const nav = document.getElementById('tabsNav');
            let html = '<button class="tab-btn active" onclick="switchTab(\'dashboard\')"><i class="bi bi-speedometer2"></i> Dashboard</button>';
            
            allTables.forEach(t => {
                html += `<button class="tab-btn" onclick="switchTab('table-${t}')" data-table="${t}"><i class="bi bi-table"></i> ${formatName(t)}</button>`;
            });
            
            nav.innerHTML = html;
        }

        function renderDataPanes() {
            const panes = document.getElementById('dataPanes');
            let html = '';
            
            allTables.forEach(t => {
                html += `
                    <div class="tab-content" id="tab-table-${t}">
                        <div class="search-section">
                            <input type="text" id="search-${t}" placeholder="Search in ${formatName(t)}..." onkeyup="if(event.key==='Enter') searchTable('${t}')">
                            <button onclick="searchTable('${t}')"><i class="bi bi-search"></i> Search</button>
                        </div>
                        <div class="data-container" id="data-${t}">
                            <div class="loading"><i class="bi bi-hourglass-split"></i> Loading...</div>
                        </div>
                    </div>
                `;
            });
            
            panes.innerHTML = html;
        }

        function formatName(name) {
            return name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ');
        }

        function switchTab(tab) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(p => p.classList.remove('active'));
            
            // Add active to clicked tab
            if (tab === 'dashboard') {
                document.querySelector('[onclick*="dashboard"]').classList.add('active');
            } else {
                const tableTab = document.querySelector(`[data-table="${tab.replace('table-', '')}"]`);
                if (tableTab) tableTab.classList.add('active');
            }
            
            // Show tab content
            const tabId = 'tab-' + (tab || 'dashboard');
            const pane = document.getElementById(tabId);
            if (pane) {
                pane.classList.add('active');
                
                // Load data for table tabs
                if (tab && tab.startsWith('table-')) {
                    const table = tab.replace('table-', '');
                    loadTableData(table);
                }
            }
        }

        function loadStats() {
            fetch('admin.php?api=stats')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('statsContainer');
                    let html = '';
                    
                    for (const [table, count] of Object.entries(data)) {
                        html += `
                            <div class="stat-card">
                                <div class="stat-label">${formatName(table)}</div>
                                <div class="stat-value">${count}</div>
                            </div>
                        `;
                    }
                    
                    container.innerHTML = html || '<div class="empty-state">Tidak ada data</div>';
                })
                .catch(err => {
                    console.error('Error:', err);
                    document.getElementById('statsContainer').innerHTML = '<div class="empty-state">Error loading statistics</div>';
                });
        }

        function loadTableData(table) {
            fetch(`admin.php?api=search&table=${encodeURIComponent(table)}`)
                .then(r => r.json())
                .then(data => renderTableData(table, data))
                .catch(err => console.error('Error:', err));
        }

        function renderTableData(table, data) {
            const container = document.getElementById(`data-${table}`);
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i> Tidak ada data</div>';
                return;
            }

            const cols = Object.keys(data[0]);
            let html = '';
            
            data.forEach(row => {
                const title = Object.values(row)[0] || 'No title';
                const meta = cols.slice(1, 3).map(c => `${formatName(c)}: ${row[c] || '-'}`).join(' | ');
                const id = row.id || row.rowid;
                
                html += `
                    <div class="data-row">
                        <div class="data-info">
                            <div class="data-title">${title}</div>
                            <div class="data-meta">${meta}</div>
                        </div>
                        <div class="data-actions">
                            <button class="action-btn btn-delete" onclick="deletePrompt('${table}', ${id}, '${title}')">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function searchTable(table) {
            const search = document.getElementById(`search-${table}`).value;
            fetch(`admin.php?api=search&table=${encodeURIComponent(table)}&search=${encodeURIComponent(search)}`)
                .then(r => r.json())
                .then(data => renderTableData(table, data))
                .catch(err => console.error('Error:', err));
        }

        function deletePrompt(table, id, name) {
            pendingDelete = { table, id };
            document.getElementById('deleteMessage').textContent = `Yakin hapus "${name || id}" dari tabel ${formatName(table)}?`;
            document.getElementById('deleteModal').classList.add('active');
        }

        function confirmDelete() {
            if (!pendingDelete) return;
            
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('table', pendingDelete.table);
            fd.append('id', pendingDelete.id);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => {
                    loadTableData(pendingDelete.table);
                    loadStats();
                    closeDeleteModal();
                })
                .catch(err => {
                    alert('Error: ' + err);
                    closeDeleteModal();
                });
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            pendingDelete = null;
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') closeDeleteModal();
        });
    </script>
</body>
</html>
