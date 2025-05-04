<?php
// Назначение: Этот файл нужен ТОЛЬКО для регистрации команд бота в интерфейсе Telegram (чтобы они отображались в меню "/")

define('IN_BOT', true);
require __DIR__ . '/config.php';

// Разрешаем только AJAX-запросы
if (empty($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'description' => 'Only JSON requests allowed']));
}

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$commands = [
    [
        'command' => 'start',
        'description' => 'Главное меню'
    ],
    [
        'command' => 'faq',
        'description' => 'Часто задаваемые вопросы'
    ],
    [
        'command' => 'search',
        'description' => 'Поиск по форуму'
    ],
    [
        'command' => 'diagnostics',
        'description' => 'Диагностика проблем'
    ],
    [
        'command' => 'help',
        'description' => 'Помощь по командам'
    ]
];

try {
    $url = TELEGRAM_API_URL . 'setMyCommands';
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['commands' => $commands])
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        throw new Exception('Ошибка HTTP-запроса к Telegram API');
    }
    
    $response = json_decode($result, true);
    
    if (!$response['ok']) {
        throw new Exception($response['description'] ?? 'Unknown Telegram API error');
    }
    
    echo json_encode([
        'ok' => true,
        'result' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'description' => $e->getMessage()
    ]);
}