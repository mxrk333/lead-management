<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the sidebar is loaded
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/header.php';

// Get project ID from URL
$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    // Redirect to project listing with an error if no ID is provided
    $_SESSION['error_message'] = 'No project ID provided.';
    header('Location: projectlisting.php');
    exit();
}

$conn = getDbConnection();
if (!$conn) {
    // Handle database connection failure gracefully
    $_SESSION['error_message'] = 'Database connection failed. Please try again later.';
    header('Location: projectlisting.php');
    exit();
}

// Fetch project data with location details
$stmt = $conn->prepare("SELECT p.*, c.name as city_name, pr.name as province_name 
                        FROM projects p 
                        LEFT JOIN cities c ON p.city_id = c.id 
                        LEFT JOIN provinces pr ON p.province_id = pr.id 
                        WHERE p.id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    // Redirect to project listing with an error if project is not found
    $_SESSION['error_message'] = 'Project not found.';
    header('Location: projectlisting.php');
    exit();
}

// Fetch all provinces for dropdown
$provincesStmt = $conn->prepare("SELECT id, name FROM provinces ORDER BY name");
$provincesStmt->execute();
$provinces = $provincesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$provincesStmt->close();

// Fetch cities for the currently selected province (for initial load)
$cities = [];
if ($project['province_id']) {
    $citiesStmt = $conn->prepare("SELECT id, name FROM cities WHERE province_id = ? ORDER BY name");
    $citiesStmt->bind_param("i", $project['province_id']);
    $citiesStmt->execute();
    $cities = $citiesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $citiesStmt->close();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - <?php echo htmlspecialchars($project['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom Tailwind Colors (if not already in tailwind.config.js) */
        :root {
            --color-blue-primary: #3B82F6; /* A vibrant blue */
            --color-blue-dark: #2563EB;   /* A darker blue for hover */
            --color-gray-extra-light: #F9FAFB; /* Lighter gray for backgrounds */
        }
        .text-blue-primary { color: var(--color-blue-primary); }
        .hover\:text-blue-dark:hover { color: var(--color-blue-dark); }
        .focus\:ring-blue-primary:focus { --tw-ring-color: var(--color-blue-primary); }
        .focus\:border-blue-primary:focus { border-color: var(--color-blue-primary); }
        .bg-blue-primary { background-color: var(--color-blue-primary); }
        .hover\:bg-blue-dark:hover { background-color: var(--color-blue-dark); }
        .border-blue-primary { border-color: var(--color-blue-primary); }

        .form-input {
            @apply w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200;
        }

        /* Styles for the loading overlay and notification toasts */
        .notification {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out forwards;
        }
        .notification.success {
            background-color: #10B981; /* green-500 */
        }
        .notification.error {
            background-color: #EF4444; /* red-500 */
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex min-h-screen">
        <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 p-8">
            <div class="max-w-6xl mx-auto bg-white rounded-xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-800">Edit Project</h1>
                        <p class="text-gray-600 mt-1">Update details for project: <strong class="text-blue-primary"><?php echo htmlspecialchars($project['name']); ?></strong></p>
                    </div>
                    <a href="projectlisting.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Projects
                    </a>
                </div>

                <form id="editProjectForm" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <fieldset class="border border-gray-200 rounded-lg p-6 shadow-sm">
                            <legend class="text-lg font-semibold text-gray-700 px-2">Project Details</legend>
                            
                            <div class="mb-5">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Project Name <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($project['name']); ?>" 
                                       class="form-input" required>
                            </div>

                            <div class="mb-5">
                                <label for="developer" class="block text-sm font-medium text-gray-700 mb-2">Developer <span class="text-red-500">*</span></label>
                                <input type="text" id="developer" name="developer" value="<?php echo htmlspecialchars($project['developer']); ?>" 
                                       class="form-input" required>
                            </div>
                            
                            <div class="mb-5">
                                <label for="house_model" class="block text-sm font-medium text-gray-700 mb-2">House Model</label>
                                <input type="text" id="house_model" name="house_model" value="<?php echo htmlspecialchars($project['house_model']); ?>" 
                                       placeholder="e.g., Lincoln, Kennedy" class="form-input">
                            </div>

                            <div class="mb-5">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">House Type</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="e.g., Single Detached, Twinhome" 
                                          class="form-input resize-y"><?php echo htmlspecialchars($project['description']); ?></textarea>
                            </div>
                        </fieldset>

                        <div>
                            <fieldset class="border border-gray-200 rounded-lg p-6 shadow-sm mb-8">
                                <legend class="text-lg font-semibold text-gray-700 px-2">Location Information</legend>
                                <div class="mb-5">
                                    <label for="province_id" class="block text-sm font-medium text-gray-700 mb-2">Province <span class="text-red-500">*</span></label>
                                    <select id="province_id" name="province_id" required class="form-input">
                                        <option value="">Select Province</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo $province['id']; ?>" 
                                                    <?php echo ($project['province_id'] == $province['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($province['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-5">
                                    <label for="city_id" class="block text-sm font-medium text-gray-700 mb-2">City <span class="text-red-500">*</span></label>
                                    <select id="city_id" name="city_id" required class="form-input">
                                        <option value="">Select City</option>
                                        <?php foreach ($cities as $city): // Only cities for the currently selected province ?>
                                            <option value="<?php echo $city['id']; ?>" 
                                                    <?php echo ($project['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($city['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="exact_location" class="block text-sm font-medium text-gray-700 mb-2">Exact Location</label>
                                    <input type="text" id="exact_location" name="exact_location" 
                                           value="<?php echo htmlspecialchars($project['exact_location']); ?>" 
                                           placeholder="Street address, building name, etc." 
                                           class="form-input">
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-6 shadow-sm mb-8">
                                <legend class="text-lg font-semibold text-gray-700 px-2">Pricing & Priority</legend>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="mb-5">
                                        <label for="price_min" class="block text-sm font-medium text-gray-700 mb-2">Minimum Price <span class="text-red-500">*</span></label>
                                        <input type="number" id="price_min" name="price_min" step="0.01" 
                                               value="<?php echo htmlspecialchars($project['price_min']); ?>"
                                               class="form-input" required>
                                    </div>
                                    <div class="mb-5">
                                        <label for="price_max" class="block text-sm font-medium text-gray-700 mb-2">Maximum Price <span class="text-red-500">*</span></label>
                                        <input type="number" id="price_max" name="price_max" step="0.01" 
                                               value="<?php echo htmlspecialchars($project['price_max']); ?>"
                                               class="form-input" required>
                                    </div>
                                    <div class="mb-5">
                                        <label for="commission" class="block text-sm font-medium text-gray-700 mb-2">Commission (%) <span class="text-red-500">*</span></label>
                                        <input type="number" id="commission" name="commission" step="0.01" 
                                               value="<?php echo htmlspecialchars($project['commission']); ?>"
                                               class="form-input" required>
                                    </div>
                                    <div class="mb-5">
                                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                        <select name="priority" id="priority" required>
  <option value="low">Low</option>
  <option value="medium">Medium</option>
  <option value="high">High</option>
</select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Project Status <span class="text-red-500">*</span></label>
                                        <select id="status" name="status" required class="form-input">
                                            <option value="rfo" <?php echo ($project['status'] == 'rfo') ? 'selected' : ''; ?>>RFO (Ready For Occupancy)</option>
                                            <option value="preselling" <?php echo ($project['status'] == 'preselling') ? 'selected' : ''; ?>>Preselling</option>
                                            <option value="ogc" <?php echo ($project['status'] == 'ogc') ? 'selected' : ''; ?>>OGC (On Going Construction)</option>
                                            <option value="rfo_preselling" <?php echo ($project['status'] == 'rfo_preselling') ? 'selected' : ''; ?>>RFO/Preselling</option>
                                            <option value="preselling_ogc" <?php echo ($project['status'] == 'preselling_ogc') ? 'selected' : ''; ?>>Preselling/OGC</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="border border-gray-200 rounded-lg p-6 shadow-sm">
                                <legend class="text-lg font-semibold text-gray-700 px-2">Additional Links</legend>
                                <div class="mb-5">
                                    <label for="drive_link" class="block text-sm font-medium text-gray-700 mb-2">Google Drive Link</label>
                                    <input type="url" id="drive_link" name="drive_link" 
                                           value="<?php echo htmlspecialchars($project['drive_link']); ?>"
                                           placeholder="https://drive.google.com/..."
                                           class="form-input">
                                </div>
                                <div>
                                    <label for="messenger_link" class="block text-sm font-medium text-gray-700 mb-2">Messenger Link</label>
                                    <input type="url" id="messenger_link" name="messenger_link" 
                                           value="<?php echo htmlspecialchars($project['messenger_link']); ?>"
                                           placeholder="https://m.me/..."
                                           class="form-input">
                                </div>
                            </fieldset>
                        </div>
                    </div>

                    <fieldset class="border border-gray-200 rounded-lg p-6 shadow-sm mt-8">
                        <legend class="text-lg font-semibold text-gray-700 px-2">Project Media</legend>
                        
                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-700 mb-4">Current Images</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <?php $imageField = "image$i"; ?>
                                    <div class="relative group" id="imageContainer<?php echo $i; ?>">
                                        <?php if (!empty($project[$imageField])): ?>
                                            <img src="uploads/projects/<?php echo htmlspecialchars($project[$imageField]); ?>" 
                                                 alt="Project Image <?php echo $i; ?>" 
                                                 class="w-full h-32 object-cover rounded-lg border border-gray-300 shadow-sm transition-transform duration-200 group-hover:scale-105">
                                            <button type="button" onclick="removeImage(<?php echo $i; ?>)" 
                                                    class="absolute -top-2 -right-2 w-7 h-7 bg-red-600 text-white rounded-full text-sm hover:bg-red-700 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75" 
                                                    title="Remove Image">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <input type="hidden" name="existing_image<?php echo $i; ?>" value="<?php echo htmlspecialchars($project[$imageField]); ?>">
                                        <?php else: ?>
                                            <div class="w-full h-32 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-400 text-center text-sm p-2">
                                                <i class="fas fa-image text-3xl mb-2"></i>
                                                <span>No image uploaded</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-700 mb-4">Upload New Images (Optional)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <div>
                                        <label for="image<?php echo $i; ?>" class="block text-sm text-gray-600 mb-2">Image <?php echo $i; ?></label>
                                        <input type="file" id="image<?php echo $i; ?>" name="image<?php echo $i; ?>" accept="image/*" 
                                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 
                                                      focus:outline-none file:mr-4 file:py-2 file:px-4 
                                                      file:rounded-lg file:border-0 file:text-sm file:font-semibold 
                                                      file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all duration-200">
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <p class="text-sm text-gray-500 mt-3">Accepted formats: JPG, PNG, GIF. Maximum size: 5MB per image. New uploads will replace existing ones.</p>
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-primary text-white rounded-lg font-semibold hover:bg-blue-dark transition-all duration-300 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-primary focus:ring-opacity-75">
                            <i class="fas fa-save mr-2"></i>
                            Update Project
                        </button>
                        <a href="projectlisting.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 transition-colors duration-300 shadow-md focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-75">
                            <i class="fas fa-ban mr-2"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 flex items-center gap-4 shadow-xl">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="text-lg font-medium text-gray-700">Updating project...</span>
        </div>
    </div>

    <script>
        // Function to show notification toasts
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                ${type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>'}
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Province change handler to dynamically load cities
        document.getElementById('province_id').addEventListener('change', function() {
            const provinceId = this.value;
            const citySelect = document.getElementById('city_id');
            
            // Clear current city options and add a default "Select City" option
            citySelect.innerHTML = '<option value="">Select City</option>';
            
            if (provinceId) {
                // Fetch cities for the selected province
                fetch(`api/get_cities.php?province_id=${provinceId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            data.cities.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        } else {
                            console.error('API Error:', data.error);
                            showNotification(data.error || 'Failed to load cities.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        showNotification('Could not load cities. Please try again.', 'error');
                    });
            }
        });

        // Function to handle image removal
        function removeImage(imageNumber) {
            if (confirm('Are you sure you want to remove this image? This action cannot be undone.')) {
                const imageContainer = document.getElementById(`imageContainer${imageNumber}`);
                
                // Add a hidden input to signal deletion to the backend
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = `delete_image${imageNumber}`;
                deleteInput.value = '1';
                document.getElementById('editProjectForm').appendChild(deleteInput);
                
                // Replace the image preview with a placeholder indicating removal
                imageContainer.innerHTML = `
                    <div class="w-full h-32 bg-red-50 rounded-lg border-2 border-dashed border-red-300 flex flex-col items-center justify-center text-red-500 text-center text-sm p-2">
                        <i class="fas fa-trash-alt text-3xl mb-2"></i>
                        <span>Image will be removed</span>
                    </div>
                `;
            }
        }

        // Form submission handler
        document.getElementById('editProjectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Show loading overlay
            loadingOverlay.classList.remove('hidden');
            
            fetch('api/update_project.php', {
                method: 'POST',
                body: formData
            })
            .then(async (response) => {
                // Parse response as text first to handle non-JSON responses
                const text = await response.text();
                console.log("Raw API response:", text); // Log raw response for debugging

                if (!response.ok) {
                    let errorMessage = 'Server error occurred.';
                    try {
                        const errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorMessage;
                    } catch (e) {
                        // If response is not JSON, use generic message
                    }
                    throw new Error(errorMessage);
                }
                
                try {
                    return JSON.parse(text); // Try to parse as JSON
                } catch (e) {
                    throw new Error("Invalid JSON response from server. Check server logs.");
                }
            })
            .then(data => {
                loadingOverlay.classList.add('hidden'); // Hide loading overlay
                
                if (data.success) {
                    showNotification(data.message || 'Project updated successfully!', 'success');
                    
                    // Redirect to project listing after a short delay for user to see notification
                    setTimeout(() => {
                        window.location.href = 'projectlisting.php';
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to update project. Please try again.', 'error');
                }
            })
            .catch(error => {
                loadingOverlay.classList.add('hidden'); // Hide loading overlay
                console.error('Fetch Error:', error);
                showNotification(error.message || 'An unexpected error occurred. Please try again.', 'error');
            });
        });

        document.getElementById('priority').value = '<?php echo $project['priority']; ?>';
    </script>
<script>
// -------- Layout responsiveness enhancements (match project listing) --------
(function () {
    const sidebar = document.getElementById('sidebar');
    const mainContainer = document.querySelector('.flex-1');
    const headerEl = document.querySelector('.main-header');

    if (!sidebar || !mainContainer) return;

    function adjustLayout() {
        const desktop = window.innerWidth >= 1024;
        if (desktop) {
            const sideWidth = sidebar.getBoundingClientRect().width;
            mainContainer.style.marginLeft = sideWidth + 'px';
            if (headerEl) {
                headerEl.style.left = sideWidth + 'px';
                headerEl.style.width = `calc(100% - ${sideWidth}px)`;
            }
        } else {
            mainContainer.style.marginLeft = '0';
            if (headerEl) {
                headerEl.style.left = '0';
                headerEl.style.width = '100%';
            }
        }
    }

    if ('ResizeObserver' in window) {
        const resizeObserver = new ResizeObserver(adjustLayout);
        resizeObserver.observe(sidebar);
    }
    window.addEventListener('resize', adjustLayout);
    adjustLayout();
})();
</script>
</body>
</html>