<?php

function getHelpDocuments() {
    return [
        [
            'title' => 'Adding a New Lead',
            'source' => 'Help Center',
            'content' => 'Navigate to the Leads page and click "Add New Lead". Fill in the required fields: Client Name, Phone, Email, Lead Source, Temperature, Status, Developer, Project Model, and Price.',
        ],
        [
            'title' => 'Updating Lead Status and Temperature',
            'source' => 'Help Center',
            'content' => 'Go to the lead details page and click "Edit Lead". Update the status (Inquiry, Presentation Stage, Negotiation, Closed Deal, Lost, Downpayment Stage, Housing Loan Application, House Turnover) and temperature (Hot, Warm, Cold).',
        ],
        [
            'title' => 'User Roles and Permissions',
            'source' => 'Help Center',
            'content' => 'Admin has full system access and user management. Manager can manage teams, view reports, and see all leads. Supervisor has team oversight and reporting access. Agent can manage their own leads and basic functions.',
        ],
        [
            'title' => 'Downpayment Stage Tracking',
            'source' => 'Help Center',
            'content' => 'Leads with Downpayment Stage status appear in the DP Stage section. Track payment schedules, amounts, and completion status. View both in-progress and completed downpayments.',
        ],
        [
            'title' => 'Notifications',
            'source' => 'Help Center',
            'content' => 'You receive notifications for activities on your leads, team activities if you are a manager or supervisor, and new memos. Click the bell icon to view recent activities.',
        ],
        [
            'title' => 'Searching and Filtering Leads',
            'source' => 'Help Center',
            'content' => 'Use the search bar in the header to search by client name, phone, or email. On the Leads page, filter by status, temperature, source, developer, or date range.',
        ],
        [
            'title' => 'Lead Contact Privacy',
            'source' => 'Help Center',
            'content' => 'Full contact details are only visible to the lead owner. Other team members see masked information unless they have manager or admin privileges.',
        ],
        [
            'title' => 'Reset Password',
            'source' => 'Help Center',
            'content' => 'Contact your system administrator or use the Forgot Password link on the login page. Admins can reset passwords through the Users management section.',
        ],
        [
            'title' => 'Getting Started',
            'source' => 'User Guide',
            'content' => 'System login and navigation, dashboard overview, profile setup and preferences, notification settings.',
        ],
        [
            'title' => 'Lead Management Workflow',
            'source' => 'User Guide',
            'content' => 'Adding new leads with all required fields, lead temperature system, status progression workflow, editing lead information, lead activity tracking and history, adding activities and notes.',
        ],
        [
            'title' => 'Lead Sources & Tracking',
            'source' => 'User Guide',
            'content' => 'Lead sources include Facebook, Google Ads, referrals, and more. Select developer and project model, format price correctly, and review lead modification history.',
        ],
        [
            'title' => 'Team Collaboration',
            'source' => 'User Guide',
            'content' => 'Understand user roles and permissions, team lead visibility and privacy, manager oversight features, memo system for announcements.',
        ],
        [
            'title' => 'Reports & Analytics',
            'source' => 'User Guide',
            'content' => 'Generate lead reports, track performance analytics, monitor team performance, export and share report data.',
        ],
        [
            'title' => 'System Features',
            'source' => 'User Guide',
            'content' => 'Search and filtering capabilities, notification management, data privacy and security.',
        ],
    ];
}

function getUserTeamId($connection, $user_id) {
    $stmt = $connection->prepare("SELECT team_id FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['team_id'] ?? null;
}

function getVisibleMemos($connection, $user_id) {
    $team_id = getUserTeamId($connection, $user_id);
    $documents = [];

    $query = "SELECT m.title, m.description AS content
              FROM memos m
              WHERE m.visible_to_all = 1
                 OR m.created_by = ?";
    if ($team_id !== null) {
        $query .= " OR m.team_id = ?";
    }
    $query .= " LIMIT 25";

    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return $documents;
    }

    if ($team_id !== null) {
        $stmt->bind_param('ii', $user_id, $team_id);
    } else {
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $documents[] = [
            'title' => $row['title'],
            'source' => 'Internal Memo',
            'content' => $row['content'],
        ];
    }

    $stmt->close();
    return $documents;
}

function sanitizeRagText($text) {
    $clean = strip_tags($text);
    $clean = preg_replace('/\s+/', ' ', $clean);
    return trim($clean);
}

