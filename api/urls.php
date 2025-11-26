<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../services/PcComponentesScraper.php';
require_once '../services/PriceScraper.php';

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

// GET - Obtener todas las URLs del usuario
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT
            mu.*,
            GROUP_CONCAT(
                CONCAT(nm.method, ':', nm.contact_info, ':', nm.is_active)
                SEPARATOR '||'
            ) as notifications
        FROM monitored_urls mu
        LEFT JOIN notification_methods nm ON mu.id = nm.url_id
        WHERE mu.user_id = ?
        GROUP BY mu.id
        ORDER BY mu.created_at DESC
    ");
    $stmt->execute([$userId]);
    $urls = $stmt->fetchAll();

    // Procesar notificaciones
    foreach ($urls as &$url) {
        $notifs = [];
        if ($url['notifications']) {
            $notifArray = explode('||', $url['notifications']);
            foreach ($notifArray as $notif) {
                list($method, $contact, $active) = explode(':', $notif);
                $notifs[] = [
                    'method' => $method,
                    'contact_info' => $contact,
                    'is_active' => (bool)$active
                ];
            }
        }
        $url['notifications'] = $notifs;
    }

    sendResponse(true, 'URLs obtenidas', $urls);
}

// POST - Crear nueva URL monitorizad
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $url = trim($input['url'] ?? '');
    $productName = trim($input['product_name'] ?? '');
    $targetPrice = floatval($input['target_price'] ?? 0);
    $notifications = $input['notifications'] ?? [];

    // Validaciones
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        sendResponse(false, 'URL inválida');
    }

    if ($targetPrice <= 0) {
        sendResponse(false, 'El precio objetivo debe ser mayor a 0');
    }

    if (empty($notifications)) {
        sendResponse(false, 'Debe seleccionar al menos un método de notificación');
    }

    try {
        $db->beginTransaction();

        // Insertar URL
        $stmt = $db->prepare("
            INSERT INTO monitored_urls (user_id, url, product_name, target_price, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$userId, $url, $productName, $targetPrice]);
        $urlId = $db->lastInsertId();
// Hacer scraping automático si es PcComponentes        if (PcComponentesScraper::isPcComponentesURL()) {             = new PcComponentesScraper(, );            ->extractProductInfo();        }

        // Insertar métodos de notificación
        $stmt = $db->prepare("
            INSERT INTO notification_methods (url_id, method, contact_info)
            VALUES (?, ?, ?)
        ");

        foreach ($notifications as $notif) {
            if (!empty($notif['method']) && !empty($notif['contact_info'])) {
                $stmt->execute([$urlId, $notif['method'], $notif['contact_info']]);
            }
        }

        $db->commit();
        sendResponse(true, 'URL agregada exitosamente', ['id' => $urlId]);

    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(false, 'Error al agregar URL: ' . $e->getMessage());
    }
}

// PUT - Actualizar URL
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    $urlId = intval($input['id'] ?? 0);
    $url = trim($input['url'] ?? '');
    $productName = trim($input['product_name'] ?? '');
    $targetPrice = floatval($input['target_price'] ?? 0);
    $status = $input['status'] ?? 'active';
    $notifications = $input['notifications'] ?? [];

    // Verificar que la URL pertenece al usuario
    $stmt = $db->prepare("SELECT id FROM monitored_urls WHERE id = ? AND user_id = ?");
    $stmt->execute([$urlId, $userId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'URL no encontrada');
    }

    try {
        $db->beginTransaction();

        // Actualizar URL
        $stmt = $db->prepare("
            UPDATE monitored_urls
            SET url = ?, product_name = ?, target_price = ?, status = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$url, $productName, $targetPrice, $status, $urlId, $userId]);

        // Eliminar notificaciones existentes
        $stmt = $db->prepare("DELETE FROM notification_methods WHERE url_id = ?");
        $stmt->execute([$urlId]);

        // Insertar nuevas notificaciones
        $stmt = $db->prepare("
            INSERT INTO notification_methods (url_id, method, contact_info)
            VALUES (?, ?, ?)
        ");

        foreach ($notifications as $notif) {
            if (!empty($notif['method']) && !empty($notif['contact_info'])) {
                $stmt->execute([$urlId, $notif['method'], $notif['contact_info']]);
            }
        }

        $db->commit();
        sendResponse(true, 'URL actualizada exitosamente');

    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(false, 'Error al actualizar URL: ' . $e->getMessage());
    }
}

// DELETE - Eliminar URL
if ($method === 'DELETE') {
    $urlId = intval($_GET['id'] ?? 0);

    // Verificar que la URL pertenece al usuario
    $stmt = $db->prepare("SELECT id FROM monitored_urls WHERE id = ? AND user_id = ?");
    $stmt->execute([$urlId, $userId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'URL no encontrada');
    }

    $stmt = $db->prepare("DELETE FROM monitored_urls WHERE id = ? AND user_id = ?");
    try {
        $stmt->execute([$urlId, $userId]);
        sendResponse(true, 'URL eliminada exitosamente');
    } catch (PDOException $e) {
        sendResponse(false, 'Error al eliminar URL');
    }
}

sendResponse(false, 'Método no permitido');
?>
