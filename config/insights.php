<?php
/**
 * Smart Insights Engine
 * ----------------------
 * A lightweight, self-contained analytics layer that turns raw order/
 * reservation/menu data into plain-English business insights, without
 * needing any external AI API or network call — everything runs locally
 * against the MySQL data using real statistical techniques:
 *
 *   - Period-over-period trend comparison (% change)
 *   - Linear regression for short-term revenue forecasting
 *   - Z-score based anomaly detection (unusually high/low days)
 *   - Peak-time / top-performer detection
 *
 * This is intentionally built as a pluggable layer: generate_ai_insights()
 * is the single entry point the dashboard calls. If a real LLM API key
 * (OpenAI/Anthropic/etc.) is ever added to config, this function is the
 * natural place to additionally send the computed metrics to that API
 * for a richer natural-language summary — the statistical groundwork
 * below would still be what feeds it.
 */

/**
 * % change between two numbers, safe against divide-by-zero.
 */
function insight_percent_change(float $current, float $previous): ?float
{
    if ($previous == 0.0) {
        return $current > 0 ? 100.0 : null;
    }
    return (($current - $previous) / $previous) * 100;
}

/**
 * Revenue + order count for a period, plus % change vs the equal-length
 * period immediately before it.
 */
function insight_period_comparison($conn, int $days = 7): array
{
    $current = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(total_price),0) AS revenue, COUNT(*) AS orders
        FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    "));
    $previous = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(total_price),0) AS revenue, COUNT(*) AS orders
        FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($days * 2) . " DAY)
          AND created_at < DATE_SUB(CURDATE(), INTERVAL $days DAY)
    "));

    return [
        'revenue_current'  => (float) $current['revenue'],
        'revenue_previous' => (float) $previous['revenue'],
        'revenue_change'   => insight_percent_change((float) $current['revenue'], (float) $previous['revenue']),
        'orders_current'   => (int) $current['orders'],
        'orders_previous'  => (int) $previous['orders'],
        'orders_change'    => insight_percent_change((float) $current['orders'], (float) $previous['orders']),
    ];
}

/**
 * Simple linear regression (least squares) over the last $historyDays of
 * daily revenue, projected forward $forecastDays. This is a real,
 * standard forecasting technique — not a random guess — appropriate for
 * short-horizon projections on reasonably stable small-business data.
 */
function insight_revenue_forecast($conn, int $historyDays = 14, int $forecastDays = 7): array
{
    $rows = [];
    $res = mysqli_query($conn, "
        SELECT DATE(created_at) AS d, SUM(total_price) AS revenue
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $historyDays DAY) AND status != 'Cancelled'
        GROUP BY d ORDER BY d ASC
    ");
    while ($row = mysqli_fetch_assoc($res)) { $rows[] = $row; }

    $n = count($rows);
    if ($n < 3) {
        return ['forecast' => null, 'daily_avg' => null, 'confidence' => 'low', 'points' => $rows];
    }

    // x = day index (0,1,2...), y = revenue
    $sumX = $sumY = $sumXY = $sumXX = 0.0;
    foreach ($rows as $i => $row) {
        $x = $i; $y = (float) $row['revenue'];
        $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
    }
    $denominator = ($n * $sumXX - $sumX * $sumX);
    $slope = $denominator != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;
    $intercept = ($sumY - $slope * $sumX) / $n;

    $forecastTotal = 0.0;
    for ($i = 0; $i < $forecastDays; $i++) {
        $x = $n + $i;
        $predicted = max(0, $intercept + $slope * $x);
        $forecastTotal += $predicted;
    }

    $avgDaily = $sumY / $n;
    // Confidence heuristic: more data points + lower relative volatility = higher confidence
    $variance = 0.0;
    foreach ($rows as $row) { $variance += pow(((float) $row['revenue']) - $avgDaily, 2); }
    $stdDev = sqrt($variance / $n);
    $coefficientOfVariation = $avgDaily > 0 ? $stdDev / $avgDaily : 1;
    $confidence = $n >= 10 && $coefficientOfVariation < 0.6 ? 'high' : ($n >= 5 ? 'medium' : 'low');

    return [
        'forecast'   => round($forecastTotal, 2),
        'daily_avg'  => round($avgDaily, 2),
        'trend'      => $slope > 0.5 ? 'up' : ($slope < -0.5 ? 'down' : 'flat'),
        'confidence' => $confidence,
        'points'     => $rows,
    ];
}

/**
 * Z-score anomaly detection over recent daily revenue: flags any day
 * that's a statistical outlier (>1.5 standard deviations from the mean)
 * — a real, standard technique for "something unusual happened" alerts.
 */
function insight_detect_anomalies($conn, int $days = 14): array
{
    $rows = [];
    $res = mysqli_query($conn, "
        SELECT DATE(created_at) AS d, SUM(total_price) AS revenue
        FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY) AND status != 'Cancelled'
        GROUP BY d ORDER BY d ASC
    ");
    while ($row = mysqli_fetch_assoc($res)) { $rows[] = $row; }

    $n = count($rows);
    if ($n < 4) return [];

    $values = array_map(fn($r) => (float) $r['revenue'], $rows);
    $mean = array_sum($values) / $n;
    $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / $n;
    $stdDev = sqrt($variance);
    if ($stdDev == 0) return [];

    $anomalies = [];
    foreach ($rows as $row) {
        $z = ((float) $row['revenue'] - $mean) / $stdDev;
        if (abs($z) >= 1.5) {
            $anomalies[] = ['date' => $row['d'], 'revenue' => (float) $row['revenue'], 'type' => $z > 0 ? 'spike' : 'drop', 'z' => round($z, 2)];
        }
    }
    return $anomalies;
}

