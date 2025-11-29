<?php
/**
 * API para Agrupación Manual de Productos
 *
 * Permite al usuario:
 * - Agrupar productos manualmente
 * - Desagrupar productos
 * - Ver grupos existentes
 */

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Database::getInstance()->getConnection();

    if ($method === 'POST') {
        // Agrupar productos manualmente
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['action'])) {
            throw new Exception('Acción no especificada');
        }

        $action = $input['action'];

        if ($action === 'group') {
            // Agrupar múltiples productos
            if (!isset($input['url_ids']) || !is_array($input['url_ids']) || count($input['url_ids']) < 2) {
                throw new Exception('Se requieren al menos 2 productos para agrupar');
            }

            $urlIds = array_map('intval', $input['url_ids']);

            // Verificar que todos los productos pertenecen al usuario
            $placeholders = str_repeat('?,', count($urlIds) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, product_name
                FROM monitored_urls
                WHERE id IN ($placeholders) AND user_id = ?
            ");
            $stmt->execute(array_merge($urlIds, [$userId]));
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($products) !== count($urlIds)) {
                throw new Exception('Algunos productos no fueron encontrados o no te pertenecen');
            }

            // Verificar si alguno ya está en un grupo
            $stmt = $pdo->prepare("
                SELECT monitored_url_id, group_id
                FROM product_group_items
                WHERE monitored_url_id IN ($placeholders)
            ");
            $stmt->execute($urlIds);
            $existingGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $groupId = null;

            if (count($existingGroups) > 0) {
                // Si algún producto ya está en un grupo, usar ese grupo
                $groupId = $existingGroups[0]['group_id'];
                error_log("[ManualGrouping] Usando grupo existente: $groupId");
            } else {
                // Crear nuevo grupo
                $groupName = $input['group_name'] ?? $products[0]['product_name'];
                $brand = $input['brand'] ?? null;
                $model = $input['model'] ?? null;
                $category = $input['category'] ?? 'otros';

                $stmt = $pdo->prepare("
                    INSERT INTO product_groups (name, brand, model, category)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$groupName, $brand, $model, $category]);
                $groupId = $pdo->lastInsertId();

                error_log("[ManualGrouping] Nuevo grupo creado: $groupId - $groupName");
            }

            // Agregar todos los productos al grupo
            $stmt = $pdo->prepare("
                INSERT INTO product_group_items
                (group_id, monitored_url_id, is_primary, auto_matched, similarity_score)
                VALUES (?, ?, ?, 0, 100.00)
                ON DUPLICATE KEY UPDATE
                    auto_matched = 0,
                    similarity_score = 100.00
            ");

            foreach ($urlIds as $index => $urlId) {
                $isPrimary = ($index === 0) ? 1 : 0;
                $stmt->execute([$groupId, $urlId, $isPrimary]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Productos agrupados correctamente',
                'group_id' => $groupId,
                'products_count' => count($urlIds)
            ]);

        } elseif ($action === 'ungroup') {
            // Desagrupar un producto específico
            if (!isset($input['url_id'])) {
                throw new Exception('Se requiere url_id');
            }

            $urlId = intval($input['url_id']);

            // Verificar que el producto pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT id FROM monitored_urls
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$urlId, $userId]);

            if (!$stmt->fetch()) {
                throw new Exception('Producto no encontrado o no te pertenece');
            }

            // Obtener el grupo del producto
            $stmt = $pdo->prepare("
                SELECT group_id FROM product_group_items
                WHERE monitored_url_id = ?
            ");
            $stmt->execute([$urlId]);
            $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$groupInfo) {
                throw new Exception('El producto no está en ningún grupo');
            }

            $groupId = $groupInfo['group_id'];

            // Eliminar del grupo
            $stmt = $pdo->prepare("
                DELETE FROM product_group_items
                WHERE monitored_url_id = ?
            ");
            $stmt->execute([$urlId]);

            // Verificar si el grupo quedó vacío o con un solo producto
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM product_group_items
                WHERE group_id = ?
            ");
            $stmt->execute([$groupId]);
            $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($remaining === 0) {
                // Eliminar el grupo si quedó vacío
                $stmt = $pdo->prepare("DELETE FROM product_groups WHERE id = ?");
                $stmt->execute([$groupId]);
                error_log("[ManualGrouping] Grupo $groupId eliminado (vacío)");
            } elseif ($remaining === 1) {
                // Si solo queda 1 producto, también eliminar el grupo
                $stmt = $pdo->prepare("DELETE FROM product_group_items WHERE group_id = ?");
                $stmt->execute([$groupId]);

                $stmt = $pdo->prepare("DELETE FROM product_groups WHERE id = ?");
                $stmt->execute([$groupId]);
                error_log("[ManualGrouping] Grupo $groupId eliminado (solo 1 producto restante)");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Producto desagrupado correctamente'
            ]);

        } elseif ($action === 'ungroup_all') {
            // Desagrupar completamente un grupo
            if (!isset($input['group_id'])) {
                throw new Exception('Se requiere group_id');
            }

            $groupId = intval($input['group_id']);

            // Verificar que el grupo pertenece a URLs del usuario
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM product_group_items pgi
                JOIN monitored_urls mu ON pgi.monitored_url_id = mu.id
                WHERE pgi.group_id = ? AND mu.user_id = ?
            ");
            $stmt->execute([$groupId, $userId]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
                throw new Exception('Grupo no encontrado o no te pertenece');
            }

            // Eliminar todos los items del grupo
            $stmt = $pdo->prepare("DELETE FROM product_group_items WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // Eliminar el grupo
            $stmt = $pdo->prepare("DELETE FROM product_groups WHERE id = ?");
            $stmt->execute([$groupId]);

            echo json_encode([
                'success' => true,
                'message' => 'Grupo eliminado completamente'
            ]);

        } else {
            throw new Exception('Acción no válida');
        }

    } elseif ($method === 'GET') {
        // Obtener información de grupos del usuario
        $stmt = $pdo->prepare("
            SELECT
                pg.id as group_id,
                pg.name as group_name,
                pg.brand,
                pg.model,
                COUNT(pgi.id) as products_count,
                GROUP_CONCAT(pgi.monitored_url_id) as url_ids
            FROM product_groups pg
            JOIN product_group_items pgi ON pg.id = pgi.group_id
            JOIN monitored_urls mu ON pgi.monitored_url_id = mu.id
            WHERE mu.user_id = ?
            GROUP BY pg.id
        ");
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'groups' => $groups
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }

} catch (Exception $e) {
    error_log("[ManualGrouping] Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
