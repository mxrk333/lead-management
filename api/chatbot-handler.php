<?php
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rag-utils.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = strtolower($input['message'] ?? '');
$user_id = $input['user_id'] ?? $_SESSION['user_id'];
$user_role = $input['user_role'] ?? 'agent';

$connection = getDbConnection();

if (!$connection) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}


$response = '';

// Helper to detect month and year from message
function detectMonthAndYear($message)
{
    $months = [
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'sept' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12
    ];

    $detected_month = null;
    foreach ($months as $name => $num) {
        if (preg_match('/\b' . preg_quote($name, '/') . '\b/i', $message)) {
            $detected_month = $num;
            break;
        }
    }

    $detected_year = null;
    if (preg_match('/\b(20\d{2})\b/', $message, $matches)) {
        $detected_year = intval($matches[1]);
    }

    return [
        'month' => $detected_month,
        'year' => $detected_year
    ];
}

$detected = detectMonthAndYear($message);
$hasMonth = $detected['month'] !== null;
$targetMonth = $detected['month'] ?? intval(date('n'));
$targetYear = $detected['year'] ?? intval(date('Y'));
$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];
$targetMonthName = $monthNames[$targetMonth] ?? date('F');

// =====================
// KEYWORD MATCHING
// =====================

// TOP PERFORMING AGENT
if (strpos($message, 'top') !== false && (strpos($message, 'agent') !== false || strpos($message, 'performer') !== false)) {
    if ($hasMonth) {
        $query = "SELECT u.name, COUNT(l.id) as total_leads,
                         SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                         SUM(l.expected_commission) as total_commission
                  FROM users u
                  LEFT JOIN leads l ON u.id = l.user_id AND YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
                  WHERE u.role = 'agent'
                  GROUP BY u.id
                  ORDER BY closed_deals DESC LIMIT 5";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('ii', $targetYear, $targetMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $agents = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $timeLabel = " in " . $targetMonthName . " " . $targetYear;
    } else {
        $query = "SELECT u.name, COUNT(l.id) as total_leads,
                         SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                         SUM(l.expected_commission) as total_commission
                  FROM users u
                  LEFT JOIN leads l ON u.id = l.user_id
                  WHERE u.role = 'agent'
                  GROUP BY u.id
                  ORDER BY closed_deals DESC LIMIT 5";
        $result = mysqli_query($connection, $query);
        $agents = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeLabel = " (All-Time)";
    }

    if (!empty($agents)) {
        $response = "<strong>🌟 Top 5 Performing Agents" . $timeLabel . ":</strong><br>";
        $count = 1;
        foreach ($agents as $agent) {
            $conversionRate = $agent['total_leads'] > 0 ? round(($agent['closed_deals'] / $agent['total_leads']) * 100) : 0;
            $response .= "$count. <strong>" . htmlspecialchars($agent['name']) . "</strong><br>";
            $response .= "   • Closed Deals: {$agent['closed_deals']}<br>";
            $response .= "   • Commission: ₱" . number_format($agent['total_commission'] ?? 0, 2) . "<br>";
            $response .= "   • Conversion: {$conversionRate}%<br><br>";
            $count++;
        }
    } else {
        $response = "No agent data available yet.";
    }
}

// TEAM PERFORMANCE
else if (strpos($message, 'team') !== false && (strpos($message, 'perform') !== false || strpos($message, 'summary') !== false)) {
    if ($hasMonth) {
        $query = "SELECT t.name as team_name, COUNT(DISTINCT u.id) as agents,
                         COUNT(l.id) as total_leads,
                         SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                         SUM(l.expected_commission) as total_commission
                  FROM teams t
                  LEFT JOIN users u ON t.id = u.team_id
                  LEFT JOIN leads l ON u.id = l.user_id AND YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
                  GROUP BY t.id
                  ORDER BY closed_deals DESC LIMIT 10";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('ii', $targetYear, $targetMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $teams = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $timeLabel = " for " . $targetMonthName . " " . $targetYear;
    } else {
        $query = "SELECT t.name as team_name, COUNT(DISTINCT u.id) as agents,
                         COUNT(l.id) as total_leads,
                         SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                         SUM(l.expected_commission) as total_commission
                  FROM teams t
                  LEFT JOIN users u ON t.id = u.team_id
                  LEFT JOIN leads l ON u.id = l.user_id
                  GROUP BY t.id
                  ORDER BY closed_deals DESC LIMIT 10";
        $result = mysqli_query($connection, $query);
        $teams = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeLabel = " (All-Time)";
    }

    if (!empty($teams)) {
        $response = "<strong>👥 Team Performance Summary" . $timeLabel . ":</strong><br>";
        foreach ($teams as $team) {
            $conversionRate = $team['total_leads'] > 0 ? round(($team['closed_deals'] / $team['total_leads']) * 100) : 0;
            $response .= "<strong>" . htmlspecialchars($team['team_name'] ?? 'No Team') . "</strong><br>";
            $response .= "   • Agents: {$team['agents']}<br>";
            $response .= "   • Closed Deals: {$team['closed_deals']}<br>";
            $response .= "   • Total Commission: ₱" . number_format($team['total_commission'] ?? 0, 2) . "<br>";
            $response .= "   • Conversion: {$conversionRate}%<br><br>";
        }
    } else {
        $response = "No team data available yet.";
    }
}

