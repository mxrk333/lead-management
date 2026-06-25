<?php
/**
 * CLI Script: Fetch Facebook Leads from Google Sheets
 * Can be run in cron: * * * * * /Applications/XAMPP/xamppfiles/bin/php /path/to/cron/fetch_facebook_leads.php
 * Use --mock to run in simulation/demo mode: php cron/fetch_facebook_leads.php --mock
 */

// Define environment
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo "This script must be run from the command line.\n";
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Configure defaults
$assigned_user_id = 7; // Romeo Cerna Cobreta Jr. (admin)
$google_sheet_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSPD0yTCdFpSvTMXs4KrcQFDnBPh8Rnpa5wSCseoYVsm52Yd1u039INPnUs7DbJh5YWWCQZg2zZz2dE/pub?gid=0&single=true&output=csv'; // Live CSV URL

// Check if we are running in mock/demo mode
$is_mock_mode = (isset($argv) && in_array('--mock', $argv)) || empty($google_sheet_csv_url);

echo "--- Facebook Leads Importer Started ---\n";
if ($is_mock_mode) {
    echo "Mode: DEMO/MOCK MODE (Simulating Google Sheet data)\n";
} else {
    echo "Mode: LIVE MODE (Fetching from Google Sheets URL)\n";
}

$conn = getDbConnection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

// Find a valid admin or fallback user ID to assign leads
$admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($admin_query && $admin_query->num_rows > 0) {
    $assigned_user_id = $admin_query->fetch_assoc()['id'];
}
echo "Default assigned user ID: $assigned_user_id\n";

// 1. Gather lead rows
$lead_rows = [];

if ($is_mock_mode) {
    // Generate unique row IDs using timestamp to allow running multiple times and inserting new records
    $ts = time();
    $lead_rows = [
        [
            'row_id' => 'fb_row_mock_' . ($ts - 100),
            'full_name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'email' => 'juan.delacruz.' . $ts . '@example.com',
            'city' => 'Cavite',
            'job_title' => 'Civil Engineer',
            'relationship_status' => 'Married'
        ],
        [
            'row_id' => 'fb_row_mock_' . ($ts - 50),
            'full_name' => 'Maria Clara Santos',
            'phone' => '09189876543',
            'email' => 'maria.clara.' . $ts . '@example.com',
            'city' => 'Quezon City',
            'job_title' => 'Nurse',
            'relationship_status' => 'Single'
        ],
        [
            'row_id' => 'fb_row_mock_' . $ts,
            'full_name' => 'Jose Rizalino',
            'phone' => '09097778888',
            'email' => 'jose.rizal.' . $ts . '@example.com',
            'city' => 'Cavite',
            'job_title' => 'IT Consultant',
            'relationship_status' => 'Married'
        ]
    ];
} else {
    // Fetch from Google Sheet published CSV URL
    echo "Fetching CSV from URL: $google_sheet_csv_url\n";
    $csv_content = file_get_contents($google_sheet_csv_url);
    if ($csv_content === false) {
        echo "Error: Failed to fetch CSV from Google Sheets. Switching to mock mode to demonstrate functionality...\n";
        $is_mock_mode = true;
        // Re-run mock setup
        $ts = time();
        $lead_rows = [
            [
                'row_id' => 'fb_row_mock_' . ($ts - 100),
                'full_name' => 'Juan Dela Cruz',
                'phone' => '09171234567',
                'email' => 'juan.delacruz.' . $ts . '@example.com',
                'city' => 'Cavite',
                'job_title' => 'Civil Engineer',
                'relationship_status' => 'Married'
            ]
        ];
    } else {
        $temp_file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($temp_file, $csv_content);

        if (($handle = fopen($temp_file, 'r')) !== false) {
            $header = null;
            $row_idx = 1;
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = array_map('strtolower', $row);
                    continue;
                }

                // Map headers to fields dynamically
                $mapped = [];
                foreach ($header as $col_idx => $header_name) {
                    $val = isset($row[$col_idx]) ? trim($row[$col_idx]) : '';
                    if ($header_name === 'full_name' || $header_name === 'name') {
                        $mapped['full_name'] = $val;
                    } elseif ($header_name === 'phone_number' || $header_name === 'phone') {
                        $mapped['phone'] = $val;
                    } elseif ($header_name === 'email') {
                        $mapped['email'] = $val;
                    } elseif ($header_name === 'city' || $header_name === 'location') {
                        $mapped['city'] = $val;
                    } elseif ($header_name === 'job_title' || $header_name === 'occupation') {
                        $mapped['job_title'] = $val;
                    } elseif ($header_name === 'relationship_status') {
                        $mapped['relationship_status'] = $val;
                    } elseif ($header_name === 'id') {
                        $mapped['id'] = $val;
                    }
                }

                $mapped['row_id'] = !empty($mapped['id']) ? $mapped['id'] : 'sheet_row_' . $row_idx;
                if (!empty($mapped['full_name'])) {
                    $lead_rows[] = $mapped;
                }
                $row_idx++;
            }
            fclose($handle);
        }
        unlink($temp_file);
    }
}

