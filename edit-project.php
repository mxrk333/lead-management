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
    $_SESSION['error_message'] = 'No project ID provided.';
    header('Location: projectlisting.php');
    exit();
}

$conn = getDbConnection();
if (!$conn) {
    $_SESSION['error_message'] = 'Database connection failed. Please try again later.';
    header('Location: projectlisting.php');
    exit();
}

// Fetch project data with location details
$stmt = $conn->prepare("SELECT p.id, p.name, p.description, p.house_model, p.status, p.developer,
                        p.price_min, p.price_max, p.commission, p.priority, p.city_id, p.province_id,
                        p.exact_location, p.image1, p.image2, p.image3, p.image4, p.drive_link, p.messenger_link,
                        p.total_contract_price, p.reservation_fee, p.bank_amortization, p.required_salary,
                        p.downpayment_percentage, p.downpayment_amount, p.downpayment_term,
                        p.created_at, p.updated_at,
                        c.name as city_name, pr.name as province_name 
                        FROM projects p 
                        LEFT JOIN cities c ON p.city_id = c.id 
                        LEFT JOIN provinces pr ON p.province_id = pr.id 
                        WHERE p.id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    $_SESSION['error_message'] = 'Project not found.';
    header('Location: projectlisting.php');
    exit();
}

// Fetch all provinces for dropdown
$provincesStmt = $conn->prepare("SELECT id, name FROM provinces ORDER BY name");
$provincesStmt->execute();
$provinces = $provincesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$provincesStmt->close();

// Fetch all cities for the dropdown
// It's better to fetch all cities and filter them client-side for simplicity in this context
$citiesStmt = $conn->prepare("SELECT c.id, c.name, c.province_id, p.name as province_name FROM cities c LEFT JOIN provinces p ON c.province_id = p.id ORDER BY c.name");
$citiesStmt->execute();
$cities = $citiesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$citiesStmt->close();

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 80px;
            --primary-color: #1d4ed8;
            --primary-hover: #1e40af;
            --error-color: #ef4444;
            --success-color: #10b981;
        }
        body {
            min-height: 100vh;
            background-color: #f8fafc;
        }
        .container {
            width: 100%;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            padding: 1.5rem;
            max-width: calc(100vw - var(--sidebar-width));
        }
        .sidebar-collapsed .container {
            margin-left: var(--sidebar-collapsed-width);
            max-width: calc(100vw - var(--sidebar-collapsed-width));
            padding: 1.5rem;
        }
        @media (max-width: 1024px) {
            .container {
                width: 100%;
                margin-left: 0;
                padding: 1rem;
            }
        }
        .form-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-section h2 {
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background-color: #fff;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1);
            outline: none;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .btn-outline {
            background-color: transparent;
            border: 1px solid #cbd5e1;
            color: #64748b;
        }
        .btn-outline:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }
        .error-message, .success-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }
        .error-message {
            background-color: #fef2f2;
            border-left: 4px solid var(--error-color);
            color: #991b1b;
            display: block;
        }
        .success-message {
            background-color: #ecfdf5;
            border-left: 4px solid var(--success-color);
            color: #065f46;
            display: block;
        }
    </style>
</head>

<body class="bg-gray-50">
<div class="container">
<div class="max-w-5xl mx-auto">

<!-- Show error/success messages prominently -->
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Error:</strong> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
        <i class="fas fa-check-circle mr-2"></i>
        <strong>Success:</strong> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
<div>
<h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Edit Project</h1>
<p class="text-sm text-gray-500 mt-1">Update details for: <?php echo htmlspecialchars($project['name']); ?></p>
</div>
<a href="projectlisting.php" class="btn btn-outline flex items-center text-sm sm:text-base">
<i class="fas fa-arrow-left mr-2"></i>Back to Projects
</a>
</div>

<form id="editProjectForm" action="api/update_project.php" method="POST" enctype="multipart/form-data">
<input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">

