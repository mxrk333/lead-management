<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Ensure session is started so sidebar can detect user role
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load shared sidebar
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/header.php';

try {
    $conn = getDbConnection();
    // Enable eror reporting for debugging
    error_log("Fetching provinces and cities");

    // For displaying the current date in "As of m/d/Y" format
    $financial_details_date = date("n/j/Y");
    // Fetch provinces
    $provinces_query = "SELECT * FROM provinces ORDER BY name";
    $provinces_result = $conn->query($provinces_query);
    if (!$provinces_result) {
        throw new Exception("Error fetching provinces: " . $conn->error);
    }
    $provinces = $provinces_result->fetch_all(MYSQLI_ASSOC);
    error_log("Found " . count($provinces) . " provinces");

    // Fetch cities with province information
    $cities_query = "SELECT c.*, p.name as province_name 
                    FROM cities c 
                    INNER JOIN provinces p ON c.province_id = p.id 
                    ORDER BY c.name";
    $cities_result = $conn->query($cities_query);
    if (!$cities_result) {
        throw new Exception("Error fetching cities: " . $conn->error);
    }
    $cities = $cities_result->fetch_all(MYSQLI_ASSOC);
    error_log("Found " . count($cities) . " cities");

} catch (Exception $e) {
    error_log("Error in add-project.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Project - Inner SPARC</title>    <script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
/* Form Styling */
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
.form-label {
display: block;
font-size: 0.875rem;
font-weight: 500;
color: #334155;
margin-bottom: 0.5rem;
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
display: none;
        }
.error-message {
background-color: #fef2f2;
border-left: 4px solid var(--error-color);
color: #991b1b;
        }
.success-message {
background-color: #ecfdf5;
border-left: 4px solid var(--success-color);
color: #065f46;
        }
.image-preview {
max-width: 100%;
max-height: 200px;
border: 2px dashed #e2e8f0;
border-radius: 0.5rem;
margin-top: 1rem;
padding: 1rem;
display: none;
background-color: #f8fafc;
        }
.image-preview img {
width: 100%;
height: 100%;
object-fit: contain;
border-radius: 0.375rem;
        }
.loading {
position: relative;
pointer-events: none;
opacity: 0.8;
        }
.loading::after {
content: '';
position: absolute;
top: 50%;
left: 50%;
width: 1.5rem;
height: 1.5rem;
margin: -0.75rem 0 0 -0.75rem;
border: 2px solid var(--primary-color);
border-top-color: transparent;
border-radius: 50%;
animation: spin 1s linear infinite;
        }
@keyframes spin {
to { transform: rotate(360deg); }
        }
/* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
padding: 1rem 0.75rem;
            }
.form-section {
padding: 1.25rem;
            }
.grid-cols-1 > div {
width: 100%;
            }
        }
</style>
</head>

<body class="bg-gray-50">
<div class="container">
<div class="max-w-5xl mx-auto">
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
<div>
<h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Add New Project</h1>
<p class="text-sm text-gray-500 mt-1">Fill in the details below to add a new project</p>
</div>
<a href="projectlisting.php" class="btn btn-outline flex items-center text-sm sm:text-base">
<i class="fas fa-arrow-left mr-2"></i>Back to Projects
</a>
</div>
<form id="addProjectForm" action="api/save_project.php" method="POST" enctype="multipart/form-data">
<!-- Basic Information -->
<div class="form-section">
<h2>Basic Information</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
<div class="space-y-4">
<div>
<label for="name" class="block text-sm font-medium text-gray-700 mb-2">Project Name*</label>
<input type="text" id="name" name="name" required
class="form-input"
placeholder="Enter project name">
</div>
<div>
<label for="house_model" class="block text-sm font-medium text-gray-700 mb-2">House Model</label>
<input type="text" id="house_model" name="house_model" 
class="form-input"
placeholder="ex. Lincoln, Kennedy">
</div>
</div>
<div class="space-y-4">
<div>
<label for="developer" class="block text-sm font-medium text-gray-700 mb-2">Developer*</label>
<input type="text" id="developer" name="developer" required
class="form-input"
placeholder="Enter developer name">
</div>
<div>
<label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status*</label>
<select id="status" name="status" required
class="form-select">
<option value="rfo">RFO/Preselling/OGC</option>
<option value="rfo">RFO (Ready For Occupancy)</option>
<option value="preselling">Preselling</option>
<option value="ogc">OGC (On Going Construction)</option>
<option value="rfo_preselling">RFO/Preselling</option>
<option value="preselling_ogc">Preselling/OGC</option>
</select>
</div>
</div>
</div>
<!-- Description -->
<div class="form-section">
<div class="space-y-2">
<label for="description" class="form-label">House Type</label>
<textarea id="description" name="description" rows="3" 
placeholder="ex. Single Detached, Twinhome"
class="form-textarea"></textarea>
</div>
</div>  
<!-- Location -->
<div class="form-section">
<h2>Location</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label for="province_id" class="block text-sm font-medium text-gray-700 mb-1">Province*</label>
<select id="province_id" name="province_id" required
class="form-select">
<option value="">Select Province</option>
<?php foreach ($provinces as $province): ?>
<option value="<?php echo $province['id']; ?>"><?php echo htmlspecialchars($province['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label for="city_id" class="block text-sm font-medium text-gray-700 mb-1">City*</label>
<select id="city_id" name="city_id" required
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<option value="">Select City</option>                                <?php 
// Debug information
                                error_log("Starting to group cities by province");
// Group cities by province
$cities_by_province = [];
foreach ($cities as $city) {
$province_id = $city['province_id'];
if (!isset($cities_by_province[$province_id])) {
$cities_by_province[$province_id] = [];
                                    }
$cities_by_province[$province_id][] = $city;
                                }
                                error_log("Cities by province: " . print_r($cities_by_province, true));
// Output all cities with data-province attribute
foreach ($cities as $city): 
// Debug information
                                    error_log("Processing city: " . print_r($city, true));
?>
<option value="<?php echo $city['id']; ?>" 
data-province="<?php echo $city['province_id']; ?>" 
style="display: none;">
<?php echo htmlspecialchars($city['name']); ?>
                                        (<?php echo htmlspecialchars($city['province_name']); ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<!-- Alert bar for success/error messages -->
<?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
    <div id="alertBar" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-lg shadow-lg text-white text-center transition-all duration-300
        <?php if (isset($_SESSION['success_message'])): ?> bg-green-600 <?php else: ?> bg-red-600 <?php endif; ?>">
        <?php
            if (isset($_SESSION['success_message'])) {
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            } elseif (isset($_SESSION['error_message'])) {
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            }
        ?>
    </div>
    <script>
        $(function() {
            setTimeout(function() {
                $('#alertBar').fadeOut(500);
            }, 3500);
        });
    </script>
<?php endif; ?>
</div>
<div class="md:col-span-2">
<label for="exact_location" class="block text-sm font-medium text-gray-700 mb-1">Exact Location</label>
<input type="text" id="exact_location" name="exact_location"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
</div>
<!-- Manage location data buttons -->
<div class="flex gap-4 mt-2">
<button type="button" id="manageProvincesBtn" class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="fas fa-map-marked-alt"></i><span>Manage Provinces</span></button>
<button type="button" id="manageCitiesBtn" class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="fas fa-city"></i><span>Manage Cities</span></button>
</div>
</div>
<!-- Pricing -->
<div class="mb-8">
<h2 class="text-xl font-semibold mb-4">Pricing</h2>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
<div>
<label for="price_min" class="block text-sm font-medium text-gray-700 mb-1">Minimum Price*</label>
<input type="number" id="price_min" name="price_min" required step="0.01"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
<div>
<label for="price_max" class="block text-sm font-medium text-gray-700 mb-1">Maximum Price*</label>
<input type="number" id="price_max" name="price_max" required step="0.01"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
<div>
<label for="commission" class="block text-sm font-medium text-gray-700 mb-1">Commission (%)*</label>
<input type="number" id="commission" name="commission" required step="0.01" value="5.00"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
<div>
<label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
<select id="priority" name="priority" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<option value="high">High</option>
<option value="medium" selected>Medium</option>
<option value="low">Low</option>
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
                   class="form-input" placeholder="Enter total contract price">
        </div>
        <div>
            <label for="reservation_fee" class="block text-sm font-medium text-gray-700 mb-2">Reservation Fee</label>
            <input type="number" id="reservation_fee" name="reservation_fee" step="0.01"
                   class="form-input" placeholder="Enter reservation fee">
        </div>
        <div>
            <label for="bank_amortization" class="block text-sm font-medium text-gray-700 mb-2">Bank Amortization</label>
            <input type="number" id="bank_amortization" name="bank_amortization" step="0.01"
                   class="form-input" placeholder="Enter bank amortization">
        </div>
        <div>
            <label for="required_salary" class="block text-sm font-medium text-gray-700 mb-2">Required Salary</label>
            <input type="number" id="required_salary" name="required_salary" step="0.01"
                   class="form-input" placeholder="Enter required salary">
        </div>
        <div>
            <label for="downpayment_percentage" class="block text-sm font-medium text-gray-700 mb-2">Downpayment %</label>
            <input type="number" id="downpayment_percentage" name="downpayment_percentage" step="0.01" max="100"
                   class="form-input" placeholder="Enter downpayment percentage">
        </div>
    </div>
    
    <div class="mt-6">
        <h3 class="text-lg font-medium text-gray-700 mb-4">Monthly Downpayment Options</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="monthly_downpayment_3mos" class="block text-sm font-medium text-gray-700 mb-2">3 Months</label>
                <input type="number" id="monthly_downpayment_3mos" name="monthly_downpayment_3mos" step="0.01"
                       class="form-input" placeholder="3 months payment">
            </div>
            <div>
                <label for="monthly_downpayment_6mos" class="block text-sm font-medium text-gray-700 mb-2">6 Months</label>
                <input type="number" id="monthly_downpayment_6mos" name="monthly_downpayment_6mos" step="0.01"
                       class="form-input" placeholder="6 months payment">
            </div>
            <div>
                <label for="monthly_downpayment_12mos" class="block text-sm font-medium text-gray-700 mb-2">12 Months</label>
                <input type="number" id="monthly_downpayment_12mos" name="monthly_downpayment_12mos" step="0.01"
                       class="form-input" placeholder="12 months payment">
            </div>
            <div>
                <label for="monthly_downpayment_18mos" class="block text-sm font-medium text-gray-700 mb-2">18 Months</label>
                <input type="number" id="monthly_downpayment_18mos" name="monthly_downpayment_18mos" step="0.01"
                       class="form-input" placeholder="18 months payment">
            </div>
        </div>
    </div>
</div>
<!-- Images -->
<div class="mb-8">
<h2 class="text-xl font-semibold mb-4">Images</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label for="image1" class="block text-sm font-medium text-gray-700 mb-1">Main Image</label>
<input type="file" id="image1" name="image1" accept="image/*"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<div class="image-preview" id="imagePreview1">
<img id="previewImage1" src="/placeholder.svg" alt="Image Preview">
</div>
</div>
<div>
<label for="image2" class="block text-sm font-medium text-gray-700 mb-1">Additional Image 1</label>
<input type="file" id="image2" name="image2" accept="image/*"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<div class="image-preview" id="imagePreview2">
<img id="previewImage2" src="/placeholder.svg" alt="Image Preview">
</div>
</div>
<div>
<label for="image3" class="block text-sm font-medium text-gray-700 mb-1">Additional Image 2</label>
<input type="file" id="image3" name="image3" accept="image/*"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<div class="image-preview" id="imagePreview3">
<img id="previewImage3" src="/placeholder.svg" alt="Image Preview">
</div>
</div>
<div>
<label for="image4" class="block text-sm font-medium text-gray-700 mb-1">Additional Image 3</label>
<input type="file" id="image4" name="image4" accept="image/*"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
<div class="image-preview" id="imagePreview4">
<img id="previewImage4" src="/placeholder.svg" alt="Image Preview">
</div>
</div>
</div>
</div>
<!-- Links -->
<div class="mb-8">
<h2 class="text-xl font-semibold mb-4">Additional Information</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label for="drive_link" class="block text-sm font-medium text-gray-700 mb-1">Drive Link</label>
<input type="url" id="drive_link" name="drive_link"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
<div>
<label for="messenger_link" class="block text-sm font-medium text-gray-700 mb-1">Messenger Link</label>
<input type="url" id="messenger_link" name="messenger_link"
class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-primary focus:border-blue-primary">
</div>
</div>
</div>
<!-- Action Buttons -->
<div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-gray-100">
<button type="button" class="btn btn-outline" onclick="window.history.back()">
                        Cancel
</button>
<button type="submit" class="btn btn-primary">
<span class="submit-text">Save Project</span>
<span class="loading-text hidden">
<i class="fas fa-spinner fa-spin mr-2"></i>Saving...
</span>
</button>
</div>
</form>
<script>
$(document).ready(function() {
    // Add form submission debugging
    $('#addProjectForm').on('submit', function(e) {
        console.log('Form is being submitted');
        console.log('Priority value:', $('#priority').val());
        
        // Let the form submit normally
        return true;
    });

    // Rest of the existing code...
    $('#province_id').on('change', function() {
        const provinceId = $(this).val();
        const $citySelect = $('#city_id');
        
        $citySelect.val('');
        $citySelect.find('option').not(':first').hide();
        
        if (provinceId) {
            $citySelect.find(`option[data-province="${provinceId}"]`).show();
        }
    });

    // Handle image previews
    $('input[type="file"]').each(function() {
        $(this).on('change', function(e) {
            const fileInput = e.target;
            const previewId = fileInput.id.replace('image', 'imagePreview');
            const previewDiv = $(`#${previewId}`);
            const previewImg = previewDiv.find('img');

            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    fileInput.value = '';
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    alert('Please upload an image file');
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.attr('src', e.target.result);
                    previewDiv.show();
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.hide();
            }
        });
    });

    // Show success/error messages
    <?php if (isset($_SESSION['success_message'])): ?>
        alert('<?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>');
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        alert('<?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>');
    <?php endif; ?>
});
</script>

</div>
</div>
</body>
</html>