echo "Found " . count($lead_rows) . " lead(s) to process.\n";

// 2. Process each lead row
foreach ($lead_rows as $lead) {
    $rowId = $lead['row_id'];
    $fullName = $lead['full_name'];
    $phone = $lead['phone'] ?? '';
    $email = $lead['email'] ?? '';
    $city = $lead['city'] ?? '';
    $jobTitle = $lead['job_title'] ?? '';
    $relationshipStatus = $lead['relationship_status'] ?? '';

    echo "\nProcessing Lead: $fullName ($email)...\n";

    // Check if lead was already imported (check sheet row ID or email)
    $stmt = $conn->prepare("SELECT id FROM leads WHERE google_sheet_row_id = ? OR (email = ? AND email != '')");
    $stmt->bind_param("ss", $rowId, $email);
    $stmt->execute();
    $dup_result = $stmt->get_result();
    $stmt->close();

    if ($dup_result && $dup_result->num_rows > 0) {
        echo "Skip: Lead '$fullName' already exists in database.\n";
        continue;
    }

    // Call Gemini AI API to analyze lead quality & summary
    echo "Calling Gemini AI to analyze profile...\n";
    $ai_analysis = callGeminiLeadAnalysis($fullName, $city, $jobTitle, $relationshipStatus);

    $quality = $ai_analysis['quality'] ?? 'Medium';
    $summary = $ai_analysis['summary'] ?? "Lead from $city working as $jobTitle.";
    $action = $ai_analysis['action'] ?? "Contact client to qualify property interest.";

    // Map Lead Quality to System Temperature
    $temperature = 'Warm';
    if ($quality === 'High') {
        $temperature = 'Hot';
    } elseif ($quality === 'Low') {
        $temperature = 'Cold';
    }

    echo "Gemini Analysis Results:\n";
    echo "  - Quality: $quality\n";
    echo "  - Summary: $summary\n";
    echo "  - Recommended Action: $action\n";
    echo "  - Assigned Temp: $temperature\n";

    // Save lead into database using extended addLead
    $remarks = "Imported from Facebook Leads via Google Sheets. Gemini AI Summary: " . $summary . " Recommended Action: " . $action;

    // Default system values
    $facebook = '';
    $linkedin = '';
    $status = 'Inquiry';
    $source = 'Facebook Ads';
    $lead_classification = 'Facebook Lead';
    $developer = 'Not Specified';
    $project_model = 'Not Specified';
    $price = 0.00;

    $lead_id = addLead(
        $assigned_user_id,
        $fullName,
        $phone,
        $email,
        $facebook,
        $linkedin,
        $temperature,
        $status,
        $source,
        $lead_classification,
        $developer,
        $project_model,
        $price,
        $remarks,
        $city,
        $jobTitle,
        $relationshipStatus,
        $summary,
        $quality,
        $action,
        $rowId
    );

    if ($lead_id) {
        echo "Success: Lead '$fullName' saved to database with ID: $lead_id.\n";

        try {
            // Log import activity
            echo "Logging lead activity...\n";
            addLeadActivity($lead_id, $assigned_user_id, 'Import', 'Facebook lead imported from Google Sheets and analyzed by Gemini AI.');

            // Trigger system notification
            echo "Creating notification...\n";
            $notif_title = "🆕 Facebook Lead: " . $fullName;
            $notif_msg = "A new Facebook Lead has been imported. AI Quality: $quality. Recommended Action: $action";
            createNotification($assigned_user_id, $notif_title, $notif_msg, 'lead', $lead_id, 'lead');

            echo "Created system notification and activity log.\n";
        } catch (Throwable $t) {
            echo "Exception caught during post-save steps: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine() . "\n";
            echo "Stack trace:\n" . $t->getTraceAsString() . "\n";
        }
    } else {
        echo "Error: Failed to save lead '$fullName' to database.\n";
    }
}

