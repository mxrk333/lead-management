<?php
/**
 * CLI Script: Fetch Facebook Page Messages from Google Sheets
 * Can be run in cron: * * * * * /Applications/XAMPP/xamppfiles/bin/php /path/to/cron/fetch_facebook_messages.php
 * Use --mock to run in simulation/demo mode: php cron/fetch_facebook_messages.php --mock
 */

// Define environment
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo "This script must be run from the command line.\n";
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/switchboard-tracer.php';

// Configure defaults
$assigned_user_id = 7; // Romeo Cerna Cobreta Jr. (admin)
$google_sheet_csv_url = ''; // Define Google Sheet published CSV URL here if available

// Check if we are running in mock/demo mode
$is_mock_mode = (isset($argv) && in_array('--mock', $argv)) || empty($google_sheet_csv_url);

echo "--- Facebook Messages Importer Started ---\n";
if ($is_mock_mode) {
    echo "Mode: DEMO/MOCK MODE (Simulating Message data)\n";
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

// 1. Gather message rows
$message_rows = [];

if ($is_mock_mode) {
    // Generate unique row IDs
    $ts = time();
    $message_rows = [
        [
            'conversation_id' => 'msg_mock_' . ($ts - 100),
            'sender_name' => 'Miguel Lorenzo',
            'message_text' => 'Hi! Is the house in Lancaster Cavite still available? Im an OFW nurse in Dubai and Im planning to buy for my family when I return next year. You can reach my sister locally at 09187654321.',
            'date' => date('Y-m-d H:i:s')
        ],
        [
            'conversation_id' => 'msg_mock_' . ($ts - 50),
            'sender_name' => 'Sarah Santos',
            'message_text' => 'hm po?',
            'date' => date('Y-m-d H:i:s')
        ]
    ];
} else {
    // Fetch from Google Sheet published CSV URL
    echo "Fetching CSV from URL: $google_sheet_csv_url\n";
    $csv_content = file_get_contents($google_sheet_csv_url);
    if ($csv_content === false) {
        echo "Error: Failed to fetch CSV from Google Sheets.\n";
        exit(1);
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

                // Map headers to fields dynamically based on common Zapier mappings
                $mapped = [];
                foreach ($header as $col_idx => $header_name) {
                    $val = isset($row[$col_idx]) ? trim($row[$col_idx]) : '';
                    if (strpos($header_name, 'name') !== false || strpos($header_name, 'sender') !== false) {
                        $mapped['sender_name'] = $val;
                    } elseif (strpos($header_name, 'message') !== false || strpos($header_name, 'text') !== false) {
                        $mapped['message_text'] = $val;
                    } elseif (strpos($header_name, 'date') !== false || strpos($header_name, 'time') !== false) {
                        $mapped['date'] = $val;
                    } elseif (strpos($header_name, 'id') !== false) {
                        $mapped['conversation_id'] = $val;
                    }
                }

                $mapped['conversation_id'] = !empty($mapped['conversation_id']) ? $mapped['conversation_id'] : 'sheet_row_' . $row_idx;
                if (!empty($mapped['sender_name']) && !empty($mapped['message_text'])) {
                    $message_rows[] = $mapped;
                }
                $row_idx++;
            }
            fclose($handle);
        }
        unlink($temp_file);
    }
}

echo "Found " . count($message_rows) . " message(s) to process.\n";

