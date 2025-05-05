<?php
// Функция поиска по артикулу
function searchByVendorCode($chat_id, $vendorCode) {
    $products = loadProductsData();
    $found = false;
    
    foreach ($products['offers'] as $product) {
        if (strcasecmp($product['vendorCode'], $vendorCode) === 0) {
            sendProductCard($chat_id, $product);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        send_message($chat_id, "Запчасть с артикулом {$vendorCode} не найдена.");
    }
    resetUserState($chat_id);
}

// Функция поиска по названию
function searchByPartName($chat_id, $query) {
    $products = loadProductsData();
    $results = [];
    
    foreach ($products['offers'] as $product) {
        if (stripos($product['name'], $query) !== false || 
            stripos($product['description'], $query) !== false) {
            $results[] = $product;
        }
    }
    
    if (!empty($results)) {
        sendSearchResults($chat_id, $results, "названию", $query);
    } else {
        send_message($chat_id, "По запросу '{$query}' ничего не найдено.");
    }
    resetUserState($chat_id);
}

// Функция поиска по производителю
function searchByManufacturer($chat_id, $query) {
    $products = loadProductsData();
    $results = [];
    
    foreach ($products['offers'] as $product) {
        if (isset($product['params']['Производитель']) && 
            stripos($product['params']['Производитель'], $query) !== false) {
            $results[] = $product;
        }
    }
    
    if (!empty($results)) {
        sendManufacturerResults($chat_id, $results, $query);
    } else {
        send_message($chat_id, "Производитель '{$query}' не найден.");
    }
    resetUserState($chat_id);
}

// Загрузка данных из JSON
function loadProductsData() {
    $json = file_get_contents(PARTS_DATA_FILE);
    return json_decode($json, true);
}

// Отправка карточки товара
function sendProductCard($chat_id, $product) {
    $price = number_format($product['price'], 0, '', ' ');
    $message = "🔹 **" . escapeMarkdown($product['name']) . "**\n\n";
    $message .= "🖼 Фото товара\n" . $product['picture'] . "\n\n";
    $message .= "🆔 Артикул: `" . $product['vendorCode'] . "`\n";
    $message .= "🏷 **Цена:** {$price} ₽\n";
    
    if (!empty($product['vendor'])) {
        $message .= "🏭 Производитель: " . escapeMarkdown($product['vendor']) . "\n";
    }
    
    if (!empty($product['description'])) {
        $message .= "📝 **Описание:** " . escapeMarkdown($product['description']) . "\n\n";
    }
    
    $message .= "🌐 Открыть страницу товара\n" . $product['url'];
    
    send_message($chat_id, $message);
}

// Отправка результатов поиска по названию
function sendSearchResults($chat_id, $products, $searchType, $query) {
    $message = "Результаты поиска по запросу: " . escapeMarkdown($query) . "\n\n";
    
    foreach ($products as $index => $product) {
        $num = $index + 1;
        $message .= "{$num}. **" . escapeMarkdown($product['name']) . "**\n";
        $message .= $product['url'] . "\n\n";
    }
    
    send_message($chat_id, $message);
}

// Отправка результатов поиска по производителю
function sendManufacturerResults($chat_id, $products, $query) {
    $message = "Результаты поиска по производителю: " . escapeMarkdown($query) . "\n\n";
    
    foreach ($products as $index => $product) {
        $num = $index + 1;
        $message .= "{$num}. **" . escapeMarkdown($product['name']) . "**\n";
        
        if (isset($product['params']['Группа товаров'])) {
            $message .= escapeMarkdown($product['params']['Группа товаров']) . "\n";
        }
        
        $message .= $product['url'] . "\n\n";
    }
    
    send_message($chat_id, $message);
}

// Экранирование спецсимволов Markdown
function escapeMarkdown($text) {
    return str_replace(
        ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
        ['\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'],
        $text
    );
}
?>