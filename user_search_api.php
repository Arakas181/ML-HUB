<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 50); // Max 50 results
    $offset = (int)($_GET['offset'] ?? 0);
    
    try {
        // Build search query
        $whereConditions = [];
        $params = [];
        
        // Check if columns exist first
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $hasFullName = in_array('full_name', $columns);
        $hasBio = in_array('bio', $columns);
        $hasAvatar = in_array('avatar_url', $columns);
        $hasCreatedAt = in_array('created_at', $columns);
        
        // Search by username, email, or full name (if exists)
        if (!empty($search)) {
            $searchConditions = ["username LIKE ?", "email LIKE ?"];
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            
            if ($hasFullName) {
                $searchConditions[] = "full_name LIKE ?";
                $params[] = $searchTerm;
            }
            
            $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        }
        
        // Filter by role
        if (!empty($role) && in_array($role, ['user', 'admin', 'super_admin', 'moderator', 'squad_leader'])) {
            $whereConditions[] = "role = ?";
            $params[] = $role;
        }
        
        // Exclude current user from results
        $whereConditions[] = "id != ?";
        $params[] = $_SESSION['user_id'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : 'WHERE id != ?';
        
        // If no conditions were added, still need to exclude current user
        if (empty($whereConditions)) {
            $params[] = $_SESSION['user_id'];
        }
        
        // Build SELECT clause based on available columns
        $selectFields = ['id', 'username', 'email', 'role'];
        if ($hasFullName) $selectFields[] = 'full_name';
        if ($hasBio) $selectFields[] = 'bio';
        if ($hasAvatar) $selectFields[] = 'avatar_url';
        if ($hasCreatedAt) $selectFields[] = 'created_at';
        
        $selectClause = implode(', ', $selectFields);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $totalCount = $stmt->fetchColumn();
        
        // Get users with pagination
        $query = "SELECT $selectClause FROM users $whereClause ORDER BY username ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove sensitive data (email) for non-admin users
        if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
            foreach ($users as &$user) {
                unset($user['email']);
            }
        }
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        error_log('User search error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage(), 'debug' => true]);
    } catch (Exception $e) {
        error_log('General search error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage(), 'debug' => true]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
