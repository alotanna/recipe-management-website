<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include '../db/config.php';
include '../functions/recipefunctions.php';


// Session check and initialization
if (isset($_SESSION['user_id'], $_SESSION['fname'], $_SESSION['lname'], $_SESSION['role'])) {
    $user_id = $_SESSION['user_id'];
    $first_name = $_SESSION['fname'];
    $last_name = $_SESSION['lname'];
    $user_role = $_SESSION['role'];
} else {
    header("Location: login.html");
    exit();
}

// Get the personal recipe count for the logged-in user
$sql = "SELECT COUNT(*) as total_recipes FROM foods WHERE created_by = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$personal_recipe_count = $result->fetch_assoc()['total_recipes'];


// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $recipeData = [
                'name' => $_POST['title'],
                'type' => $_POST['type'],
                'origin' => $_POST['origin'],
                'instructions' => $_POST['instructions'],
                'description' => $_POST['description'],
                'prep_time' => $_POST['prepTime'],
                'cook_time' => $_POST['cookTime'],
                'serving_size' => $_POST['servingSize'],
                'calories' => $_POST['calories'],
                'image_url' => $_POST['image'],
                'is_healthy' => $_POST['isHealthy'],
                'created_by' => $_SESSION['user_id'],
                'nutrition' => json_decode($_POST['nutrition'], true),
                'ingredients' => json_decode($_POST['ingredients'], true)
            ];
            
            $response = createRecipe($recipeData);
            echo json_encode($response);
            break;
            
        case 'update':
            $recipeData = [
                'name' => $_POST['title'],
                'type' => $_POST['type'],
                'origin' => $_POST['origin'],
                'instructions' => $_POST['instructions'],
                'description' => $_POST['description'],
                'prep_time' => $_POST['prepTime'],
                'cook_time' => $_POST['cookTime'],
                'serving_size' => $_POST['servingSize'],
                'calories' => $_POST['calories'],
                'image_url' => $_POST['image'],
                'is_healthy' => $_POST['isHealthy'],
                'nutrition' => json_decode($_POST['nutrition'], true),
                'ingredients' => json_decode($_POST['ingredients'], true)
            ];
            
            $response = updateRecipe($_POST['recipeId'], $recipeData);
            echo json_encode($response);
            break;
            
        case 'delete':
            $response = deleteRecipe($_POST['recipeId']);
            echo json_encode($response);
            break;

    }
    exit();
}

// Handle GET requests for recipe details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_recipe') {
    if (isset($_GET['id'])) {
        $recipeId = (int)$_GET['id'];
        
        $sql = "SELECT f.*, u.fname, u.lname,
                GROUP_CONCAT(DISTINCT CONCAT(i.name, ':', r.quantity, ':', r.unit, ':', r.optional) SEPARATOR '|') as ingredients,
                nf.protein, nf.carbohydrates, nf.fat, nf.fiber, nf.sugar, nf.sodium
                FROM Foods f
                JOIN users u ON f.created_by = u.user_id
                LEFT JOIN recipes r ON f.food_id = r.food_id
                LEFT JOIN ingredients i ON r.ingredient_id = i.ingredient_id
                LEFT JOIN nutritionfacts nf ON f.food_id = nf.food_id
                WHERE f.food_id = ?
                GROUP BY f.food_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($recipe = $result->fetch_assoc()) {
            echo json_encode($recipe);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Recipe not found']);
        }
        exit();
    }
}

