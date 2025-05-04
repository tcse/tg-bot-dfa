<?php
// В начало api_proxy.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

define('IN_BOT', true);
require __DIR__ . '/config.php';

session_start();

// Проверка CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'CSRF token mismatch']));
}

$action = $_POST['action'] ?? '';
$apiUrl = '';

switch($action) {
    case 'setWebhook':
        $apiUrl = TELEGRAM_API_URL . 'setWebhook?url=' . BOT_WEBHOOK_URL;
        break;
    case 'deleteWebhook':
        $apiUrl = TELEGRAM_DELETE_WEBHOOK;
        break;
    case 'getWebhookInfo':
        $apiUrl = TELEGRAM_GET_WEBHOOK_INFO;
        break;
    case 'getMe':
        $apiUrl = TELEGRAM_GET_ME;
        break;
    default:
        die(json_encode(['error' => 'Invalid action']));
}

// Выполняем запрос через cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;