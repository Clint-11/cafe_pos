<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle add/edit/delete menu items with image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        $description = $_POST['description'];
        
        // Handle image upload
        $image_url = '';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/menu/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['item_image']['name']);
            $file_path = $upload_dir . $file_name;
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['item_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $file_path)) {
                    $image_url = 'assets/images/menu/' . $file_name;
                }
            }
        }
        
        $sql = "INSERT INTO menu_items (category_id, name, description, price, image_url) 
                VALUES (?, ?, ?, ?, ?)";
        executeQuery($sql, array($category_id, $name, $description, $price, $image_url));
        
        // Log the action
        $sql = "INSERT INTO security_logs (user_id, action_type, description) 
                VALUES (?, 'menu_add', ?)";
        executeQuery($sql, array($_SESSION['user_id'], "Added menu item: $name"));
        
        $success = "Menu item added successfully!";
    }
    
    if (isset($_POST['update_item'])) {
        $id = $_POST['item_id'];
        $name = $_POST['name'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        $description = $_POST['description'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Handle image upload for update
        $image_url = $_POST['existing_image'] ?? '';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/menu/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['item_image']['name']);
            $file_path = $upload_dir . $file_name;
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['item_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $file_path)) {
                    $image_url = 'assets/images/menu/' . $file_name;
                    
                    // Delete old image if exists
                    if (!empty($_POST['existing_image']) && file_exists('../' . $_POST['existing_image'])) {
                        unlink('../' . $_POST['existing_image']);
                    }
                }
            }
        }
        
        $sql = "UPDATE menu_items SET 
                category_id = ?, name = ?, description = ?, price = ?, 
                is_available = ?, image_url = ? 
                WHERE id = ?";
        executeQuery($sql, array($category_id, $name, $description, $price, $is_available, $image_url, $id));
        
        // Log the action
        $sql = "INSERT INTO security_logs (user_id, action_type, description) 
                VALUES (?, 'menu_edit', ?)";
        executeQuery($sql, array($_SESSION['user_id'], "Updated menu item: $name"));
        
        $success = "Menu item updated successfully!";
    }
    
    if (isset($_POST['delete_item'])) {
        $id = $_POST['item_id'];
        
        // Get item info before deleting
        $sql = "SELECT name, image_url FROM menu_items WHERE id = ?";
        $stmt = executeQuery($sql, array($id));
        $item = fetchSingle($stmt);
        
        // Delete image file if exists
        if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])) {
            unlink('../' . $item['image_url']);
        }
        
        $sql = "DELETE FROM menu_items WHERE id = ?";
        executeQuery($sql, array($id));
        
        // Log the action
        $sql = "INSERT INTO security_logs (user_id, action_type, description) 
                VALUES (?, 'menu_delete', ?)";
        executeQuery($sql, array($_SESSION['user_id'], "Deleted menu item: " . $item['name']));
        
        $success = "Menu item deleted successfully!";
    }
}

// Get all categories
$sql = "SELECT * FROM categories ORDER BY display_order";
$stmt = executeQuery($sql);
$categories = fetchAll($stmt);

// Get all menu items
$sql = "SELECT m.*, c.name as category_name 
        FROM menu_items m 
        JOIN categories c ON m.category_id = c.id 
        ORDER BY c.display_order, m.name";
