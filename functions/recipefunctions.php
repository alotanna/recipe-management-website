<?php
// Enum-like array of allowed recipe types
const RECIPE_TYPES = ['breakfast', 'lunch', 'dinner', 'snack', 'dessert'];

// Function to validate recipe type
function isValidRecipeType($type) {
    return in_array(strtolower($type), RECIPE_TYPES);
}
// Function to get all recipes for super admin (role = 1)
function getAllRecipes() {
    global $conn;
    $sql = "SELECT f.food_id, f.name, f.type, f.origin, f.instructions, f.description, 
            f.preparation_time, f.cooking_time, f.serving_size, f.calories_per_serving, 
            f.image_url, f.is_healthy, f.created_by,
            MAX(u.fname) as fname, MAX(u.lname) as lname,
            GROUP_CONCAT(DISTINCT CONCAT(i.name, ':', r.quantity, ' ', r.unit) SEPARATOR '|') as ingredients,
            MAX(nf.protein) as protein, MAX(nf.carbohydrates) as carbohydrates, 
            MAX(nf.fat) as fat, MAX(nf.fiber) as fiber, 
            MAX(nf.sugar) as sugar, MAX(nf.sodium) as sodium
            FROM foods f
            JOIN users u ON f.created_by = u.user_id
            LEFT JOIN recipes r ON f.food_id = r.food_id
            LEFT JOIN ingredients i ON r.ingredient_id = i.ingredient_id
            LEFT JOIN nutritionfacts nf ON f.food_id = nf.food_id
            GROUP BY f.food_id, f.name, f.type, f.origin, f.instructions, f.description, 
            f.preparation_time, f.cooking_time, f.serving_size, f.calories_per_serving, 
            f.image_url, f.is_healthy, f.created_by";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get personal recipes for user (role = 2)
function getPersonalRecipes($userId) {
    global $conn;
    $sql = "SELECT f.food_id, f.name, f.type, f.origin, f.instructions, f.description, 
            f.preparation_time, f.cooking_time, f.serving_size, f.calories_per_serving, 
            f.image_url, f.is_healthy, f.created_by,
            MAX(u.fname) as fname, MAX(u.lname) as lname,
            GROUP_CONCAT(DISTINCT CONCAT(i.name, ':', r.quantity, ' ', r.unit) SEPARATOR '|') as ingredients,
            MAX(nf.protein) as protein, MAX(nf.carbohydrates) as carbohydrates, 
            MAX(nf.fat) as fat, MAX(nf.fiber) as fiber, 
            MAX(nf.sugar) as sugar, MAX(nf.sodium) as sodium
            FROM foods f
            JOIN users u ON f.created_by = u.user_id
            LEFT JOIN recipes r ON f.food_id = r.food_id
            LEFT JOIN ingredients i ON r.ingredient_id = i.ingredient_id
            LEFT JOIN nutritionfacts nf ON f.food_id = nf.food_id
            WHERE f.created_by = ?
            GROUP BY f.food_id, f.name, f.type, f.origin, f.instructions, f.description, 
            f.preparation_time, f.cooking_time, f.serving_size, f.calories_per_serving, 
            f.image_url, f.is_healthy, f.created_by";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to create a new recipe with related data
function createRecipe($data) {
    global $conn;
    
    // Validate recipe type
    if (!isValidRecipeType($data['type'])) {
        return ['success' => false, 'error' => 'Invalid recipe type'];
    }
    
    try {
        $conn->begin_transaction();

        // Normalize type to lowercase for consistency
        $data['type'] = strtolower($data['type']);

        // Insert into foods table
        $sql = "INSERT INTO foods (name, type, origin, instructions, description, 
                preparation_time, cooking_time, serving_size, calories_per_serving, 
                image_url, is_healthy, created_by, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiiiisii", 
            $data['name'], 
            $data['type'],
            $data['origin'],
            $data['instructions'],
            $data['description'],
            $data['prep_time'],
            $data['cook_time'],
            $data['serving_size'],
            $data['calories'],
            $data['image_url'],
            $data['is_healthy'],
            $data['created_by']
        );
        
        $stmt->execute();
        $food_id = $conn->insert_id;

        // Insert nutrition facts
        $sql = "INSERT INTO nutritionfacts (food_id, protein, carbohydrates, fat, fiber, sugar, sodium)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idddddd",
            $food_id,
            $data['nutrition']['protein'],
            $data['nutrition']['carbohydrates'],
            $data['nutrition']['fat'],
            $data['nutrition']['fiber'],
            $data['nutrition']['sugar'],
            $data['nutrition']['sodium']
        );
        $stmt->execute();

        // Insert ingredients and recipe relationships
        foreach ($data['ingredients'] as $ingredient) {
            // Check if ingredient exists or create new
            $stmt = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
            $stmt->bind_param("s", $ingredient['name']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $ingredient_id = $result->fetch_assoc()['ingredient_id'];
            } else {
                // Create new ingredient
                $stmt = $conn->prepare("INSERT INTO ingredients (name, origin, nutritional_value, allergen_info, shelf_life) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss",
                    $ingredient['name'],
                    $ingredient['origin'],
                    $ingredient['nutritional_value'],
                    $ingredient['allergen_info'],
                    $ingredient['shelf_life']
                );
                $stmt->execute();
                $ingredient_id = $conn->insert_id;
            }

            // Create recipe relationship
            $stmt = $conn->prepare("INSERT INTO recipes (food_id, ingredient_id, quantity, unit, optional) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsi",
                $food_id,
                $ingredient_id,
                $ingredient['quantity'],
                $ingredient['unit'],
                $ingredient['optional']
            );
            $stmt->execute();
        }

        $conn->commit();
        return ['success' => true, 'food_id' => $food_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Function to update a recipe
function updateRecipe($recipeId, $data) {
    global $conn;
    
    // Validate recipe type
    if (!isValidRecipeType($data['type'])) {
        return ['success' => false, 'error' => 'Invalid recipe type'];
    }
    
    try {
        $conn->begin_transaction();

        // Normalize type to lowercase for consistency
        $data['type'] = strtolower($data['type']);

        // Update foods table
        $sql = "UPDATE foods SET 
                name = ?, type = ?, origin = ?, instructions = ?, 
                description = ?, preparation_time = ?, cooking_time = ?, 
                serving_size = ?, calories_per_serving = ?, image_url = ?, 
                is_healthy = ?
                WHERE food_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiiiisii",
            $data['name'],
            $data['type'],
            $data['origin'],
            $data['instructions'],
            $data['description'],
            $data['prep_time'],
            $data['cook_time'],
            $data['serving_size'],
            $data['calories'],
            $data['image_url'],
            $data['is_healthy'],
            $recipeId
        );
        $stmt->execute();

        // Delete existing recipe relationships
        $stmt = $conn->prepare("DELETE FROM recipes WHERE food_id = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // Insert updated ingredients and recipe relationships
        foreach ($data['ingredients'] as $ingredient) {
            // Check if ingredient exists or create new
            $stmt = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
            $stmt->bind_param("s", $ingredient['name']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $ingredient_id = $result->fetch_assoc()['ingredient_id'];
            } else {
                // Create new ingredient
                $stmt = $conn->prepare("INSERT INTO ingredients (name, origin, nutritional_value, allergen_info, shelf_life) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss",
                    $ingredient['name'],
                    $ingredient['origin'],
                    $ingredient['nutritional_value'],
                    $ingredient['allergen_info'],
                    $ingredient['shelf_life']
                );
                $stmt->execute();
                $ingredient_id = $conn->insert_id;
            }

            // Create recipe relationship
            $stmt = $conn->prepare("INSERT INTO recipes (food_id, ingredient_id, quantity, unit, optional) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsi",
                $recipeId,
                $ingredient_id,
                $ingredient['quantity'],
                $ingredient['unit'],
                $ingredient['optional']
            );
            $stmt->execute();
        }

        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


// Function to delete a recipe
function deleteRecipe($recipeId) {
    global $conn;
    
    try {
        $conn->begin_transaction();

        // Delete recipe (cascading will handle related records)
        $stmt = $conn->prepare("DELETE FROM foods WHERE food_id = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>