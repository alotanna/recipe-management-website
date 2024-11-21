<?php

// Get the total number of users
function getTotalusers() {
    global $conn;
    $sql = "SELECT COUNT(*) as total_users FROM users";
    $result = $conn->query($sql);
    return $result->fetch_assoc()['total_users'];
}

// Get the total number of recipes
function getTotalRecipes() {
    global $conn;
    $sql = "SELECT COUNT(*) as total_recipes FROM foods";
    $result = $conn->query($sql);
    return $result->fetch_assoc()['total_recipes'];
}

// Get the number of pending approvals
function getPendingApprovals() {
    global $conn;
    $sql = "SELECT COUNT(*) as pending_approvals FROM foods WHERE is_approved = 0";
    $result = $conn->query($sql);
    return $result->fetch_assoc()['pending_approvals'];
}

// Get the top 5 most active users
function getTopusers() {
    global $conn;
    $sql = "SELECT u.fname, u.lname, COUNT(f.created_by) as recipe_count
            FROM users u
            LEFT JOIN foods f ON u.user_id = f.created_by
            GROUP BY u.user_id
            ORDER BY recipe_count DESC
            LIMIT 5";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get the number of approved and pending recipes
function getApprovalStats() {
    global $conn;
    $sql = "SELECT 
            (SELECT COUNT(*) FROM foods WHERE is_approved = 1) AS approved_recipes,
            (SELECT COUNT(*) FROM foods WHERE is_approved = 0) AS pending_recipes";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// Get the monthly recipe and user registration data
function getRecipeData() {
    global $conn;
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%b') AS month,
            COUNT(*) AS recipe_count 
            FROM foods
            GROUP BY month
            ORDER BY 
                CASE month 
                    WHEN 'Jan' THEN 1 
                    WHEN 'Feb' THEN 2 
                    WHEN 'Mar' THEN 3 
                    WHEN 'Apr' THEN 4 
                    WHEN 'May' THEN 5 
                    WHEN 'Jun' THEN 6 
                    WHEN 'Jul' THEN 7 
                    WHEN 'Aug' THEN 8 
                    WHEN 'Sep' THEN 9 
                    WHEN 'Oct' THEN 10 
                    WHEN 'Nov' THEN 11 
                    WHEN 'Dec' THEN 12 
                END;";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getUserData() {
    global $conn;
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%b') AS month,
            COUNT(*) AS user_count
            FROM users
            GROUP BY month
            ORDER BY 
                CASE month 
                    WHEN 'Jan' THEN 1 
                    WHEN 'Feb' THEN 2 
                    WHEN 'Mar' THEN 3 
                    WHEN 'Apr' THEN 4 
                    WHEN 'May' THEN 5 
                    WHEN 'Jun' THEN 6 
                    WHEN 'Jul' THEN 7 
                    WHEN 'Aug' THEN 8 
                    WHEN 'Sep' THEN 9 
                    WHEN 'Oct' THEN 10 
                    WHEN 'Nov' THEN 11 
                    WHEN 'Dec' THEN 12 
                END;";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get the personal recipe data for the logged-in user
function getPersonalRecipeCount() {
    global $conn, $user_id;
    $sql = "SELECT COUNT(*) as total_recipes 
            FROM foods 
            WHERE created_by = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total_recipes'];
}

// Get the 5 most recent recipe submissions by the logged-in user
function getRecentRecipes() {
    global $conn, $user_id;
    $sql = "SELECT name
            FROM foods
            WHERE created_by = ?
            ORDER BY created_at DESC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get the monthly recipe data for a specific user
function getUserRecipeData() {
    global $conn, $user_id;
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%b') AS month,
            COUNT(*) AS recipe_count 
            FROM foods 
            WHERE created_by = ?
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
            GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%b')
            ORDER BY MONTH(created_at)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get the number of approved and pending recipes for a specific user
function getUserApprovalStats() {
    global $conn, $user_id;
    $sql = "SELECT 
            (SELECT COUNT(*) FROM foods WHERE is_approved = 1 AND created_by = ?) AS approved_recipes,
            (SELECT COUNT(*) FROM foods WHERE is_approved = 0 AND created_by = ?) AS pending_recipes";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>
