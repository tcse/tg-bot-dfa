<?php
// Настройки Telegram API
define('BOT_TOKEN', 'ваш_токен_бота');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

// Путь к файлу с данными о запчастях
define('PARTS_DATA_FILE', '/plugins/tcse/xml2json/data/price.json');

// Функция отправки сообщений
function send_message($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'MarkdownV2'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    
    file_get_contents(API_URL.'sendMessage?'.http_build_query($data));
}
?>