$conn->close();
echo "\n--- Facebook Leads Importer Finished ---\n";

/**
 * Call Gemini AI to perform lead profiling and quality analysis
 */
function callGeminiLeadAnalysis($name, $city, $job, $relationship)
{
    // API Details (Reusing the key active in the LMS project)
    $apiKey = 'AQ.Ab8RN6Kw1CoGjGimjFzDeCqvdCfjNmSZjvPNmf2SULH6mBO8jQ';
    $model = 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $apiKey;

    $prompt = "You are an AI Sales Assistant for InnerSPARC Realty Corporation (a Philippine real estate developer).
Analyze the following Facebook lead profile for a property purchase:
- Client Name: {$name}
- City/Location: {$city}
- Job Title/Occupation: {$job}
- Relationship Status: {$relationship}

Determine:
1. Lead Quality: 'High', 'Medium', or 'Low'.
   - 'High': Typically married professionals, engineers, nurses, IT consultants, doctors, OFWs, or corporate managers with high purchasing power.
   - 'Medium': Other employed professionals, self-employed, or single individuals with moderate income potential.
   - 'Low': Unemployed, students, or profile details that are highly incomplete or low-intent.
2. A concise 1-2 sentence AI summary of the lead in English.
3. A recommended sales action (e.g., 'Assign to Cavite specialist and follow up within 24 hours to present house models.').

You MUST respond in JSON format ONLY. Do NOT wrap the JSON response in any markdown tags or code blocks.
Response format:
{
  \"quality\": \"High\" | \"Medium\" | \"Low\",
  \"summary\": \"A short 1-2 sentence profile summary...\",
  \"action\": \"Actionable recommended sales step...\"
}
";

    $postData = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 256
        ]
    ];

    $headers = ['Content-Type: application/json'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($data['candidates'][0]['content']['parts'][0]['text']);

            // Clean markdown code blocks if the model returned them
            if (strpos($text, '```') !== false) {
                $text = preg_replace('/```json|```/', '', $text);
                $text = trim($text);
            }

            $json = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
    }

    // Fallback if API fails or response is malformed
    // Apply basic rule-based classification
    $quality = 'Medium';
    $lowerJob = strtolower($job);
    $highQualityJobs = ['engineer', 'nurse', 'doctor', 'manager', 'consultant', 'developer', 'ofw', 'director', 'accountant'];
    foreach ($highQualityJobs as $hqJob) {
        if (strpos($lowerJob, $hqJob) !== false) {
            $quality = 'High';
            break;
        }
    }

    $summary = "$name from $city. Relationship: $relationship. Occupation: $job.";
    $action = "Follow up with client to query budget and property preferences.";

    return [
        'quality' => $quality,
        'summary' => $summary,
        'action' => $action
    ];
}
?>