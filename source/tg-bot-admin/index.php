<?php
require_once 'config.php';
require_once 'db.php';
require_once 'parts_search.php'; // Подключаем новый функционал

// Обработка входящих сообщений
$update = json_decode(file_get_contents('php://input'), true);

// Обработка команды /parts
if (isset($update['message']['text']) && strpos($update['message']['text'], '/parts') === 0) {
    $chatId = $update['message']['chat']['id'];
    showPartsSearchMenu($chatId);
}

// Обработка callback-кнопок
if (isset($update['callback_query'])) {
    $data = $update['callback_query']['data'];
    $chatId = $update['callback_query']['message']['chat']['id'];
    
    if ($data === 'diagnose_by_code') {
        send_message($chatId, "Введите код ошибки (например P0451):", ['force_reply' => true]);
    }
}

// Обработка состояний поиска запчастей
if (isset($update['message']['text'])) {
    $text = $update['message']['text'];
    $chatId = $update['message']['chat']['id'];
    $state = getUserState($chatId);
    
    // Обработка выбора типа поиска
    if ($state === 'awaiting_search_type') {
        if ($text === '1 - поиск по артикулу') {
            setUserState($chatId, 'awaiting_vendor_code');
            send_message($chatId, "Введите артикул запчасти (например: W084098):", ['force_reply' => true]);
        } elseif ($text === '2 - поиск по названию') {
            setUserState($chatId, 'awaiting_part_name');
            send_message($chatId, "Введите название или часть названия запчасти:", ['force_reply' => true]);
        } elseif ($text === '3 - поиск по производителю') {
            setUserState($chatId, 'awaiting_manufacturer');
            send_message($chatId, "Введите название производителя:", ['force_reply' => true]);
        }
    }
    // Обработка ввода артикула
    elseif ($state === 'awaiting_vendor_code') {
        searchByVendorCode($chatId, $text);
    }
    // Обработка ввода названия
    elseif ($state === 'awaiting_part_name') {
        searchByPartName($chatId, $text);
    }
    // Обработка ввода производителя
    elseif ($state === 'awaiting_manufacturer') {
        searchByManufacturer($chatId, $text);
    }
}

// Функция показа меню поиска запчастей
function showPartsSearchMenu($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => '1 - поиск по артикулу']],
            [['text' => '2 - поиск по названию']],
            [['text' => '3 - поиск по производителю']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    
    send_message($chat_id, "Выберите как именно вы будете искать:", json_encode($keyboard));
    setUserState($chat_id, 'awaiting_search_type');
}
?>