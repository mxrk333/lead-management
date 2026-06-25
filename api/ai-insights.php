<?php
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$connection = getDbConnection();

if (!$connection) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// 1. Overall stats
$statsQuery = "SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as total_closed,
                SUM(expected_commission) as total_commission,
                COUNT(DISTINCT user_id) as total_agents
              FROM leads";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// 2. Leads by Source
$sourceQuery = "SELECT 
                    source, 
                    COUNT(*) as count, 
                    SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                    SUM(expected_commission) as commissions
                FROM leads
                GROUP BY source
                ORDER BY count DESC";
$sourceResult = mysqli_query($connection, $sourceQuery);
$sources = mysqli_fetch_all($sourceResult, MYSQLI_ASSOC);

// 3. Leads by Temperature
$tempQuery = "SELECT 
                temperature, 
                COUNT(*) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals
              FROM leads
              GROUP BY temperature";
$tempResult = mysqli_query($connection, $tempQuery);
$temps = mysqli_fetch_all($tempResult, MYSQLI_ASSOC);

// 4. Top Developer / Properties
$propQuery = "SELECT 
                developer, 
                project_model, 
                COUNT(*) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals
              FROM leads
              WHERE project_model IS NOT NULL AND project_model != ''
              GROUP BY developer, project_model
              ORDER BY count DESC
              LIMIT 5";
$propResult = mysqli_query($connection, $propQuery);
$properties = mysqli_fetch_all($propResult, MYSQLI_ASSOC);

// Get top performer agent
$topAgentQuery = "SELECT u.name, 
                  COUNT(l.id) as total_leads,
                  SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals
           FROM users u
           LEFT JOIN leads l ON u.id = l.user_id
           WHERE u.role = 'agent'
           GROUP BY u.id
           ORDER BY closed_deals DESC
           LIMIT 1";
$topAgentResult = mysqli_query($connection, $topAgentQuery);
$topAgent = mysqli_fetch_assoc($topAgentResult);

// Your Google AI Studio API key
$apiKey = 'AQ.Ab8RN6Kw1CoGjGimjFzDeCqvdCfjNmSZjvPNmf2SULH6mBO8jQ';

// First, try to get available models
$listUrl = 'https://generativelanguage.googleapis.com/v1/models?key=' . $apiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $listUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$listResponse = curl_exec($ch);
$listHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Priority order: try different models
$availableModel = 'gemini-2.0-flash'; // Use more stable model
$supportedModels = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-pro'];

if ($listHttpCode === 200) {
    $models = json_decode($listResponse, true);
    if (isset($models['models']) && is_array($models['models'])) {
        foreach ($models['models'] as $model) {
            $modelName = $model['name'] ?? '';
            foreach ($supportedModels as $supported) {
                if (strpos($modelName, $supported) !== false) {
                    $availableModel = basename($modelName);
                    break 2;
                }
            }
        }
    }
}

// Prepare the prompt with the data
$prompt = "You are a Campaign Optimization & Real Estate Marketing Consultant. Analyze the following real-time campaign performance data from our sales pipeline and generate a detailed Business Intelligence & Marketing Report.

DATABASE PERFORMANCE SUMMARY:
- Total Leads (Reach): {$stats['total_leads']}
- Closed Deals (Conversions): {$stats['total_closed']}
- Total Expected Commission: ₱" . number_format($stats['total_commission'], 2) . "
- Overall Conversion Rate: " . ($stats['total_leads'] > 0 ? round(($stats['total_closed'] / $stats['total_leads']) * 100, 1) : 0) . "%

CAMPAIGN PERFORMANCE BY LEAD SOURCE:
";
foreach ($sources as $src) {
    $srcName = !empty($src['source']) ? $src['source'] : 'Direct / Organic';
    $conv = $src['count'] > 0 ? round(($src['closed_deals'] / $src['count']) * 100, 1) : 0;
    $prompt .= "- Campaign [{$srcName}]: {$src['count']} leads, {$src['closed_deals']} closed deals, Conversion Rate: {$conv}%, Commission: ₱" . number_format($src['commissions'], 2) . "\n";
}

$prompt .= "\nLEADS BY TEMPERATURE (QUALITY DISTRIBUTION):\n";
foreach ($temps as $t) {
    $tempName = !empty($t['temperature']) ? $t['temperature'] : 'Unassigned';
    $prompt .= "- {$tempName} Leads: {$t['count']} leads, {$t['closed_deals']} closed deals\n";
}

$prompt .= "\nTOP 5 PROJECT MODELS INQUIRIES:\n";
foreach ($properties as $p) {
    $prompt .= "- {$p['project_model']} (Developer: {$p['developer']}): {$p['count']} leads, {$p['closed_deals']} closed deals\n";
}

$prompt .= "
Provide an extremely detailed, professional, and actionable report containing:
1. CAMPAIGN METRICS ANALYSIS: Evaluate the ROI and performance of each lead source. Compare channels (e.g., which channels drive high lead volumes vs. high-value closed deals).
2. REAL ESTATE MARKETING STRATEGIES: Give concrete suggestions for running social media or search ads for our top properties, including specific copywriting hooks, targeting criteria (e.g. demographic, interests), and budget allocation tips.
3. PIPELINE & LEAD TEMPERATURE IMPROVEMENTS: Suggest how to nurture 'Warm' and 'Cold' leads into closed deals, and how to expedite 'Hot' leads.
4. SALES GUIDE FOR BEGINNERS (TAGLISH/TAGALOG): Provide a highly encouraging, step-by-step Philippine real estate sales guide. Include tips on handling client objections, following up with online leads (speed to lead), and building trust with first-time homebuyers.