function computeRelevanceScore($query, $text) {
    $query = strtolower($query);
    $words = array_filter(preg_split('/[^a-z0-9]+/', $query), fn($word) => strlen($word) > 2);
    if (empty($words)) {
        return 0;
    }

    $text = strtolower($text);
    $score = 0;
    foreach ($words as $word) {
        $count = substr_count($text, $word);
        $score += $count;
    }

    return $score;
}

function retrieveRelevantDocuments($question, $connection, $user_id) {
    $documents = getHelpDocuments();
    $memoDocs = getVisibleMemos($connection, $user_id);
    $documents = array_merge($documents, $memoDocs);

    $scored = [];
    foreach ($documents as $doc) {
        $content = sanitizeRagText($doc['title'] . ' ' . $doc['content']);
        $score = computeRelevanceScore($question, $content);
        $scored[] = [
            'title' => $doc['title'],
            'source' => $doc['source'],
            'content' => $content,
            'score' => $score,
        ];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    $top = array_filter($scored, fn($doc) => $doc['score'] > 0);
    if (empty($top)) {
        $top = array_slice($scored, 0, 3);
    } else {
        $top = array_slice($top, 0, 4);
    }

    return array_map(fn($doc) => [
        'title' => $doc['title'],
        'source' => $doc['source'],
        'content' => mb_substr($doc['content'], 0, 1100),
    ], $top);
}

function buildRagPrompt($question, $documents) {
    $prompt = "You are SPARC BOT, a smart real estate assistant. You have extensive general knowledge in real estate sales, marketing strategies, client relations, and property selling techniques. You are also capable of conversing fluently in Tagalog or Taglish. If the user asks general real estate questions, client handling advice, or marketing tips (especially if they are new to the industry), use your general expertise to provide a comprehensive, encouraging, and actionable response in their preferred language. If the question is specifically about how to use this lead management system, refer to the internal documentation below to guide the user step-by-step.\n\n";
    $prompt .= "System context (Internal documents/memos):\n";
    foreach ($documents as $index => $doc) {
        $prompt .= "Source " . ($index + 1) . ": " . $doc['title'] . " (" . $doc['source'] . ")\n";
        $prompt .= $doc['content'] . "\n\n";
    }
    $prompt .= "User question: " . $question . "\n";
    $prompt .= "Provide a professional, helpful, and concise response. If the internal documents are relevant, cite them; otherwise, draw from your expert real estate knowledge.\n";
    return $prompt;
}

function callGeminiModel($prompt) {
    $apiKey = 'AQ.Ab8RN6Kw1CoGjGimjFzDeCqvdCfjNmSZjvPNmf2SULH6mBO8jQ';
    $model = 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $apiKey;

    $postData = [
        'contents' => [[
            'parts' => [[
                'text' => $prompt,
            ]],
        ]],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 512,
        ],
    ];

    $headers = ['Content-Type: application/json'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'Connection error: ' . $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => true, 'text' => $data['candidates'][0]['content']['parts'][0]['text']];
    }

    $errorMessage = $data['error']['message'] ?? $data['message'] ?? 'Unknown API error';
    return ['success' => false, 'error' => 'API Error (HTTP ' . $httpCode . '): ' . $errorMessage];
}

function generateRagResponse($question, $connection, $user_id) {
    $documents = retrieveRelevantDocuments($question, $connection, $user_id);
    if (empty($documents)) {
        return [
            'success' => false,
            'error' => 'No relevant internal documents found for this query.',
        ];
    }

    $prompt = buildRagPrompt($question, $documents);
    $result = callGeminiModel($prompt);
    if ($result['success']) {
        return ['success' => true, 'response' => $result['text']];
    }

    $fallback = "<strong>📚 Retrieved documents:</strong><br>";
    foreach ($documents as $doc) {
        $excerpt = mb_substr($doc['content'], 0, 250);
        $excerpt = htmlspecialchars($excerpt);
        $fallback .= "<strong>" . htmlspecialchars($doc['title']) . "</strong> (" . htmlspecialchars($doc['source']) . ")<br>";
        $fallback .= "<em>" . $excerpt . "...</em><br><br>";
    }
    $fallback .= "<strong>Note:</strong> The AI service is unavailable, but these documents are relevant to your question.";

    return ['success' => true, 'response' => $fallback];
}
