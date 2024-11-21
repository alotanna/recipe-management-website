<?php
include '../db/config.php';
// Start the session to access session variables
session_start();

// Check if the user is logged in by verifying the session ID
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

// Check if user has admin role (role = 1)
if ($_SESSION['role'] != 1) {
    echo "<script>
        alert('You do not have permission to access this page.');
        window.location.href = 'dashboard.php';
      </script>";
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            $userId = $_POST['userId'];
            $fname = $_POST['fname'];
            $lname = $_POST['lname'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            
            $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->bind_param("sssii", $fname, $lname, $email, $role, $userId);
            
            $response = ['success' => $stmt->execute()];
            echo json_encode($response);
            exit();
            
        case 'delete':
            $userId = $_POST['userId'];
            
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            
            $response = ['success' => $stmt->execute()];
            echo json_encode($response);
            exit();
    }
}

// Handle GET requests for user details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_user') {
    if (isset($_GET['id'])) {
        $userId = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT user_id, fname, lname, email, role, created_at, updated_at FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Recipe Sharing Platform</title>
    <link rel="stylesheet" href="../assets/css/Users_recipes_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Alkatra&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
        <aside class="sidebar">
            <div class="user-profile">
                <img src="../assets/images/donMoen.jpeg" alt="User Avatar" class="user-avatar">
                <h3><span id="user-name"></span></h3>
            </div>
            <nav>
                <ul>
                    <li><a href="admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <?php if ($_SESSION['role'] == 1) { ?>
                        <li><a href="users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <?php } ?>
                    <li><a href="recipes.php"><i class="fas fa-utensils"></i> Recipe Management</a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../actions/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-container">
                <h2>User management</h2>
            </div>

            <section id="user-list">
                <h2>User List</h2>
                <table id="userTable">
                    <thead>
                        <tr>
                            
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registration Date</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT user_id, fname, lname, email, role, created_at, updated_at FROM users ORDER BY user_id";
                        $result = $conn->query($query);

                        while ($row = $result->fetch_assoc()) {
                            $role_text = $row['role'] == 1 ? 'SuperAdmin' : ($row['role'] == 2 ? 'Admin' : 'User');
                            echo "<tr data-id='{$row['user_id']}'>";
                            echo "<td>{$row['user_id']}</td>";
                            echo "<td>{$row['fname']} {$row['lname']}</td>";
                            echo "<td>{$row['email']}</td>";
                            echo "<td>{$role_text}</td>";
                            echo "<td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>";
                            echo "<td>" . date('Y-m-d H:i', strtotime($row['updated_at'])) . "</td>";
                            echo "<td>
                                    <button class='action-btn view-btn' onclick='viewUser({$row['user_id']})'>View <i class='fas fa-eye'></i></button>
                                    <button class='action-btn edit-btn' onclick='editUser({$row['user_id']})'>Edit <i class='fas fa-edit'></i></button>
                                    <button class='action-btn delete-btn' onclick='deleteUser({$row['user_id']})'>Delete <i class='fas fa-trash-alt'></i></button>
                                  </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>User Details</h2>
            <div id="userDetails"></div>
        </div>
    </div>

   <!-- Edit User Modal -->
   <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit User</h2>
            <form id="editForm" class="form">
                <input type="hidden" id="editUserId">
                
                <label for="editFName">First Name:</label>
                <input type="text" id="editFName" required>
                <div id="fnameError" class="error"></div>
                
                <label for="editLName">Last Name:</label>
                <input type="text" id="editLName" required>
                <div id="lnameError" class="error"></div>
                
                <label for="editEmail">Email:</label>
                <input type="email" id="editEmail" required>
                <div id="emailError" class="error"></div>
                
                <label for="editRole">Role:</label>
                <select id="editRole" required>
                    <option value="3">Regular User</option>
                    <option value="2">Moderator</option>
                    <option value="1">Admin</option>
                </select>
                <div id="roleError" class="error"></div>
                
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>


    <footer>
        <p>&copy; 2024 Recipe Sharing Platform. All rights reserved by Austine Omo Naija.</p>
    </footer>

    <script>
        // Set the user's name based on the session data
        document.getElementById('user-name').textContent = '<?php echo $_SESSION['fname'] . ' ' . $_SESSION['lname']; ?>';

        // View User
        function viewUser(userId) {
            fetch(`users.php?action=get_user&id=${userId}`)
                .then(response => response.json())
                .then(user => {
                    const userDetails = document.getElementById("userDetails");
                    userDetails.innerHTML = `
                        <p><strong>ID:</strong> ${user.user_id}</p>
                        <p><strong>Name:</strong> ${user.fname} ${user.lname}</p>
                        <p><strong>Email:</strong> ${user.email}</p>
                        <p><strong>Role:</strong> ${user.role === 1 ? 'Admin' : (user.role === 2 ? 'Moderator' : 'User')}</p>
                        <p><strong>Registration Date:</strong> ${new Date(user.created_at).toLocaleString()}</p>
                        <p><strong>Last Updated:</strong> ${new Date(user.updated_at).toLocaleString()}</p>
                    `;
                    document.getElementById("viewModal").style.display = "block";
                })
                .catch(error => console.error('Error:', error));
        }

        // Edit User
        function editUser(userId) {
            fetch(`users.php?action=get_user&id=${userId}`)
                .then(response => response.json())
                .then(user => {
                    document.getElementById("editUserId").value = user.user_id;
                    document.getElementById("editFName").value = user.fname;
                    document.getElementById("editLName").value = user.lname;
                    document.getElementById("editEmail").value = user.email;
                    document.getElementById("editRole").value = user.role;
                    document.getElementById("editModal").style.display = "block";
                })
                .catch(error => console.error('Error:', error));
        }

        // Update User
        document.getElementById("editForm").onsubmit = function(e) {
            e.preventDefault();
            if (validateForm()) {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('userId', document.getElementById("editUserId").value);
                formData.append('fname', document.getElementById("editFName").value);
                formData.append('lname', document.getElementById("editLName").value);
                formData.append('email', document.getElementById("editEmail").value);
                formData.append('role', document.getElementById("editRole").value);

                fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        ('User updated successfully');
                        location.reload();
                    } else {
                        alert('Error updating user');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        };

        // Delete User
        function deleteUser(userId) {
            if (confirm("Are you sure you want to delete this user?")) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('userId', userId);

                fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting user');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        // Form validation
        function validateForm() {
            const fname = document.getElementById("editFName").value;
            const lname = document.getElementById("editLName").value;
            const email = document.getElementById("editEmail").value;
            const role = document.getElementById("editRole").value;
            let isValid = true;

            // Name validation
            if (fname.trim() === "") {
                document.getElementById("fnameError").textContent = "First name is required";
                isValid = false;
            } else {
                document.getElementById("fnameError").textContent = "";
            }

            if (lname.trim() === "") {
                document.getElementById("lnameError").textContent = "Last name is required";
                isValid = false;
            } else {
                document.getElementById("lnameError").textContent = "";
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                document.getElementById("emailError").textContent = "Please enter a valid email address";
                isValid = false;
            } else {
                document.getElementById("emailError").textContent = "";
            }

            // Role validation
            if (role === "") {
                document.getElementById("roleError").textContent = "Role is required";
                isValid = false;
            } else {
                document.getElementById("roleError").textContent = "";
            }

            return isValid;
        }

        // Close modals
        document.querySelectorAll(".close").forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest(".modal").style.display = "none";
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>