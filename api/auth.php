<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

// Respuesta JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Registro de usuario
if ($action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirmPassword'] ?? '';

    // Validaciones
    if (empty($username) || empty($email) || empty($password)) {
        sendResponse(false, 'Todos los campos son obligatorios');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Email inválido');
    }

    if (strlen($password) < 6) {
        sendResponse(false, 'La contraseña debe tener al menos 6 caracteres');
    }

    if ($password !== $confirmPassword) {
        sendResponse(false, 'Las contraseñas no coinciden');
    }

    // Verificar si el usuario ya existe
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        sendResponse(false, 'El usuario o email ya existe');
    }

    // Crear usuario
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");

    try {
        $stmt->execute([$username, $email, $hashedPassword]);
        sendResponse(true, 'Usuario registrado exitosamente');
    } catch (PDOException $e) {
        sendResponse(false, 'Error al registrar usuario');
    }
}

// Login de usuario
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        sendResponse(false, 'Usuario y contraseña son obligatorios');
    }

    $stmt = $db->prepare("SELECT id, username, email, password FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }

    // Iniciar sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    sendResponse(true, 'Login exitoso', [
        'username' => $user['username'],
        'email' => $user['email']
    ]);
}

// Logout
if ($action === 'logout') {
    session_destroy();
    sendResponse(true, 'Sesión cerrada');
}

// Verificar sesión
if ($action === 'check') {
    if (isset($_SESSION['user_id'])) {
        sendResponse(true, 'Sesión activa', [
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email']
        ]);
    } else {
        sendResponse(false, 'No hay sesión activa');
    }
}

sendResponse(false, 'Acción no válida');
?>