<!-- Basic Information -->
<div class="form-section">
<h2>Basic Information</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
<div class="space-y-4">
<div>
<label for="name" class="block text-sm font-medium text-gray-700 mb-2">Project Name*</label>
<input type="text" id="name" name="name" required
class="form-input"
value="<?php echo htmlspecialchars($project['name']); ?>"
placeholder="Enter project name">
</div>
<div>
<label for="house_model" class="block text-sm font-medium text-gray-700 mb-2">House Model</label>
<input type="text" id="house_model" name="house_model" 
class="form-input"
value="<?php echo htmlspecialchars($project['house_model']); ?>"
placeholder="ex. Lincoln, Kennedy">
</div>
</div>
<div class="space-y-4">
<div>
<label for="developer" class="block text-sm font-medium text-gray-700 mb-2">Developer*</label>
<input type="text" id="developer" name="developer" required
class="form-input"
value="<?php echo htmlspecialchars($project['developer']); ?>"
placeholder="Enter developer name">
</div>
<div>
<label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status*</label>
<select id="status" name="status" required class="form-select">
<option value="">Select Status</option>
<option value="rfo" <?php echo ($project['status'] == 'rfo') ? 'selected' : ''; ?>>RFO (Ready For Occupancy)</option>
<option value="preselling" <?php echo ($project['status'] == 'preselling') ? 'selected' : ''; ?>>Preselling</option>
<option value="ogc" <?php echo ($project['status'] == 'ogc') ? 'selected' : ''; ?>>OGC (On Going Construction)</option>
<option value="rfo_preselling" <?php echo ($project['status'] == 'rfo_preselling') ? 'selected' : ''; ?>>RFO/Preselling</option>
<option value="preselling_ogc" <?php echo ($project['status'] == 'preselling_ogc') ? 'selected' : ''; ?>>Preselling/OGC</option>
</select>
</div>
</div>
</div>

<!-- Description -->
<div class="form-section">
<div class="space-y-2">
<label for="description" class="block text-sm font-medium text-gray-700 mb-2">House Type</label>
<textarea id="description" name="description" rows="3" 
placeholder="ex. Single Detached, Twinhome"
class="form-textarea"><?php echo htmlspecialchars($project['description']); ?></textarea>
</div>
</div>  

