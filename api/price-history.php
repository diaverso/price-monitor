<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// GET - Obtener historial de precios de una URL
if ($method === 'GET') {
    $urlId = intval($_GET['url_id'] ?? 0);

    if (!$urlId) {
        sendResponse(false, 'ID de URL requerido');
    }

    // Verificar que la URL pertenece al usuario
    $stmt = $db->prepare("
        SELECT mu.id, mu.product_name, mu.url, mu.current_price, mu.target_price
        FROM monitored_urls mu
        WHERE mu.id = ? AND mu.user_id = ?
    ");
    $stmt->execute([$urlId, $userId]);
    $urlInfo = $stmt->fetch();

    if (!$urlInfo) {
        sendResponse(false, 'URL no encontrada');
    }

    // Obtener historial de precios
    $period = $_GET['period'] ?? '30'; // días
    $stmt = $db->prepare("
        SELECT
            price,
            checked_at,
            scraping_method,
            extraction_time_ms
        FROM price_history
        WHERE url_id = ?
        AND checked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY checked_at ASC
    ");
    $stmt->execute([$urlId, $period]);
    $history = $stmt->fetchAll();

    // Calcular estadísticas
    $prices = array_column($history, 'price');
    $stats = [
        'count' => count($prices),
        'min' => count($prices) > 0 ? min($prices) : null,
        'max' => count($prices) > 0 ? max($prices) : null,
        'avg' => count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : null,
        'first' => count($prices) > 0 ? $prices[0] : null,
        'last' => count($prices) > 0 ? end($prices) : null,
    ];

    // Calcular cambio porcentual
    if ($stats['first'] && $stats['last'] && $stats['first'] > 0) {
        $stats['change_percent'] = round((($stats['last'] - $stats['first']) / $stats['first']) * 100, 2);
        $stats['change_absolute'] = round($stats['last'] - $stats['first'], 2);
    } else {
        $stats['change_percent'] = 0;
        $stats['change_absolute'] = 0;
    }

    sendResponse(true, 'Historial obtenido', [
        'url_info' => $urlInfo,
        'history' => $history,
        'stats' => $stats
    ]);
}

sendResponse(false, 'Método no permitido');
?>