// MONTHLY REPORT / MONTH SPECIFIC SUMMARY
else if (
    strpos($message, 'report') !== false ||
    strpos($message, 'monthly') !== false ||
    ((strpos($message, 'summary') !== false || strpos($message, 'overview') !== false) && $hasMonth)
) {
    $stmt = $connection->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
            SUM(l.expected_commission) as total_commission,
            COUNT(DISTINCT CASE WHEN l.temperature = 'Hot' THEN l.id END) as hot_leads,
            COUNT(DISTINCT CASE WHEN l.status = 'Presentation Stage' THEN l.id END) as in_presentation
        FROM leads l
        WHERE YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
    ");
    $stmt->bind_param('ii', $targetYear, $targetMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    $response = "<strong>📊 Monthly Report (" . $targetMonthName . " " . $targetYear . "):</strong><br>";
    $response .= "   • Total Leads: {$stats['total_leads']}<br>";
    $response .= "   • Closed Deals: {$stats['closed_deals']}<br>";
    $response .= "   • Expected Commission: ₱" . number_format($stats['total_commission'] ?? 0, 2) . "<br>";
    $response .= "   • Hot Leads: {$stats['hot_leads']}<br>";
    $response .= "   • In Presentation: {$stats['in_presentation']}<br>";
    $response .= "   • Conversion Rate: " . ($stats['total_leads'] > 0 ? round(($stats['closed_deals'] / $stats['total_leads']) * 100) : 0) . "%<br>";
}

// FACEBOOK LEADS / FB LEADS
else if (strpos($message, 'facebook') !== false || strpos($message, 'fb') !== false || strpos($message, 'google sheet') !== false) {
    $query = "SELECT client_name, city, job_title, relationship_status, lead_quality, ai_summary, recommended_action, created_at 
              FROM leads 
              WHERE source = 'Facebook Ads' OR google_sheet_row_id IS NOT NULL 
              ORDER BY created_at DESC 
              LIMIT 5";
    $result = mysqli_query($connection, $query);
    $fb_leads = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    if (!empty($fb_leads)) {
        $response = "<strong>🎯 Recent Facebook Leads (Google Sheets + Gemini AI):</strong><br><br>";
        foreach ($fb_leads as $lead) {
            $quality = $lead['lead_quality'] ?? 'Medium';
            $badgeColor = $quality === 'High' ? '#10b981' : ($quality === 'Medium' ? '#f59e0b' : '#6b7280');
            $date = date('M j, Y g:i A', strtotime($lead['created_at']));
            
            $response .= "👤 <strong>" . htmlspecialchars($lead['client_name']) . "</strong> ";
            $response .= "<span style='font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 9999px; background-color: {$badgeColor}; color: white; font-weight: bold;'>{$quality} Quality</span><br>";
            
            $demographics = [];
            if (!empty($lead['city'])) $demographics[] = "Location: " . htmlspecialchars($lead['city']);
            if (!empty($lead['job_title'])) $demographics[] = "Job: " . htmlspecialchars($lead['job_title']);
            if (!empty($lead['relationship_status'])) $demographics[] = "Status: " . htmlspecialchars($lead['relationship_status']);
            if (!empty($demographics)) {
                $response .= "   • 📋 " . implode(' | ', $demographics) . "<br>";
            }
            
            if (!empty($lead['ai_summary'])) {
                $response .= "   • 🧠 <strong>AI Summary:</strong> <em>\"" . htmlspecialchars($lead['ai_summary']) . "\"</em><br>";
            }
            if (!empty($lead['recommended_action'])) {
                $response .= "   • 💡 <strong>Action:</strong> " . htmlspecialchars($lead['recommended_action']) . "<br>";
            }
            $response .= "   • 📅 <em>Imported: {$date}</em><br><br>";
        }
    } else {
        $response = "No Facebook leads found in the database. Run the importer script to fetch new leads.";
    }
}

// SYSTEM HELP - HOW TO USE
else if (strpos($message, 'how') !== false || strpos($message, 'help') !== false || strpos($message, 'guide') !== false) {
    $ragResult = generateRagResponse($input['message'], $connection, $user_id);
    if ($ragResult['success']) {
        $response = $ragResult['response'];
    } else {
        $response = "<strong>❓ How to Use This System:</strong><br><br>";
        $response .= "<strong>📝 Adding Leads:</strong><br>";
        $response .= "Click 'Add Lead' → Fill in client details → Select temperature (Hot/Warm/Cold) → Set status → Save<br><br>";

        $response .= "<strong>👁️ Viewing Leads:</strong><br>";
        $response .= "Go to 'Leads' → Use filters by status, temperature, date → Click on any lead for details<br><br>";

        $response .= "<strong>💰 Tracking Downpayment:</strong><br>";
        $response .= "Open a lead → Go to 'Downpayment' tab → Add reservation date and payment schedule<br><br>";

        $response .= "<strong>📋 Creating Memos:</strong><br>";
        $response .= "Click 'Memos' → New memo → Add title, content → Choose visibility → Post<br><br>";

        $response .= "<strong>📊 Reports:</strong><br>";
        $response .= "Visit 'Reports' → Select date range and filters → Generate custom reports<br><br>";
    }
}

// LEAD STATUS INFO
else if (strpos($message, 'status') !== false || strpos($message, 'stage') !== false) {
    $response = "<strong>📊 Lead Status Stages:</strong><br>";
    $response .= "🔵 <strong>Inquiry</strong> - Initial contact<br>";
    $response .= "🟡 <strong>Presentation Stage</strong> - Product shown<br>";
    $response .= "🟠 <strong>Negotiation</strong> - Terms discussed<br>";
    $response .= "🟢 <strong>Closed Deal</strong> - Transaction complete<br>";
    $response .= "❌ <strong>Lost</strong> - Opportunity lost<br>";
    $response .= "🏠 <strong>Downpayment Stage</strong> - DP payment process<br>";
    $response .= "🏦 <strong>Housing Loan Application</strong> - Bank processing<br>";
    $response .= "✅ <strong>House Turnover</strong> - Keys handed over<br>";
}

// TEMPERATURE LEVELS
else if (strpos($message, 'temperature') !== false) {
    $response = "<strong>🌡️ Lead Temperature:</strong><br>";
    $response .= "🔥 <strong>Hot</strong> - High interest, ready to buy soon<br>";
    $response .= "🌤️ <strong>Warm</strong> - Interested but needs more time<br>";
    $response .= "❄️ <strong>Cold</strong> - Low interest, long-term prospect<br>";
}

// GENERAL SYSTEM STATS
else if (strpos($message, 'overview') !== false || strpos($message, 'summary') !== false || strpos($message, 'statistics') !== false) {
    $query = "SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN status = 'Lost' THEN 1 ELSE 0 END) as lost,
                SUM(expected_commission) as total_commission,
                COUNT(DISTINCT user_id) as total_agents
              FROM leads";

    $result = mysqli_query($connection, $query);
    $stats = mysqli_fetch_assoc($result);

    $response = "<strong>📈 System Overview:</strong><br>";
    $response .= "   • Total Leads: {$stats['total_leads']}<br>";
    $response .= "   • Closed Deals: {$stats['closed']}<br>";
    $response .= "   • Lost Deals: {$stats['lost']}<br>";
    $response .= "   • Total Commissions: ₱" . number_format($stats['total_commission'] ?? 0, 2) . "<br>";
    $response .= "   • Active Agents: {$stats['total_agents']}<br>";
    $response .= "   • Success Rate: " . ($stats['total_leads'] > 0 ? round(($stats['closed'] / $stats['total_leads']) * 100) : 0) . "%<br>";
}

// DEFAULT - RAG-POWERED ASSISTANCE
else {
    $ragResult = generateRagResponse($input['message'], $connection, $user_id);
    if ($ragResult['success']) {
        $response = $ragResult['response'];
    } else {
        $response = "<strong>💡 SPARC BOT is here to help.</strong><br><br>";
        $response .= "I currently respond to specific system questions and reports. You can ask me about:<br>";
        $response .= "✅ <strong>Performance metrics</strong> - \"Who is top performer?\"<br>";
        $response .= "✅ <strong>Team stats</strong> - \"Show team performance\"<br>";
        $response .= "✅ <strong>Monthly reports</strong> - \"Create monthly report\"<br>";
        $response .= "✅ <strong>System help</strong> - \"How do I add a lead?\"<br>";
        $response .= "✅ <strong>Lead info</strong> - \"What are lead statuses?\"<br><br>";
        $response .= "If you ask something outside those areas, I’ll show this message instead of guessing.\n";
        $response .= "<strong>💬 Your question:</strong> \"" . htmlspecialchars(substr($input['message'], 0, 50)) . (strlen($input['message']) > 50 ? "...\"" : "\"") . "<br><br>";
        $response .= "Try one of the quick suggestions or rephrase your question around performance, reports, team stats, or system help.";
    }
}

$connection->close();

echo json_encode([
    'success' => true,
    'response' => $response
]);
?>