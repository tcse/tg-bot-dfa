<?php
// =============================================
// КОНФИГУРАЦИЯ БЕЗ ЗАВИСИМОСТИ ОТ WORDPRESS
// =============================================

// Безопасность: запрещаем прямой доступ к файлу
if (!defined('IN_BOT')) {
    die('Direct access not allowed');
}

// Настройки Telegram бота
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_BOT_USERNAME', 'dongfengaeolus_login_bot');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');

// Настройки для работы с форумом
define('FORUM_BASE_URL', 'https://dongfeng-aeolus.ru/forums/');
define('MECHANICS_GROUP_LINK', 'https://t.me/dongfeng_rb');
define('FORUM_SEARCH_URL', 'https://dongfeng-aeolus.ru/community/?wpfs=');

// Настройки базы данных
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASSWORD', '');

// Пути к файлам данных
define('DATA_DIR', __DIR__ . '/data/');
define('FAQ_DATA_FILE', DATA_DIR . 'faq_data.json');
define('ERRORS_DB_FILE', DATA_DIR . 'errors_db.json');
define('FAQ_BUTTONS_FILE', __DIR__ . '/data/faq_buttons.json');
define('LOG_FILE', __DIR__ . '/logs/bot.log');

// Создаём папки при их отсутствии
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(__DIR__ . '/logs/')) {
    mkdir(__DIR__ . '/logs/', 0755, true);
}
if (!file_exists(__DIR__ . '/backups/')) {
    mkdir(__DIR__ . '/backups/', 0755, true);
}

// Подключение к БД
function connect_db() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if ($db->connect_error) {
                throw new Exception("DB connection failed: " . $db->connect_error);
            }
            $db->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log("DB error: " . $e->getMessage());
            return false;
        }
    }
    
    return $db;
}

// Функции безопасности
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Логирование
function log_action($message, $data = []) {
    $log = date('[Y-m-d H:i:s]') . ' ' . $message;
    if (!empty($data)) {
        $log .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents(LOG_FILE, $log . PHP_EOL, FILE_APPEND);
}

// Загрузка JSON данных с обработкой ошибок
function load_json_data($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $data = file_get_contents($file);
    $result = json_decode($data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_action("JSON decode error in $file", ['error' => json_last_error_msg()]);
        return [];
    }
    
    return $result;
}

// Автоматическое определение базового URL
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) 
             ? 'https://' : 'http://';
define('BOT_BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/');
define('BOT_WEBHOOK_URL', BOT_BASE_URL . 'index.php');

// Telegram API endpoints
define('TELEGRAM_DELETE_WEBHOOK', TELEGRAM_API_URL . 'deleteWebhook');
define('TELEGRAM_GET_WEBHOOK_INFO', TELEGRAM_API_URL . 'getWebhookInfo');
define('TELEGRAM_GET_ME', TELEGRAM_API_URL . 'getMe');