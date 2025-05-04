<?php
define('IN_BOT', true);
require_once __DIR__ . '/config.php';

// Загрузка данных
$faqAnswers = load_json_data(FAQ_DATA_FILE);
if (empty($faqAnswers)) {
    $faqAnswers = [
        'Доставка' => "🚚 <b>Информация о доставке:</b>\n\nДоставка осуществляется по всей России...",
        'Гарантия' => "🔧 <b>Гарантийные условия:</b>\n\nНа все товары предоставляется гарантия...",
        'Коды ошибок' => "⚠️ <b>Коды ошибок:</b>\n\nДля диагностики ошибок используйте команду /diagnostics...",
        'Техподдержка' => "📞 <b>Техническая поддержка:</b>\n\nТелефон: +7 (XXX) XXX-XX-XX..."
    ];
}

// Загрузка кнопок FAQ (добавлено)
$faqButtons = load_json_data(FAQ_BUTTONS_FILE)['buttons'] ?? [
    ['Доставка', 'Гарантия'],
    ['Коды ошибок', 'Техподдержка']
];

// Получаем входящее сообщение
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    
    // Обработка команд
    if (strpos($text, '/') === 0) {
        handle_command($chatId, $text);
    }
    // Обработка кнопок FAQ
    elseif (isset($faqAnswers[$text])) {
        send_message($chatId, $faqAnswers[$text], null, 'HTML');
    }
    // Обработка ответов на команды
    elseif (isset($message['reply_to_message'])) {
        handle_reply($chatId, $text, $message['reply_to_message']);
    }
}

// Обработка callback-кнопок
if (isset($update['callback_query'])) {
    $data = $update['callback_query']['data'];
    $chatId = $update['callback_query']['message']['chat']['id'];
    
    if ($data === 'diagnose_by_code') {
        send_message($chatId, "Введите код ошибки (например P0451):", ['force_reply' => true]);
    }
}

// Функции обработки
function handle_command($chatId, $command) {
    global $faqButtons; // Добавлено для доступа к переменной
    
    $command = explode(' ', $command)[0];
    
    switch ($command) {
        case '/start':
            send_message($chatId, "👋 Добро пожаловать! Используйте:\n/faq - Частые вопросы\n/search - Поиск\n/diagnostics - Диагностика");
            break;
            
        case '/faq':
            send_message($chatId, "Выберите вопрос:", [
                'keyboard' => $faqButtons, // Изменено на динамическую загрузку
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);
            break;
            
        case '/search':
            send_message($chatId, "Введите поисковый запрос:", ['force_reply' => true]);
            break;
            
        case '/diagnostics':
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Диагностика по коду', 'callback_data' => 'diagnose_by_code'],
                        ['text' => 'Консультация механика', 'url' => MECHANICS_GROUP_LINK]
                    ]
                ]
            ];
            send_message($chatId, "Выберите вариант:", $keyboard);
            break;
            
        case '/help':
            send_message($chatId, "Доступные команды:\n/faq\n/search\n/diagnostics");
            break;
            
        default:
            send_message($chatId, "Неизвестная команда. Используйте /help");
    }
}

// Остальные функции остаются без изменений

function handle_reply($chatId, $text, $reply) {
    // Обработка поискового запроса
    if (strpos($reply['text'], 'поисковый запрос') !== false) {
        $searchQuery = trim($text);
        $searchUrl = FORUM_SEARCH_URL . urlencode($searchQuery);
        
        $response = "🔍 Результаты поиска по запросу \"$searchQuery\":\n";
        $response .= "Перейдите по ссылке для просмотра результатов:\n";
        $response .= $searchUrl;
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Открыть результаты', 'url' => $searchUrl]
                ]
            ]
        ];
        
        send_message($chatId, $response, $keyboard);
    }
    // Обработка кода ошибки
    elseif (strpos($reply['text'], 'Введите код ошибки') !== false) {
        $errorCode = strtoupper(trim($text));
        $errorInfo = get_error_info($errorCode);
        
        if ($errorInfo) {
            $response = "🔧 {$errorInfo['code']}\n\n";
            $response .= "📝 Описание: {$errorInfo['description']}\n\n";
            $response .= "⚠️ Причины:\n• " . implode("\n• ", $errorInfo['causes']) . "\n\n";
            $response .= "🛠️ Решения:\n• " . implode("\n• ", $errorInfo['solutions']);
            
            $searchUrl = FORUM_SEARCH_URL . urlencode($errorCode);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Нужна помощь', 'url' => MECHANICS_GROUP_LINK],
                        ['text' => 'Поиск на форуме', 'url' => $searchUrl]
                    ]
                ]
            ];
            send_message($chatId, $response, $keyboard);
        } else {
            send_message($chatId, "❌ Код ошибки не найден. Проверьте правильность ввода.");
        }
    }
}

// Вспомогательные функции
function send_message($chatId, $text, $replyMarkup = null, $parseMode = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }
    
    file_get_contents(TELEGRAM_API_URL . 'sendMessage?' . http_build_query($data));
}

function get_error_info($errorCode) {
    $errors = load_json_data(ERRORS_DB_FILE);
    return $errors[$errorCode] ?? null;
}

header("Content-Type: application/json");
echo json_encode(['status' => 'ok']);