/**
 * Generate the full list of natural-language insight cards for the dashboard.
 * Each insight: ['type' => success|warning|info|danger, 'icon' => bootstrap-icon class, 'text' => string]
 */
function generate_ai_insights($conn): array
{
    $insights = [];

    // 1. Weekly revenue trend
    $week = insight_period_comparison($conn, 7);
    if ($week['revenue_change'] !== null) {
        if ($week['revenue_change'] >= 5) {
            $insights[] = ['type' => 'success', 'icon' => 'bi-graph-up-arrow', 'text' => "Revenue is up " . round($week['revenue_change'], 1) . "% this week compared to last week ($" . number_format($week['revenue_current'], 2) . " vs $" . number_format($week['revenue_previous'], 2) . ")."];
        } elseif ($week['revenue_change'] <= -5) {
            $insights[] = ['type' => 'warning', 'icon' => 'bi-graph-down-arrow', 'text' => "Revenue is down " . round(abs($week['revenue_change']), 1) . "% this week compared to last week. Worth a look."];
        } else {
            $insights[] = ['type' => 'info', 'icon' => 'bi-dash-circle', 'text' => "Revenue is holding steady this week (" . ($week['revenue_change'] >= 0 ? '+' : '') . round($week['revenue_change'], 1) . "% vs last week)."];
        }
    }

    // 2. Revenue forecast
    $forecast = insight_revenue_forecast($conn, 14, 7);
    if ($forecast['forecast'] !== null) {
        $trendWord = $forecast['trend'] === 'up' ? 'growing' : ($forecast['trend'] === 'down' ? 'declining' : 'stable');
        $insights[] = ['type' => 'info', 'icon' => 'bi-cpu', 'text' => "Based on the last 14 days (" . $trendWord . " trend), projected revenue for the next 7 days is ~$" . number_format($forecast['forecast'], 2) . " (" . $forecast['confidence'] . " confidence)."];
    }

    // 3. Anomalies
    $anomalies = insight_detect_anomalies($conn, 14);
    foreach (array_slice($anomalies, -2) as $a) {
        if ($a['type'] === 'spike') {
            $insights[] = ['type' => 'success', 'icon' => 'bi-lightning-charge', 'text' => "Unusual spike detected on " . date('M d', strtotime($a['date'])) . " — revenue was significantly above your recent average."];
        } else {
            $insights[] = ['type' => 'warning', 'icon' => 'bi-exclamation-triangle', 'text' => "Unusually low revenue on " . date('M d', strtotime($a['date'])) . " — worth checking what happened that day."];
        }
    }

    // 4. Top selling item
    $top = mysqli_fetch_assoc(mysqli_query($conn, "SELECT title, SUM(quantity) qty FROM order_items GROUP BY title ORDER BY qty DESC LIMIT 1"));
    if ($top) {
        $insights[] = ['type' => 'success', 'icon' => 'bi-star', 'text' => "\"{$top['title']}\" is your best-selling item with {$top['qty']} units sold. Consider featuring it more prominently."];
    }

    // 5. Underperforming category (lowest revenue share among categories with at least one sale)
    $catRes = mysqli_query($conn, "
        SELECT c.title, SUM(oi.price * oi.quantity) AS total
        FROM order_items oi JOIN menu_items mi ON mi.id = oi.menu_item_id JOIN categories c ON c.id = mi.category_id
        GROUP BY c.title ORDER BY total ASC LIMIT 1
    ");
    $lowCat = mysqli_fetch_assoc($catRes);
    if ($lowCat) {
        $insights[] = ['type' => 'info', 'icon' => 'bi-lightbulb', 'text' => "\"{$lowCat['title']}\" has the lowest sales among your categories. A promotion or menu refresh could help."];
    }

    // 6. Pending operational load
    $pendingOrders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='Pending'"))['c'];
    $pendingRes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE status='Pending'"))['c'];
    if ($pendingOrders > 5) {
        $insights[] = ['type' => 'warning', 'icon' => 'bi-hourglass-split', 'text' => "$pendingOrders orders are still Pending. Consider confirming them soon to avoid delays."];
    }
    if ($pendingRes > 5) {
        $insights[] = ['type' => 'warning', 'icon' => 'bi-calendar-x', 'text' => "$pendingRes reservations are awaiting confirmation."];
    }

    // 7. Peak order hour (helps with staffing decisions)
    $peakRes = mysqli_query($conn, "SELECT HOUR(created_at) AS hr, COUNT(*) c FROM orders GROUP BY hr ORDER BY c DESC LIMIT 1");
    $peak = mysqli_fetch_assoc($peakRes);
    if ($peak && $peak['c'] > 0) {
        $hour12 = date('g A', strtotime($peak['hr'] . ':00'));
        $insights[] = ['type' => 'info', 'icon' => 'bi-clock-history', 'text' => "Your busiest ordering hour is around $hour12. Consider staffing up around that time."];
    }

    if (empty($insights)) {
        $insights[] = ['type' => 'info', 'icon' => 'bi-info-circle', 'text' => 'Not enough order history yet to generate insights. Check back once you have more orders.'];
    }

    return $insights;
}
