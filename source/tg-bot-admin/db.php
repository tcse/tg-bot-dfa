<?php
// Функция установки состояния пользователя
function setUserState($chatId, $state) {
    $filename = "user_states/{$chatId}.txt";
    file_put_contents($filename, $state);
}

// Функция получения состояния пользователя
function getUserState($chatId) {
    $filename = "user_states/{$chatId}.txt";
    return file_exists($filename) ? file_get_contents($filename) : null;
}

// Функция сброса состояния пользователя
function resetUserState($chatId) {
    $filename = "user_states/{$chatId}.txt";
    if (file_exists($filename)) {
        unlink($filename);
    }
}
?>