// 2. Process each message row
foreach ($message_rows as $msg) {
    $convoId = $msg['conversation_id'];
    $senderName = $msg['sender_name'];
    $messageText = $msg['message_text'];

    echo "\nProcessing Message from: $senderName...\n";

    // Check if message was already imported
    $stmt = $conn->prepare("SELECT id FROM leads WHERE google_sheet_row_id = ?");
    $stmt->bind_param("s", $convoId);
    $stmt->execute();
    $dup_result = $stmt->get_result();
    $stmt->close();

    if ($dup_result && $dup_result->num_rows > 0) {
        echo "Skip: Message from '$senderName' already processed.\n";
        continue;
    }

    // Call Gemini AI API to extract contact info and analyze intent
    echo "Calling Gemini AI to parse message text...\n";
    $ai_analysis = callGeminiMessageParser($senderName, $messageText);

    // Extracted data
    $fullName = $ai_analysis['full_name'] ?? $senderName;
    $phone = $ai_analysis['phone'] ?? '';
    $email = $ai_analysis['email'] ?? '';
    $city = $ai_analysis['city'] ?? '';
    $jobTitle = $ai_analysis['job_title'] ?? '';
    $relationshipStatus = $ai_analysis['relationship_status'] ?? '';
    
    // AI Insights
    $quality = $ai_analysis['quality'] ?? 'Medium';
    $summary = $ai_analysis['summary'] ?? "Inquired via Facebook Page. Message: " . substr($messageText, 0, 50) . "...";
    $action = $ai_analysis['action'] ?? "Reply to their message on Facebook to gather more requirements.";

    // Map Lead Quality to System Temperature
    $temperature = 'Warm';
    if ($quality === 'High') {
        $temperature = 'Hot';
    } elseif ($quality === 'Low') {
        $temperature = 'Cold';
    }

    echo "Gemini Extraction Results:\n";
    echo "  - Extracted Phone: $phone\n";
    echo "  - Extracted City: $city\n";
    echo "  - Extracted Job: $jobTitle\n";
    echo "  - Quality: $quality\n";
    echo "  - Summary: $summary\n";
    echo "  - Recommended Action: $action\n";

    // Save lead into database using extended addLead
    $remarks = "Imported from Facebook Page Message. Original Message: \"{$messageText}\". AI Summary: {$summary}";

    // Default system values
    $facebook = '';
    $linkedin = '';
    $status = 'Inquiry';
    $source = 'Facebook Page'; // Differentiating from "Facebook Ads"
    $lead_classification = 'Organic Message';
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
        $convoId
    );

    if ($lead_id) {
        echo "Success: Message from '$fullName' saved as lead ID: $lead_id.\n";

        try {
            // Log import activity
            addLeadActivity($lead_id, $assigned_user_id, 'Import', 'Facebook Page message automatically parsed and imported by Gemini AI.');

            // Trigger system notification
            $notif_title = "💬 New FB Page Message: " . $fullName;
            $notif_msg = "A new message was parsed. Quality: $quality. Action: $action";
            createNotification($assigned_user_id, $notif_title, $notif_msg, 'lead', $lead_id, 'lead');
        } catch (Throwable $t) {
            echo "Exception caught during post-save steps: " . $t->getMessage() . "\n";
        }
    } else {
        echo "Error: Failed to save lead '$fullName' to database.\n";
    }
}

$conn->close();
echo "\n--- Facebook Messages Importer Finished ---\n";

/**
 * Call Gemini AI to parse unstructured messages into structured lead data
 */
function callGeminiMessageParser($senderName, $messageText)
{
    // API key comes from .env (see includes/switchboard-tracer.php's require of includes/env.php)
    $apiKey = getenv('GEMINI_API_KEY');
    $model = 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $apiKey;

    $prompt = "You are an AI Assistant for InnerSPARC Realty Corporation (a Philippine real estate developer).
You need to analyze an incoming chat message from a Facebook Page and extract structured lead data.

Sender Name: {$senderName}
Message Text: \"{$messageText}\"

Extract the following information if available. If it's not mentioned in the text, leave it blank (empty string):
- phone (detect any Philippine mobile number format)
- email
- city/location
- job_title / occupation
- relationship_status

Also, provide these AI insights based on the message content:
1. 'quality': 'High', 'Medium', or 'Low'. (e.g. \"hm po?\" is Low/Medium. \"I want to buy a house, I am an OFW\" is High).
2. 'summary': A concise 1-2 sentence summary of their inquiry.
3. 'action': A recommended sales action for the agent.

You MUST respond in JSON format ONLY. Do NOT wrap the JSON response in any markdown tags or code blocks.
Response format:
{
  \"full_name\": \"{$senderName}\",
  \"phone\": \"\",
  \"email\": \"\",
  \"city\": \"\",
  \"job_title\": \"\",
  \"relationship_status\": \"\",
  \"quality\": \"Medium\",
  \"summary\": \"...\",
  \"action\": \"...\"
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
            'maxOutputTokens' => 512
        ]
    ];

    $headers = ['Content-Type: application/json'];

    try {
        return switchboard_traced([
            'name' => 'facebook-messages.parse-message',
            'model' => $model,
            'inputs' => ['senderName' => $senderName, 'messageText' => $messageText],
            'tags' => ['facebook-messages', 'gemini'],
        ], function () use ($url, $headers, $postData) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new RuntimeException("Gemini request failed with HTTP {$httpCode}");
            }

            $data = json_decode($response, true);
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                throw new RuntimeException('Invalid Gemini response format');
            }

            $text = trim($data['candidates'][0]['content']['parts'][0]['text']);

            // Clean markdown code blocks
            if (strpos($text, '```') !== false) {
                $text = preg_replace('/```json|```/', '', $text);
                $text = trim($text);
            }

            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Gemini returned malformed JSON: ' . json_last_error_msg());
            }

            return [
                'outputs' => $json,
                'tokenUsage' => switchboard_gemini_token_usage($data),
            ];
        });
    } catch (Exception $e) {
        // Fallback if the API call fails — same behavior as before tracing was added.
        return [
            'full_name' => $senderName,
            'quality' => 'Medium',
            'summary' => "Inquired via Facebook Page: " . substr($messageText, 0, 100),
            'action' => 'Follow up with client to ask for contact details and requirements.'
        ];
    }
}
?>