<!-- Location -->
<div class="form-section">
<h2>Location</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label for="province_id" class="block text-sm font-medium text-gray-700 mb-1">Province*</label>
<select id="province_id" name="province_id" required class="form-select">
<option value="">Select Province</option>
<?php foreach ($provinces as $province): ?>
<option value="<?php echo $province['id']; ?>" <?php echo ($project['province_id'] == $province['id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($province['name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div>
<label for="city_id" class="block text-sm font-medium text-gray-700 mb-1">City*</label>
<select id="city_id" name="city_id" required class="form-select">
<option value="">Select City</option>                                
<?php foreach ($cities as $city): ?>
<option value="<?php echo $city['id']; ?>" 
data-province="<?php echo $city['province_id']; ?>" 
<?php echo ($project['city_id'] == $city['id']) ? 'selected' : ''; ?>
style="<?php echo ($project['province_id'] != $city['province_id']) ? 'display: none;' : ''; ?>">
<?php echo htmlspecialchars($city['name']); ?> (<?php echo htmlspecialchars($city['province_name']); ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="md:col-span-2">
<label for="exact_location" class="block text-sm font-medium text-gray-700 mb-1">Exact Location</label>
<input type="text" id="exact_location" name="exact_location"
class="form-input"
value="<?php echo htmlspecialchars($project['exact_location']); ?>">
</div>
</div>
</div>

<!-- Pricing -->
<div class="form-section">
<h2>Pricing</h2>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
<div>
<label for="price_min" class="block text-sm font-medium text-gray-700 mb-1">Minimum Price*</label>
<input type="number" id="price_min" name="price_min" required step="0.01"
class="form-input"
value="<?php echo htmlspecialchars($project['price_min']); ?>">
</div>
<div>
<label for="price_max" class="block text-sm font-medium text-gray-700 mb-1">Maximum Price*</label>
<input type="number" id="price_max" name="price_max" required step="0.01"
class="form-input"
value="<?php echo htmlspecialchars($project['price_max']); ?>">
</div>
<div>
<label for="commission" class="block text-sm font-medium text-gray-700 mb-1">Commission (%)*</label>
<input type="number" id="commission" name="commission" required step="0.01"
class="form-input"
value="<?php echo htmlspecialchars($project['commission']); ?>">
</div>
<div>
<label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
<select id="priority" name="priority" class="form-select">
<option value="high" <?php echo ($project['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
<option value="medium" <?php echo ($project['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
<option value="low" <?php echo ($project['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
</select>
</div>
</div>
</div>

<!-- Financial Details -->
<div class="form-section">
    <h2>Financial Details</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="total_contract_price" class="block text-sm font-medium text-gray-700 mb-2">Total Contract Price</label>
            <input type="number" id="total_contract_price" name="total_contract_price" step="0.01"
                   class="form-input" placeholder="Enter total contract price"
                   value="<?php echo htmlspecialchars($project['total_contract_price'] ?? ''); ?>">
        </div>
        <div>
            <label for="reservation_fee" class="block text-sm font-medium text-gray-700 mb-2">Reservation Fee</label>
            <input type="number" id="reservation_fee" name="reservation_fee" step="0.01"
                   class="form-input" placeholder="Enter reservation fee"
                   value="<?php echo htmlspecialchars($project['reservation_fee'] ?? ''); ?>">
        </div>
        <div>
            <label for="bank_amortization" class="block text-sm font-medium text-gray-700 mb-2">Bank Amortization</label>
            <input type="number" id="bank_amortization" name="bank_amortization" step="0.01"
                   class="form-input" placeholder="Enter bank amortization"
                   value="<?php echo htmlspecialchars($project['bank_amortization'] ?? ''); ?>">
        </div>
        <div>
            <label for="required_salary" class="block text-sm font-medium text-gray-700 mb-2">Required Salary</label>
            <input type="number" id="required_salary" name="required_salary" step="0.01"
                   class="form-input" placeholder="Enter required salary"
                   value="<?php echo htmlspecialchars($project['required_salary'] ?? ''); ?>">
        </div>
        <div>
            <label for="downpayment_percentage" class="block text-sm font-medium text-gray-700 mb-2">Downpayment %</label>
            <input type="number" id="downpayment_percentage" name="downpayment_percentage" step="0.01" max="100"
                   class="form-input" placeholder="Enter downpayment percentage"
                   value="<?php echo htmlspecialchars($project['downpayment_percentage'] ?? ''); ?>">
        </div>
    </div>
    
    <div class="mt-6">
        <h3 class="text-lg font-medium text-gray-700 mb-4">Downpayment Options</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="downpayment_amount" class="block text-sm font-medium text-gray-700 mb-2">Downpayment Amount</label>
                <input type="number" id="downpayment_amount" name="downpayment_amount" step="0.01"
                       class="form-input" placeholder="e.g., 44000"
                       value="<?php echo htmlspecialchars($project['downpayment_amount'] ?? ''); ?>">
                <p class="text-xs text-gray-500 mt-1">Monthly amount to be paid (e.g., ₱44,000 per month)</p>
            </div>
            <div>
                <label for="downpayment_term" class="block text-sm font-medium text-gray-700 mb-2">Downpayment Term (Months)</label>
                <input type="number" id="downpayment_term" name="downpayment_term" min="1" max="120"
                       class="form-input" placeholder="e.g., 12"
                       value="<?php echo htmlspecialchars($project['downpayment_term'] ?? ''); ?>">
                <p class="text-xs text-gray-500 mt-1">Example: 3, 6, 12, 24, 36 months</p>
            </div>
        </div>
        
    </div>
</div>

<!-- Images -->
<div class="form-section">
<h2>Images</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php for ($i = 1; $i <= 4; $i++): ?>
<div>
<label for="image<?php echo $i; ?>" class="block text-sm font-medium text-gray-700 mb-1">
<?php echo $i == 1 ? 'Main Image' : 'Additional Image ' . ($i - 1); ?>
</label>
<?php if (!empty($project["image$i"])): ?>
<div class="mb-2">
<img src="uploads/projects/<?php echo htmlspecialchars($project["image$i"]); ?>" 
alt="Current Image <?php echo $i; ?>" 
class="w-32 h-32 object-cover rounded border">
<p class="text-sm text-gray-500 mt-1">Current image</p>
</div>
<?php endif; ?>
<input type="file" id="image<?php echo $i; ?>" name="image<?php echo $i; ?>" accept="image/*"
class="form-input">
<?php if (!empty($project["image$i"])): ?>
<label class="flex items-center mt-2">
<input type="checkbox" name="delete_image<?php echo $i; ?>" value="1" class="mr-2">
<span class="text-sm text-red-600">Delete current image</span>
</label>
<?php endif; ?>
</div>
<?php endfor; ?>
</div>
</div>

<!-- Links -->
<div class="form-section">
<h2>Additional Information</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label for="drive_link" class="block text-sm font-medium text-gray-700 mb-1">Drive Link</label>
<input type="url" id="drive_link" name="drive_link"
class="form-input"
value="<?php echo htmlspecialchars($project['drive_link']); ?>">
</div>
<div>
<label for="messenger_link" class="block text-sm font-medium text-gray-700 mb-1">Messenger Link</label>
<input type="url" id="messenger_link" name="messenger_link"
class="form-input"
value="<?php echo htmlspecialchars($project['messenger_link']); ?>">
</div>
</div>
</div>

<!-- Action Buttons -->
<div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-gray-100">
<a href="projectlisting.php" class="btn btn-outline">
Cancel
</a>
<button type="submit" class="btn btn-primary">
<span class="submit-text">Update Project</span>
<span class="loading-text hidden">
<i class="fas fa-spinner fa-spin mr-2"></i>Updating...
</span>
</button>
</div>
</form>

<script>
// PHP variables passed to JavaScript
const initialProjectProvinceId = <?php echo json_encode($project['province_id']); ?>;
const initialProjectCityId = <?php echo json_encode($project['city_id']); ?>;

$(document).ready(function() {
    // Function to update city dropdown based on province
    function updateCityDropdown(provinceId, selectedCityId = null) {
        const $citySelect = $('#city_id');
        
        // Hide all city options except the first one
        $citySelect.find('option').not(':first').hide();
        
        if (provinceId) {
            // Show cities for the selected province
            $citySelect.find(`option[data-province="${provinceId}"]`).show();
            
            // If a specific city ID is provided, try to select it
            if (selectedCityId) {
                const $optionToSelect = $citySelect.find(`option[value="${selectedCityId}"][data-province="${provinceId}"]`);
                if ($optionToSelect.length) {
                    $citySelect.val(selectedCityId);
                } else {
                    // If the selectedCityId is not found for this province, clear selection
                    $citySelect.val('');
                }
            } else {
                // If no specific city ID, clear selection (for manual province change)
                $citySelect.val('');
            }
        } else {
            // No province selected, clear city selection
            $citySelect.val('');
        }
    }
    
    // Initialize city dropdown on page load using the project's actual province and city IDs
    if (initialProjectProvinceId) {
        updateCityDropdown(initialProjectProvinceId, initialProjectCityId);
    }
    
    // Handle province change
    $('#province_id').on('change', function() {
        const provinceId = $(this).val();
        updateCityDropdown(provinceId); // No specific city to select when user manually changes province
    });

    // Form submission
    $('#editProjectForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show loading state
        $('.submit-text').hide();
        $('.loading-text').show();
        $('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: 'api/update_project.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
            // Reset button state
            $('.submit-text').show();
            $('.loading-text').hide();
            $('button[type="submit"]').prop('disabled', false);

            // Remove any previous success message
            $('.success-message').remove();

            if (response.success) {
                // Show green box message only after successful update
                const successBox = $(
                '<div class="success-message">' +
                '<i class="fas fa-check-circle mr-2"></i>' +
                '<strong>Success:</strong> Project updated successfully!' +
                '</div>'
                );
                $('.container .max-w-5xl').prepend(successBox);

                // Optionally redirect after a short delay
                setTimeout(function() {
                window.location.href = 'projectlisting.php';
                }, 1500);
            } else {
                // Show error as a red box instead of alert
                $('.error-message').remove();
                const errorBox = $(
                '<div class="error-message">' +
                '<i class="fas fa-exclamation-triangle mr-2"></i>' +
                '<strong>Error:</strong> ' + (response.message || 'Failed to update project') +
                '</div>'
                );
                $('.container .max-w-5xl').prepend(errorBox);
            }
            },
            error: function(xhr, status, error) {
            // Reset button state
            $('.submit-text').show();
            $('.loading-text').hide();
            $('button[type="submit"]').prop('disabled', false);

            // Show error as a red box instead of alert
            $('.error-message').remove();
            const errorBox = $(
                '<div class="error-message">' +
                '<i class="fas fa-exclamation-triangle mr-2"></i>' +
                '<strong>Error:</strong> An error occurred while updating the project. Please try again.' +
                '</div>'
            );
            $('.container .max-w-5xl').prepend(errorBox);
            }
        });
        });
    });

</script>

</div>
</div>
</body>
</html>