$stmt = executeQuery($sql);
$menu_items = fetchAll($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .status-badge {
            width: 100px;
        }
        .menu-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 10px;
        }
        .upload-area {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .upload-area:hover {
            border-color: #6f4e37;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <span class="navbar-text">
                Admin: <?php echo $_SESSION['fullname']; ?>
            </span>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4">Menu Management</h2>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <!-- Add New Item Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Add New Menu Item</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id" required>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo $cat['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price (₱)</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Item Image</label>
                                <div class="upload-area" onclick="document.getElementById('newItemImage').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                    <p class="mb-0">Click to upload image</p>
                                    <small class="text-muted">JPEG, PNG, GIF, WebP (Max 2MB)</small>
                                </div>
                                <input type="file" class="form-control d-none" id="newItemImage" name="item_image" 
                                       accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewNewImage(event)">
                                <div id="newImagePreview" class="mt-2"></div>
                            </div>
                            <button type="submit" name="add_item" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i> Add Item
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Menu Items Table -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Menu Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($menu_items) > 0): ?>
                                    <?php foreach ($menu_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>" 
                                                 class="menu-image" 
                                                 onerror="this.src='https://via.placeholder.com/80x80/6f4e37/ffffff?text=No+Image'">
                                            <?php else: ?>
                                            <img src="https://via.placeholder.com/80x80/6f4e37/ffffff?text=No+Image" 
                                                 alt="No Image" class="menu-image">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $item['name']; ?></strong>
                                            <?php if ($item['description']): ?>
                                            <br><small class="text-muted"><?php echo $item['description']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['category_name']; ?></td>
                                        <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $item['is_available'] ? 'bg-success' : 'bg-danger'; ?> status-badge">
                                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $item['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?php echo $item['id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Menu Item</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="existing_image" value="<?php echo $item['image_url']; ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Name</label>
                                                                    <input type="text" class="form-control" name="name" 
                                                                           value="<?php echo $item['name']; ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Category</label>
                                                                    <select class="form-select" name="category_id" required>
                                                                        <?php foreach ($categories as $cat): ?>
                                                                        <option value="<?php echo $cat['id']; ?>" 
                                                                                <?php echo $cat['id'] == $item['category_id'] ? 'selected' : ''; ?>>
                                                                            <?php echo $cat['name']; ?>
                                                                        </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Price (₱)</label>
                                                                    <input type="number" class="form-control" name="price" 
                                                                           value="<?php echo $item['price']; ?>" step="0.01" min="0" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Current Image</label>
                                                                    <div>
                                                                        <?php if (!empty($item['image_url'])): ?>
                                                                        <img src="../<?php echo $item['image_url']; ?>" 
                                                                             alt="Current Image" class="img-fluid image-preview"
                                                                             onerror="this.src='https://via.placeholder.com/200x200/6f4e37/ffffff?text=No+Image'">
                                                                        <?php else: ?>
                                                                        <img src="https://via.placeholder.com/200x200/6f4e37/ffffff?text=No+Image" 
                                                                             alt="No Image" class="img-fluid image-preview">
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="3"><?php echo $item['description']; ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Update Image (Optional)</label>
                                                            <div class="upload-area" onclick="document.getElementById('editItemImage<?php echo $item['id']; ?>').click()">
                                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                                <p class="mb-0">Click to upload new image</p>
                                                                <small class="text-muted">Leave empty to keep current image</small>
                                                            </div>
                                                            <input type="file" class="form-control d-none" 
                                                                   id="editItemImage<?php echo $item['id']; ?>" 
                                                                   name="item_image" 
                                                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                                                   onchange="previewEditImage(event, 'editPreview<?php echo $item['id']; ?>')">
                                                            <div id="editPreview<?php echo $item['id']; ?>" class="mt-2"></div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_available" 
                                                                       value="1" id="available<?php echo $item['id']; ?>"
                                                                       <?php echo $item['is_available'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="available<?php echo $item['id']; ?>">
                                                                    Available for sale
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_item" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <p>Are you sure you want to delete <strong><?php echo $item['name']; ?></strong>?</p>
                                                        <?php if (!empty($item['image_url'])): ?>
                                                        <p class="text-warning">The associated image will also be deleted.</p>
                                                        <?php endif; ?>
                                                        <p class="text-danger">This action cannot be undone.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No menu items found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview for new item form
        function previewNewImage(event) {
            const input = event.target;
            const preview = document.getElementById('newImagePreview');
            
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-fluid image-preview';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Image preview for edit form
        function previewEditImage(event, previewId) {
            const input = event.target;
            const preview = document.getElementById(previewId);
            
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-fluid image-preview';
                    preview.appendChild(img);
                    
                    // Show message that this will replace existing image
                    const message = document.createElement('p');
                    message.className = 'text-warning small mt-2';
                    message.textContent = 'This will replace the current image';
                    preview.appendChild(message);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Make upload areas clickable
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.upload-area').forEach(area => {
                area.style.cursor = 'pointer';
            });
        });
    </script>
</body>
</html>