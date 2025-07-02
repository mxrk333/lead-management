<?php

require_once 'dbConfig.php';

function mergeGoogleSheetToDB($pdo, $url)
{
    if (($handle = fopen($url, 'r')) === false)
        return;

    fgetcsv($handle); // skip header

    $stmt = $pdo->prepare("
        INSERT INTO manual_data (datetime, name, team, toggle)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE toggle = VALUES(toggle)
    ");

    while (($row = fgetcsv($handle)) !== false) {
        $datetimeStr = $row[0] ?? '';
        $name = trim($row[1] ?? '');
        $team = trim($row[2] ?? '');
        $toggle = trim($row[3] ?? '');

        $date = DateTime::createFromFormat('n/j/Y H:i:s', $datetimeStr);
        if (!$date)
            continue;

        $datetime = $date->format('Y-m-d H:i:s');

        $stmt->execute([$datetime, $name, $team, $toggle]);
    }

    fclose($handle);
}

function getToggleDataByTeamAndMonth(PDO $pdo)
{
    $query = "
        SELECT 
            team,
            YEAR(datetime) AS year,
            MONTH(datetime) AS month,
            toggle,
            COUNT(*) AS count
        FROM manual_data
        GROUP BY team, year, month, toggle
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $toggle = $row['toggle'];
        $team = $row['team'];
        $year = (int) $row['year'];
        $month = (int) $row['month'];
        $count = (int) $row['count'];

        // Initialize structure if not set
        if (!isset($data[$year][$toggle][$team])) {
            $data[$year][$toggle][$team] = array_fill(1, 12, 0);
        }

        $data[$year][$toggle][$team][$month] = $count;
    }

    return $data;
}


function getToggleDataByTeamPerMonth(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT 
            toggle,
            team,
            YEAR(datetime) AS year,
            MONTH(datetime) AS month,
            COUNT(*) AS count
        FROM manual_data
        GROUP BY toggle, team, year, month
        ORDER BY year, toggle, team, month
    ");

    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $toggle = $row['toggle'];
        $team = $row['team'];
        $year = (int) $row['year'];
        $month = (int) $row['month'];
        $count = (int) $row['count'];

        if (!isset($data[$year][$toggle][$team])) {
            $data[$year][$toggle][$team] = array_fill(1, 12, 0);
        }

        $data[$year][$toggle][$team][$month] = $count;
    }

    return $data;
}

function getTeamMonthlyData(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT team, DATE_FORMAT(datetime, '%Y-%m') AS ym, COUNT(*) AS total
            FROM manual_data
            GROUP BY team, DATE_FORMAT(datetime, '%Y-%m')
            ORDER BY ym, team
        ");

        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Structure: $monthlyData[month][team] = total
        $monthlyData = [];

        foreach ($rawData as $row) {
            $month = $row['ym'];
            $team = $row['team'];
            $count = (int) $row['total'];

            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = [];
            }

            $monthlyData[$month][$team] = $count;
        }

        return $monthlyData;

    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function getFilteredManualData(PDO $pdo, array $filters = []): array
{
    try {
        // Get filter parameters with defaults
        $name = $filters['name'] ?? '';
        $team = $filters['team'] ?? '';
        $toggle = $filters['toggle'] ?? '';
        $month = $filters['month'] ?? '';

        // Build the query with filters
        $query = "SELECT * FROM manual_data WHERE 1=1";
        $params = [];

        if (!empty($name)) {
            $query .= " AND name LIKE ?";
            $params[] = "%$name%";
        }

        if (!empty($team)) {
            $query .= " AND team = ?";
            $params[] = $team;
        }

        if ($toggle !== '') {
            if ($toggle === 'null') {
                $query .= " AND (toggle IS NULL OR toggle = '')";
            } else {
                $query .= " AND toggle = ?";
                $params[] = $toggle;
            }
        }

        if (!empty($month)) {
            $query .= " AND MONTH(datetime) = ?";
            $params[] = ltrim($month, '0'); // Remove leading zero for MySQL MONTH()
        }

        $query .= " ORDER BY datetime DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

function getAllTeams(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT DISTINCT team FROM manual_data ORDER BY team ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        throw new Exception("Failed to get teams: " . $e->getMessage());
    }
}

function getMondayWeekOfMonth(DateTime $date)
{
    // Get the first day of the month
    $firstOfMonth = new DateTime($date->format('Y-m-01'));

    // Find the Monday on or before the first of the month
    $firstMonday = clone $firstOfMonth;
    if ($firstMonday->format('w') != 1) { // if not Monday
        $firstMonday->modify('last monday');
    }

    // Difference in days from first Monday to current date
    $daysDiff = $firstMonday->diff($date)->days;

    // Week number = full weeks passed + 1
    $weekNumber = intval(floor($daysDiff / 7)) + 1;

    return "Week $weekNumber";
}