// Get recipes based on user role
$recipes = ($user_role == 1) ? getAllRecipes() : getPersonalRecipes($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Management - Recipe Sharing Platform</title>
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

        <?php if ($_SESSION['role'] == 1) { ?>
        <main class="main-content">
            <div class="welcome-container">
                <h2>Recipe Management</h2>
                <h3>Total recipes: <?php echo count(getAllRecipes()); ?></h3>
            </div>
        <?php } else { ?>
        <main class="main-content">
            <div class="welcome-container">
                <h2>My Recipes</h2>
                <h3>Total recipes: <?php echo $personal_recipe_count; ?></h3>
            </div>
        <?php } ?>

            <section id="recipe-list">
                <h2><?php echo $_SESSION['role'] == 1 ? 'Recipe List' : 'My Recipe List'; ?></h2>
                <button id="add-recipe-btn"><i class="fas fa-plus"></i> Add New Recipe</button>
                <table id="recipeTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <!-- Add Recipe Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New Recipe</h2>
            <form id="addForm" class="form">
                <label for="addTitle">Recipe Title:</label>
                <input type="text" id="addTitle" required>
                <div id="titleError" class="error"></div>

                <label for="addType">Recipe Type:</label>
                <select id="addType" required>
                    <option value="">Select Recipe Type</option>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner">Dinner</option>
                    <option value="snack">Snack</option>
                    <option value="dessert">Dessert</option>
                </select>
                <div id="typeError" class="error"></div>

                <label for="addOrigin">Origin:</label>
                <input type="text" id="addOrigin" required>
                <div id="originError" class="error"></div>

                <label for="addInstructions">Instructions:</label>
                <textarea id="addInstructions" required></textarea>
                <div id="instructionsError" class="error"></div>

                <label for="addDescription">Description:</label>
                <textarea id="addDescription" required></textarea>
                <div id="descriptionError" class="error"></div>

                <label for="addPrepTime">Preparation Time (minutes):</label>
                <input type="number" id="addPrepTime" min="1" required>
                <div id="prepTimeError" class="error"></div>

                <label for="addCookTime">Cooking Time (minutes):</label>
                <input type="number" id="addCookTime" min="1" required>
                <div id="cookTimeError" class="error"></div>

                <label for="addServingSize">Serving Size:</label>
                <input type="number" id="addServingSize" min="1" required>
                <div id="servingSizeError" class="error"></div>

                <label for="addCalories">Calories per Serving:</label>
                <input type="number" id="addCalories" min="1" required>
                <div id="caloriesError" class="error"></div>

                <label for="addImage">Image URL:</label>
                <input type="text" id="addImage" required>
                <div id="imageError" class="error"></div>

                <label for="addIsHealthy">Is Healthy:</label>
                <select id="addIsHealthy" required>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
                <div id="isHealthyError" class="error"></div>

                <label for="addIngredients">Ingredients:</label>
                <div id="ingredientsList">
                    <div class="ingredient-entry">
                        <input type="text" class="ingredient-name" placeholder="Name" required>
                        <input type="number" class="ingredient-quantity" placeholder="Quantity" required>
                        <input type="text" class="ingredient-unit" placeholder="Unit" required>
                        <select class="ingredient-optional">
                            <option value="0">Required</option>
                            <option value="1">Optional</option>
                        </select>
                        <button type="button" class="remove-ingredient">Remove</button>
                    </div>
                </div>
                <button type="button" id="addIngredientBtn">Add Another Ingredient</button>
                <div id="ingredientsError" class="error"></div>

                <label>Nutrition Facts:</label>
                <div class="nutrition-facts">
                    <input type="number" id="addProtein" placeholder="Protein (g)" required>
                    <input type="number" id="addCarbs" placeholder="Carbohydrates (g)" required>
                    <input type="number" id="addFat" placeholder="Fat (g)" required>
                    <input type="number" id="addFiber" placeholder="Fiber (g)" required>
                    <input type="number" id="addSugar" placeholder="Sugar (g)" required>
                    <input type="number" id="addSodium" placeholder="Sodium (mg)" required>
                </div>
                <div id="nutritionError" class="error"></div>

                <button type="submit">Add Recipe</button>
            </form>
        </div>
    </div>

    <!-- View Recipe Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Recipe Details</h2>
            <div id="recipeDetails"></div>
        </div>
    </div>

    <!-- Edit Recipe Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Recipe</h2>
            <form id="editForm" class="form">
                <input type="hidden" id="editRecipeId">
                
                <label for="editTitle">Recipe Title:</label>
                <input type="text" id="editTitle" required>
                <div id="editTitleError" class="error"></div>


                <label for="editType">Recipe Type:</label>
                <select id="editType" required>
                    <option value="">Select Recipe Type</option>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner">Dinner</option>
                    <option value="snack">Snack</option>
                    <option value="dessert">Dessert</option>
                </select>
                <div id="editTypeError" class="error"></div>

                <label for="editOrigin">Origin:</label>
                <input type="text" id="editOrigin" required>
                <div id="editOriginError" class="error"></div>

                <label for="editInstructions">Instructions:</label>
                <textarea id="editInstructions" required></textarea>
                <div id="editInstructionsError" class="error"></div>

                <label for="editDescription">Description:</label>
                <textarea id="editDescription" required></textarea>
                <div id="editDescriptionError" class="error"></div>

                <label for="editPrepTime">Preparation Time (minutes):</label>
                <input type="number" id="editPrepTime" min="1" required>
                <div id="editPrepTimeError" class="error"></div>

                <label for="editCookTime">Cooking Time (minutes):</label>
                <input type="number" id="editCookTime" min="1" required>
                <div id="editCookTimeError" class="error"></div>

                <label for="editServingSize">Serving Size:</label>
                <input type="number" id="editServingSize" min="1" required>
                <div id="editServingSizeError" class="error"></div>

                <label for="editCalories">Calories per Serving:</label>
                <input type="number" id="editCalories" min="1" required>
                <div id="editCaloriesError" class="error"></div>

                <label for="editImage">Image URL:</label>
                <input type="text" id="editImage" required>
                <div id="editImageError" class="error"></div>

                <label for="editIsHealthy">Is Healthy:</label>
                <select id="editIsHealthy" required>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
                <div id="editIsHealthyError" class="error"></div>

                <label for="editIngredients">Ingredients:</label>
                <div id="editIngredientsList">
                </div>
                <button type="button" id="editAddIngredientBtn">Add Another Ingredient</button>
                <div id="editIngredientsError" class="error"></div>

                <label>Nutrition Facts:</label>
                <div class="nutrition-facts">
                    <input type="number" id="editProtein" placeholder="Protein (g)" required>
                    <input type="number" id="editCarbs" placeholder="Carbohydrates (g)" required>
                    <input type="number" id="editFat" placeholder="Fat (g)" required>
                    <input type="number" id="editFiber" placeholder="Fiber (g)" required>
                    <input type="number" id="editSugar" placeholder="Sugar (g)" required>
                    <input type="number" id="editSodium" placeholder="Sodium (mg)" required>
                </div>
                <div id="editNutritionError" class="error"></div>

                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Recipe Sharing Platform. All rights reserved by Austine Omo Naija.</p>
    </footer>

    <script>
// Set the user's name and role based on the session data
document.getElementById('user-name').textContent = '<?php echo $_SESSION['fname'] . ' ' . $_SESSION['lname']; ?>';

// Store the recipes data in a JavaScript variable
const recipes = <?php echo json_encode($recipes); ?>;

// Function to initialize modals
function initModals() {
    const addButton = document.getElementById('add-recipe-btn');
    if (addButton) {
        addButton.addEventListener('click', () => {
            const addModal = document.getElementById('addModal');
            if (addModal) {
                addModal.style.display = 'block';
            }
        });
    }

    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
}

// Function to add ingredient fields
function addIngredientField(container) {
    const newIngredient = document.createElement('div');
    newIngredient.className = 'ingredient-entry';
    newIngredient.innerHTML = `
        <input type="text" class="ingredient-name" placeholder="Name" required>
        <input type="number" class="ingredient-quantity" placeholder="Quantity" required>
        <input type="text" class="ingredient-unit" placeholder="Unit" required>
        <select class="ingredient-optional">
            <option value="0">Required</option>
            <option value="1">Optional</option>
        </select>
        <button type="button" class="remove-ingredient">Remove</button>
    `;
    container.appendChild(newIngredient);

    newIngredient.querySelector('.remove-ingredient').addEventListener('click', function() {
        container.removeChild(newIngredient);
    });
}

// Function to gather ingredients data
function getIngredientsData(container) {
    const ingredients = [];
    container.querySelectorAll('.ingredient-entry').forEach(entry => {
        ingredients.push({
            name: entry.querySelector('.ingredient-name').value,
            quantity: parseFloat(entry.querySelector('.ingredient-quantity').value),
            unit: entry.querySelector('.ingredient-unit').value,
            optional: parseInt(entry.querySelector('.ingredient-optional').value),
            origin: '',
            nutritional_value: '',
            allergen_info: '',
            shelf_life: ''
        });
    });
    return ingredients;
}

// Function to render recipe table
function renderRecipeTable() {
    const tableBody = document.querySelector("#recipeTable tbody");
    if (!tableBody) return;
    
    tableBody.innerHTML = "";

    if (Array.isArray(recipes) && recipes.length > 0) {
        recipes.forEach(recipe => {
            const row = `
                <tr data-id="${recipe.food_id}">
                    <td><img src="${recipe.image_url}" alt="${recipe.name}" class="recipe-thumb"></td>
                    <td>${recipe.food_id}</td>
                    <td>${recipe.name}</td>
                    <td>${recipe.fname} ${recipe.lname}</td>
                    <td>${recipe.is_approved ? 'Approved' : 'Pending'}</td>
                    <td>${recipe.created_at}</td>
                    <td>
                        <button class="action-btn view-btn" onclick="viewRecipe(${recipe.food_id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="action-btn edit-btn" onclick="editRecipe(${recipe.food_id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-btn delete-btn" onclick="deleteRecipe(${recipe.food_id})">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
            tableBody.innerHTML += row;
        });
    } else {
        tableBody.innerHTML = '<tr><td colspan="7">No recipes found.</td></tr>';
    }
}

// Recipe View Function
function viewRecipe(recipeId) {
    const recipe = recipes.find(r => r.food_id === recipeId);
    if (!recipe) return;

    const modal = document.getElementById("viewModal");
    const recipeDetails = document.getElementById("recipeDetails");

    // Parse ingredients string
    const ingredientsList = recipe.ingredients ? recipe.ingredients.split('|').map(ing => {
        const [name, quantity, unit, optional] = ing.split(':');
        return `<li>${quantity} ${unit} ${name} ${optional === '1' ? '(Optional)' : ''}</li>`;
    }).join('') : '';

    recipeDetails.innerHTML = `
        <div class="recipe-header">
            <img src="${recipe.image_url}" alt="${recipe.name}" class="recipe-avatar">
            <h3>${recipe.name}</h3>
        </div>
        <div class="recipe-info">
            <p><strong>Type:</strong> ${recipe.type}</p>
            <p><strong>Origin:</strong> ${recipe.origin}</p>
            <p><strong>Author:</strong> ${recipe.fname} ${recipe.lname}</p>
            <p><strong>Preparation Time:</strong> ${recipe.preparation_time} minutes</p>
            <p><strong>Cooking Time:</strong> ${recipe.cooking_time} minutes</p>
            <p><strong>Serving Size:</strong> ${recipe.serving_size}</p>
            <p><strong>Calories per Serving:</strong> ${recipe.calories_per_serving}</p>
        </div>
        <div class="recipe-description">
            <h4>Description:</h4>
            <p>${recipe.description}</p>
        </div>
        <div class="recipe-ingredients">
            <h4>Ingredients:</h4>
            <ul>${ingredientsList}</ul>
        </div>
        <div class="recipe-nutrition">
            <h4>Nutrition Facts (per serving):</h4>
            <p>Protein: ${recipe.protein}g</p>
            <p>Carbohydrates: ${recipe.carbohydrates}g</p>
            <p>Fat: ${recipe.fat}g</p>
            <p>Fiber: ${recipe.fiber}g</p>
            <p>Sugar: ${recipe.sugar}g</p>
            <p>Sodium: ${recipe.sodium}mg</p>
        </div>
        <div class="recipe-instructions">
            <h4>Instructions:</h4>
            <p>${recipe.instructions}</p>
        </div>
    `;

    modal.style.display = "block";
}

// Add Recipe Function
function addRecipe(event) {
    event.preventDefault();

    // Gather ingredients data
    const ingredientsData = getIngredientsData(document.getElementById('ingredientsList'));
    
    // Gather nutrition data
    const nutritionData = {
        protein: parseFloat(document.getElementById('addProtein').value),
        carbohydrates: parseFloat(document.getElementById('addCarbs').value),
        fat: parseFloat(document.getElementById('addFat').value),
        fiber: parseFloat(document.getElementById('addFiber').value),
        sugar: parseFloat(document.getElementById('addSugar').value),
        sodium: parseFloat(document.getElementById('addSodium').value)
    };

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('title', document.getElementById('addTitle').value);
    formData.append('type', document.getElementById('addType').value);
    formData.append('origin', document.getElementById('addOrigin').value);
    formData.append('instructions', document.getElementById('addInstructions').value);
    formData.append('description', document.getElementById('addDescription').value);
    formData.append('prepTime', document.getElementById('addPrepTime').value);
    formData.append('cookTime', document.getElementById('addCookTime').value);
    formData.append('servingSize', document.getElementById('addServingSize').value);
    formData.append('calories', document.getElementById('addCalories').value);
    formData.append('image', document.getElementById('addImage').value);
    formData.append('isHealthy', document.getElementById('addIsHealthy').value);
    formData.append('ingredients', JSON.stringify(ingredientsData));
    formData.append('nutrition', JSON.stringify(nutritionData));

    fetch('recipes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Recipe added successfully');
            location.reload();
        } else {
            alert('Error adding recipe: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding recipe');
    });
}

// Update Recipe Function
function updateRecipe(event) {
    event.preventDefault();

    const ingredientsData = getIngredientsData(document.getElementById('editIngredientsList'));
    
    const nutritionData = {
        protein: parseFloat(document.getElementById('editProtein').value),
        carbohydrates: parseFloat(document.getElementById('editCarbs').value),
        fat: parseFloat(document.getElementById('editFat').value),
        fiber: parseFloat(document.getElementById('editFiber').value),
        sugar: parseFloat(document.getElementById('editSugar').value),
        sodium: parseFloat(document.getElementById('editSodium').value)
    };

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('recipeId', document.getElementById('editRecipeId').value);
    formData.append('title', document.getElementById('editTitle').value);
    formData.append('type', document.getElementById('editType').value);
    formData.append('origin', document.getElementById('editOrigin').value);
    formData.append('instructions', document.getElementById('editInstructions').value);
    formData.append('description', document.getElementById('editDescription').value);
    formData.append('prepTime', document.getElementById('editPrepTime').value);
    formData.append('cookTime', document.getElementById('editCookTime').value);
    formData.append('servingSize', document.getElementById('editServingSize').value);
    formData.append('calories', document.getElementById('editCalories').value);
    formData.append('image', document.getElementById('editImage').value);
    formData.append('isHealthy', document.getElementById('editIsHealthy').value);
    formData.append('ingredients', JSON.stringify(ingredientsData));
    formData.append('nutrition', JSON.stringify(nutritionData));

    fetch('recipes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Recipe updated successfully');
            location.reload();
        } else {
            alert('Error updating recipe: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating recipe');
    });
}

// Delete Recipe Function
function deleteRecipe(recipeId) {
    if (confirm('Are you sure you want to delete this recipe?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('recipeId', recipeId);

        fetch('recipes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Recipe deleted successfully');
                location.reload();
            } else {
                alert('Error deleting recipe: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting recipe');
        });
    }
}

// Edit Recipe Function
function editRecipe(recipeId) {
    fetch(`recipes.php?action=get_recipe&id=${recipeId}`)
        .then(response => response.json())
        .then(recipe => {
            document.getElementById('editRecipeId').value = recipe.food_id;
            document.getElementById('editTitle').value = recipe.name;
            document.getElementById('editType').value = recipe.type;
            document.getElementById('editOrigin').value = recipe.origin;
            document.getElementById('editInstructions').value = recipe.instructions;
            document.getElementById('editDescription').value = recipe.description;
            document.getElementById('editPrepTime').value = recipe.preparation_time;
            document.getElementById('editCookTime').value = recipe.cooking_time;
            document.getElementById('editServingSize').value = recipe.serving_size;
            document.getElementById('editCalories').value = recipe.calories_per_serving;
            document.getElementById('editImage').value = recipe.image_url;
            document.getElementById('editIsHealthy').value = recipe.is_healthy;
            
            // Set nutrition values
            document.getElementById('editProtein').value = recipe.protein;
            document.getElementById('editCarbs').value = recipe.carbohydrates;
            document.getElementById('editFat').value = recipe.fat;
            document.getElementById('editFiber').value = recipe.fiber;
            document.getElementById('editSugar').value = recipe.sugar;
            document.getElementById('editSodium').value = recipe.sodium;

            // Clear and populate ingredients
            const ingredientsList = document.getElementById('editIngredientsList');
            ingredientsList.innerHTML = '';
            
            if (recipe.ingredients) {
                recipe.ingredients.split('|').forEach(ing => {
                    const [name, quantity, unit, optional] = ing.split(':');
                    const ingredientDiv = document.createElement('div');
                    ingredientDiv.className = 'ingredient-entry';
                    ingredientDiv.innerHTML = `
                        <input type="text" class="ingredient-name" value="${name}" required>
                        <input type="number" class="ingredient-quantity" value="${quantity}" required>
                        <input type="text" class="ingredient-unit" value="${unit}" required>
                        <select class="ingredient-optional">
                            <option value="0" ${optional === '0' ? 'selected' : ''}>Required</option>
                            <option value="1" ${optional === '1' ? 'selected' : ''}>Optional</option>
                        </select>
                        <button type="button" class="remove-ingredient">Remove</button>
                    `;
                    ingredientsList.appendChild(ingredientDiv);
                });
            }
            
            document.getElementById('editModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading recipe details');
        });
}

// Initialize everything when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    initModals();
    renderRecipeTable();
    
    // Add form submit handlers
    const addForm = document.getElementById('addForm');
    const editForm = document.getElementById('editForm');
    
    if (addForm) {
        addForm.addEventListener('submit', addRecipe);
    }
    
    if (editForm) {
        editForm.addEventListener('submit', updateRecipe);
    }

    // Add ingredient button handlers
    const addIngredientBtn = document.getElementById('addIngredientBtn');
    if (addIngredientBtn) {
        addIngredientBtn.addEventListener('click', () => {
            addIngredientField(document.getElementById('ingredientsList'));
        });
    }

    const editAddIngredientBtn = document.getElementById('editAddIngredientBtn');
    if (editAddIngredientBtn) {
        editAddIngredientBtn.addEventListener('click', () => {
            addIngredientField(document.getElementById('editIngredientsList'));
        });
    }

    // Add remove ingredient handlers
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-ingredient')) {
            const container = e.target.closest('.ingredient-entry').parentElement;
            if (container.children.length > 1) {
                container.removeChild(e.target.closest('.ingredient-entry'));
            } else {
                alert('At least one ingredient is required');
            }
        }
    });

    // Add form validation
    function validateForm(formType) {
        let isValid = true;
        const prefix = formType === 'add' ? '' : 'edit';

        // Validate title
        const title = document.getElementById(`${prefix}Title`);
        if (title.value.trim() === '') {
            document.getElementById(`${prefix}TitleError`).textContent = 'Title is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}TitleError`).textContent = '';
        }

        // Validate type
        const type = document.getElementById(`${prefix}Type`);
        if (type.value === '') {
            document.getElementById(`${prefix}TypeError`).textContent = 'Please select a recipe type';
            isValid = false;
        } else {
            document.getElementById(`${prefix}TypeError`).textContent = '';
        }

        // Validate preparation time
        const prepTime = document.getElementById(`${prefix}PrepTime`);
        if (prepTime.value <= 0) {
            document.getElementById(`${prefix}PrepTimeError`).textContent = 'Valid preparation time is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}PrepTimeError`).textContent = '';
        }

        // Validate cooking time
        const cookTime = document.getElementById(`${prefix}CookTime`);
        if (cookTime.value <= 0) {
            document.getElementById(`${prefix}CookTimeError`).textContent = 'Valid cooking time is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}CookTimeError`).textContent = '';
        }

        // Validate serving size
        const servingSize = document.getElementById(`${prefix}ServingSize`);
        if (servingSize.value <= 0) {
            document.getElementById(`${prefix}ServingSizeError`).textContent = 'Valid serving size is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}ServingSizeError`).textContent = '';
        }

        // Validate calories
        const calories = document.getElementById(`${prefix}Calories`);
        if (calories.value <= 0) {
            document.getElementById(`${prefix}CaloriesError`).textContent = 'Valid calories count is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}CaloriesError`).textContent = '';
        }

        // Validate nutrition values
        const nutritionFields = ['Protein', 'Carbs', 'Fat', 'Fiber', 'Sugar', 'Sodium'];
        nutritionFields.forEach(field => {
            const element = document.getElementById(`${prefix}${field}`);
            if (!element.value || element.value < 0) {
                document.getElementById(`${prefix}NutritionError`).textContent = 'All nutrition values must be valid numbers';
                isValid = false;
            } else {
                document.getElementById(`${prefix}NutritionError`).textContent = '';
            }
        });

        // Validate ingredients
        const ingredientsList = document.getElementById(`${prefix}IngredientsList`);
        const ingredients = ingredientsList.querySelectorAll('.ingredient-entry');
        let hasValidIngredients = false;

        ingredients.forEach(ingredient => {
            const name = ingredient.querySelector('.ingredient-name').value;
            const quantity = ingredient.querySelector('.ingredient-quantity').value;
            const unit = ingredient.querySelector('.ingredient-unit').value;
            
            if (name && quantity > 0 && unit) {
                hasValidIngredients = true;
            }
        });

        if (!hasValidIngredients) {
            document.getElementById(`${prefix}IngredientsError`).textContent = 'At least one valid ingredient is required';
            isValid = false;
        } else {
            document.getElementById(`${prefix}IngredientsError`).textContent = '';
        }

        return isValid;
    }

});
</script>
</body>
</html>