Format the response beautifully as HTML with:
- Subheadings styled as <h3 style='color: #4f46e5; margin-top: 15px; margin-bottom: 8px;'>...</h3>
- Paragraphs in <p style='line-height: 1.6; margin-bottom: 10px;'>...</p>
- Recommendations in structured bullet lists <ul style='margin-left: 20px; margin-bottom: 10px;'><li style='margin-bottom: 5px;'>...</li></ul>
Do not wrap in a markdown code block. Simply output the raw HTML.";

// Call Google Generative AI API (Gemini) with retry logic
$url = 'https://generativelanguage.googleapis.com/v1/models/' . $availableModel . ':generateContent?key=' . $apiKey;

$headers = array(
    'Content-Type: application/json'
);

$postData = array(
    'contents' => array(
        array(
            'parts' => array(
                array(
                    'text' => $prompt
                )
            )
        )
    ),
    'generationConfig' => array(
        'temperature' => 0.7,
        'maxOutputTokens' => 1500
    )
);

// Retry logic for handling 503 errors
$maxRetries = 3;
$retryDelay = 1;
$response = null;
$httpCode = 0;
$lastError = '';

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    if ($attempt > 0) {
        sleep($retryDelay);
        $retryDelay *= 2;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $lastError = "Connection error: " . $curlError;
        continue;
    }

    if ($httpCode !== 503) {
        break;
    }
    
    $lastError = "Service temporarily unavailable (503)";
}

if ($curlError || $httpCode !== 200) {
    // FALLBACK: Provide intelligent insights from database analysis instead of API
    $conversionRate = ($stats['total_leads'] > 0) ? round(($stats['total_closed'] / $stats['total_leads']) * 100, 1) : 0;
    
    $insights = "<h3 style='color: #4f46e5; margin-bottom: 10px;'>📈 System Performance Analytics (API Fallback)</h3>";
    $insights .= "<p style='line-height: 1.6; margin-bottom: 10px;'>The Campaign Optimizer service is currently offline or unreachable. We have generated this campaign performance report directly from your database logs.</p>";
    
    $insights .= "<h4 style='color: #1e3a5f; margin-top: 15px; margin-bottom: 5px;'>1. Channel Performance Report</h4>";
    $insights .= "<ul style='margin-left: 20px; margin-bottom: 10px;'>";
    foreach ($sources as $src) {
        $srcName = !empty($src['source']) ? htmlspecialchars($src['source']) : 'Direct / Organic';
        $conv = $src['count'] > 0 ? round(($src['closed_deals'] / $src['count']) * 100, 1) : 0;
        $insights .= "<li style='margin-bottom: 5px;'><strong>Campaign [{$srcName}]:</strong> Reach of <strong>{$src['count']}</strong> leads, resulting in <strong>{$src['closed_deals']}</strong> closed deals (Conversion: <strong>{$conv}%</strong>) generating <strong>₱" . number_format($src['commissions'], 2) . "</strong> in commissions.</li>";
    }
    $insights .= "</ul>";
    
    $insights .= "<h4 style='color: #1e3a5f; margin-top: 15px; margin-bottom: 5px;'>2. Lead Temperature Analysis</h4>";
    $insights .= "<ul style='margin-left: 20px; margin-bottom: 10px;'>";
    foreach ($temps as $t) {
        $tempName = !empty($t['temperature']) ? htmlspecialchars($t['temperature']) : 'Unassigned';
        $insights .= "<li style='margin-bottom: 5px;'><strong>{$tempName} Leads:</strong> <strong>{$t['count']}</strong> total leads, with <strong>{$t['closed_deals']}</strong> closed. " . 
                     ($tempName == 'Hot' ? "Prioritize following up with these immediately as they have the highest conversion potential." : "") . 
                     ($tempName == 'Warm' ? "Nurture these leads with secondary walkthrough videos and loan guides." : "") . "</li>";
    }
    $insights .= "</ul>";

    $insights .= "<h4 style='color: #1e3a5f; margin-top: 15px; margin-bottom: 5px;'>3. Essential Real Estate Recommendations</h4>";
    $insights .= "<ul style='margin-left: 20px; margin-bottom: 10px;'>";
    $insights .= "<li style='margin-bottom: 5px;'><strong>Speed to Lead:</strong> Call internet leads (Facebook/Google) within 5 minutes of inquiry. The conversion drops by 391% if called after 30 minutes!</li>";
    $insights .= "<li style='margin-bottom: 5px;'><strong>Taglish Closing Script:</strong> Kapag kumakausap ng kliyente, simulan sa pag-alam ng kanilang 'Why' (pamilya, investment, etc.) bago ipakita ang bahay. Mas madaling ibenta ang solusyon sa kanilang pangarap.</li>";
    $insights .= "<li style='margin-bottom: 5px;'><strong>Ad Targeting:</strong> Focus marketing budgets on top-performing properties like <strong>" . (!empty($properties[0]['project_model']) ? htmlspecialchars($properties[0]['project_model']) : 'active listings') . "</strong>.</li>";
    $insights .= "</ul>";
    
    echo json_encode(['success' => true, 'insights' => $insights]);
    exit;
}

$apiResponse = json_decode($response, true);
$insightText = '';

if (isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
    $insightText = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid API response format']);
    exit;
}

$insights = htmlspecialchars_decode($insightText);

echo json_encode(['success' => true, 'insights' => $insights]);
?>
