<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include '../../db/config.php';
include '../../functions/dashboardfunctions.php';


// In your dashboard.php script
if (isset($_SESSION['user_id'], $_SESSION['fname'], $_SESSION['lname'], $_SESSION['role'])) {
    $user_id = $_SESSION['user_id'];
    $first_name = $_SESSION['fname'];
    $last_name = $_SESSION['lname'];
    $user_role = $_SESSION['role'];
} else {
    // If the session variables are not set, the user may not be logged in
    // Redirect them to the login page or display an error message
    header("Location: ../login.html");
    exit();
}


// Retrieve and store the data
$total_users = getTotalUsers();
$total_recipes = getTotalRecipes();
$pending_approvals = getPendingApprovals();
$top_users = getTopUsers();
$approval_stats = getApprovalStats();
$recipe_data = getRecipeData();
$user_data = getUserData();
$personal_recipe_count = getPersonalRecipeCount();
$recent_recipes = getRecentRecipes();
$user_recipe_data = getUserRecipeData();
$user_approval_stats = getUserApprovalStats();

// Close the database connection
$conn->close();

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Recipe Sharing Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Alkatra&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="user-profile">
                <img src="../../assets/images/donMoen.jpeg" alt="User Avatar" class="user-avatar">
                <h3><span id="user-name"></span></h3>
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <?php if ($_SESSION['role'] == 1) { ?>
                        <li><a href="../users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <?php } ?>
                    <li><a href="../recipes.php"><i class="fas fa-utensils"></i> Recipe Management</a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../../actions/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($user_role == 1){?>
                <div class="welcome-container">
                <h2>Welcome, Super Admin, to your Dashboard</h2>
            </div>

            <div class="dashboard-layout">
                <div class="left-column">
                    <div class="analytics-cards">
                        <div class="analytics-card">
                            <h3>Total Users</h3>
                            <p class="analytics-number"><?php echo $total_users; ?></p>
                        </div>
                        <div class="analytics-card">
                            <h3>Total Recipes</h3>
                            <p class="analytics-number"><?php echo $total_recipes; ?></p>
                        </div>
                        <div class="analytics-card">
                            <h3>Pending Approvals</h3>
                            <p class="analytics-number"><?php echo $pending_approvals; ?></p>
                        </div>
                    </div>
                    <div class="top-users">
                        <h3>System wide Statistics</h3>
                        <ol>
                            <li>Total Storage Used: 125 GB</li>
                            <li>Average Daily Traffic: 15,000 visitors</li>
                            <li>Average Recipe Rating: 4.2 out of 5</li>
                        </ol>
                    </div>
                    <br>
                    <div class="top-users">
                        <h3>Top 5 Most Active Users</h3>
                        <ol>
                            <?php foreach ($top_users as $user) { ?>
                                    <li><?php echo $user['fname'] . ' ' . $user['lname'] . ' (' . $user['recipe_count'] . ' recipes)'; ?></li>
                                <?php } ?>
                        </ol>
                    </div>
                    <br>
                    <div class="piechart">
                        <h3>User approval statistics</h3>
                        <canvas id="approvalPieChart"></canvas>
                    </div>
                </div>
                <div class="right-column">
                    <div class="chart-container">
                        <h3>Recipes Created per Month</h3>
                        <canvas id="recipeChart"></canvas>
                    </div>
                    <br>
                    <div class="chart-container">
                        <h3>Users registered per Month</h3>
                        <canvas id="UserChart"></canvas>
                    </div>
                </div>
            </div>

                <?php } else if ($user_role == 2) { ?>
                <div class="welcome-container">
                    <h2>Welcome, Admin, to your Dashboard</h2>
                </div>

                <div class="dashboard-layout">
                    <div class="left-column">
                        <div class="analytics-cards">
                            <div class="analytics-card">
                                <h3>Total Recipes</h3>
                                <p class="analytics-number"><?php echo $personal_recipe_count; ?></p>
                            </div>
                        </div>
                        <div class="top-users">
                            <h3>Recent Recipe Submissions</h3>
                            <ol>
                            <?php foreach ($recent_recipes as $recipe) { ?>
                                    <li><?php echo $recipe['name']; ?></li>
                                <?php } ?>
                            </ol>
                        </div>
                        <br>
                        <div class="piechart">
                            <h3>Recipe category distribution</h3>
                            <canvas id="userapprovalPieChart"></canvas>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="chart-container">
                            <h3>Personal recipe submission trends</h3>
                            <canvas id="userrecipeChart"></canvas>
                        </div>
                        <br>
                        <div class="chart-container">
                            <h3>Activeness per Month</h3>
                            <canvas id="activeChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php } else { echo "User role not found"?>
                <?php } ?>
            <footer>
                <p>&copy; 2024 Recipe Sharing Platform. All rights reserved by Austine Omo Naija.</p>
            </footer>
        </main>
    </div>




    <script>
        // Utility function to create charts
        const createChart = (elementId, type, data, options = {}) => {
            const ctx = document.getElementById(elementId);
            if (!ctx) return null;
            
            return new Chart(ctx, {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    ...options
                }
            });
        };

        // Chart configurations
        const barChartOptions = {
            scales: {
                y: { beginAtZero: true }
            }
        };

        const pieChartOptions = {
            maintainAspectRatio: true,
            plugins: {
                title: { display: false }
            }
        };

        // Set user name
        document.getElementById('user-name').textContent = '<?php echo $first_name . ' ' . $last_name; ?>';

        // Initialize charts based on role
        if (<?php echo $user_role; ?> === 1) {
            // Admin charts
            createChart('recipeChart', 'bar', {
                labels: <?php echo json_encode(array_column($recipe_data, 'month')); ?>,
                datasets: [{
                    label: 'Recipes Created',
                    data: <?php echo json_encode(array_column($recipe_data, 'recipe_count')); ?>,
                    backgroundColor: '#4a90e2',
                    borderColor: '#3a7bc8',
                    borderWidth: 1
                }]
            }, barChartOptions);

            createChart('UserChart', 'bar', {
                labels: <?php echo json_encode(array_column($user_data, 'month')); ?>,
                datasets: [{
                    label: 'Users Registered',
                    data: <?php echo json_encode(array_column($user_data, 'user_count')); ?>,
                    backgroundColor: '#4a90e2',
                    borderColor: '#3a7bc8',
                    borderWidth: 1
                }]
            }, barChartOptions);

            createChart('approvalPieChart', 'pie', {
                labels: ['Approved', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $approval_stats['approved_recipes']; ?>,
                        <?php echo $approval_stats['pending_recipes']; ?>
                    ],
                    backgroundColor: ['#4a90e2', '#e74c3c'],
                    borderColor: ['#3a7bc8', '#c0392b'],
                    borderWidth: 1
                }]
            }, pieChartOptions);
        } else {
            // User charts
            createChart('userrecipeChart', 'bar', {
                labels: <?php echo json_encode(array_column($user_recipe_data, 'month')); ?>,
                datasets: [{
                    label: 'Recipes Created',
                    data: <?php echo json_encode(array_column($user_recipe_data, 'recipe_count')); ?>,
                    backgroundColor: '#4a90e2',
                    borderColor: '#3a7bc8',
                    borderWidth: 1
                }]
            }, barChartOptions);

            createChart('userapprovalPieChart', 'pie', {
                labels: ['Approved', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $user_approval_stats['approved_recipes']; ?>,
                        <?php echo $user_approval_stats['pending_recipes']; ?>
                    ],
                    backgroundColor: ['#4a90e2', '#e74c3c'],
                    borderColor: ['#3a7bc8', '#c0392b'],
                    borderWidth: 1
                }]
            }, pieChartOptions);

            createChart('activeChart', 'bar', {
                labels: <?php echo json_encode(array_column($user_recipe_data, 'month')); ?>,
                datasets: [{
                    label: 'Activity Level',
                    data: <?php echo json_encode(array_column($user_recipe_data, 'recipe_count')); ?>,
                    backgroundColor: '#4a90e2',
                    borderColor: '#3a7bc8',
                    borderWidth: 1
                }]
            }, barChartOptions);
        }
    </script>
</body>
</html>