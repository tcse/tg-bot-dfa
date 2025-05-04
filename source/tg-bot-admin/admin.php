<?php
define('IN_BOT', true);
require __DIR__ . '/config.php';

// Настройки авторизации
define('ADMIN_LOGIN', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$92X8rXf7nN9B5Jh6V8QeE.FYxjZ7NjX9sLb9VYbLk9JHs7d8KsN2');

session_start();

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Выход из системы
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['login'] === ADMIN_LOGIN) {
            $_SESSION['auth'] = true;
            header('Location: admin.php');
            exit;
        }
        $error = "Неверные учетные данные";
    }
    
    show_login_form($error ?? null);
    exit;
}

// Обработка сохранения
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Проверка CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка безопасности: неверный CSRF токен');
    }

    // Создаем backup
    create_backup();

    // Обновляем конфиг
    update_config($_POST);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Сохраняем кнопки FAQ
    if (!empty($_POST['faq_buttons'])) {
        file_put_contents(FAQ_BUTTONS_FILE, $_POST['faq_buttons']);
    }
    
    // Сохраняем тексты FAQ (исправленная версия)
    if (!empty($_POST['faq_data'])) {
        $faq_data = json_decode($_POST['faq_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            file_put_contents(FAQ_DATA_FILE, json_encode($faq_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    // ... остальные сохранения
    // Сохраняем ошибки
    if (!empty($_POST['errors_data'])) {
        $errors_data = json_decode($_POST['errors_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            file_put_contents(ERRORS_DB_FILE, json_encode($errors_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    $success = "Настройки успешно сохранены!";
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Обновляем CSRF
}

    
}

// Загрузка текущих данных
$current_config = [
    'bot_token' => TELEGRAM_BOT_TOKEN,
    'mechanics_link' => MECHANICS_GROUP_LINK,
    'forum_search_url' => FORUM_SEARCH_URL,
    'forum_base_url' => FORUM_BASE_URL
];

$faq_data = load_json_data(FAQ_DATA_FILE);
$errors_data = load_json_data(ERRORS_DB_FILE);

show_admin_panel($current_config, $faq_data, $errors_data, $success ?? null);

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

function create_backup() {
    $backup_dir = __DIR__ . '/backups/' . date('Y-m-d_H-i-s');
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
        mkdir($backup_dir . '/data', 0755, true); // Папка для данных в бэкапе
    }
    
    // Копируем основные файлы
    $files = ['config.php', 'admin.php', 'index.php'];
    foreach ($files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            copy(__DIR__ . '/' . $file, $backup_dir . '/' . $file);
        }
    }
    
    // Копируем файлы данных
    $data_files = ['faq_data.json', 'errors_db.json'];
    foreach ($data_files as $file) {
        if (file_exists(DATA_DIR . $file)) {
            copy(DATA_DIR . $file, $backup_dir . '/data/' . $file);
        }
    }
}

function update_config($data) {
    $config_content = file_get_contents(__DIR__ . '/config.php');
    
    $replacements = [
        'TELEGRAM_BOT_TOKEN' => addslashes($data['bot_token']),
        'MECHANICS_GROUP_LINK' => addslashes($data['mechanics_link']),
        'FORUM_SEARCH_URL' => addslashes($data['forum_search_url']),
        'FORUM_BASE_URL' => addslashes($data['forum_base_url'])
    ];
    
    foreach ($replacements as $const => $value) {
        $config_content = preg_replace(
            "/define\('$const',\s*'.*?'\);/",
            "define('$const', '$value');",
            $config_content
        );
    }
    
    file_put_contents(__DIR__ . '/config.php', $config_content);
}

function show_login_form($error = null) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Авторизация</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container" style="max-width: 400px; margin-top: 100px;">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="text-center mb-4">Авторизация</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <input type="text" name="login" class="form-control" placeholder="Логин" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Пароль" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Войти</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function show_admin_panel($config, $faq, $errors, $success = null) {
    $faq_json = json_encode($faq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $errors_json = json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Админ-панель</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <style>
            .json-editor { height: 400px; font-family: monospace; }
            .nav-tabs .nav-link.active { font-weight: bold; }
            .tab-pane { padding: 20px 0; }
            /* Подсветка Markdown в предпросмотре */
            .markdown-preview b, .markdown-preview strong { font-weight: bold; }
            .markdown-preview i, .markdown-preview em { font-style: italic; }
            .markdown-preview code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
        </style>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
        <style>
        .font-monospace {
            font-family: monospace;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3 bg-dark text-white min-vh-100 p-4">
                    <h4>TG Bot Admin</h4>
                    <hr>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white active" data-bs-toggle="tab" href="#settings">Настройки</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" data-bs-toggle="tab" href="#faq">FAQ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" data-bs-toggle="tab" href="#errors">Коды ошибок</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" data-bs-toggle="tab" href="#commands">Команды бота</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" data-bs-toggle="tab" href="#helpers">Помогаторы</a>
                        </li>
                        <li class="nav-item mt-4">
                            <a href="admin.php?logout" class="nav-link text-white">Выйти</a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-9 p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" id="helpersForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="tab-content">
                            <!-- Вкладка основных настроек -->
                            <div class="tab-pane fade show active" id="settings">
                                <h2>Основные настройки</h2>
                                <div class="mb-3">
                                    <label class="form-label">Telegram Bot Token</label>
                                    <input type="text" name="bot_token" value="<?= htmlspecialchars($config['bot_token']) ?>" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ссылка на группу механиков</label>
                                    <input type="url" name="mechanics_link" value="<?= htmlspecialchars($config['mechanics_link']) ?>" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL поиска по форуму</label>
                                    <input type="url" name="forum_search_url" value="<?= htmlspecialchars($config['forum_search_url']) ?>" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Базовый URL форума</label>
                                    <input type="url" name="forum_base_url" value="<?= htmlspecialchars($config['forum_base_url']) ?>" class="form-control" required>
                                </div>
                            </div>
                            
                            <!-- Вкладка FAQ -->
                            <div class="tab-pane fade" id="faq">
                                <h2>Управление FAQ</h2>

                                <div class="mb-4">
                                    <p>
                                        В админке 2 JSON-редактора – для кнопок и текстов.<br>
                                        При сохранении бот автоматически подхватит новую структуру кнопок из <code>data/faq_buttons.json</code>.<br>
                                        Без перезагрузки бота – изменения применяются мгновенно!
                                    </p>
                                </div>
                                <div class="mb-4">
                                    <h4>Структура кнопок</h4>
                                    <textarea name="faq_buttons" class="form-control json-editor mb-3"><?= 
                                        file_exists(FAQ_BUTTONS_FILE) ? 
                                        htmlspecialchars(file_get_contents(FAQ_BUTTONS_FILE)) : 
                                        '{"buttons":[["Доставка","Гарантия"],["Коды ошибок","Техподдержка"]]}'
                                    ?></textarea>
                                    <p class="small">
                                        Формирует кнопки внутри телеграм
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <h4>Тексты ответов</h4>
                                    <textarea name="faq_data" class="form-control json-editor"><?= 
                                        htmlspecialchars(file_get_contents(FAQ_DATA_FILE))
                                    ?></textarea>
                                    <p class="small">
                                        Формирует текст ответа после нажатия на кнопку указанную выше. Обязателньое условие, название кнопки должно быть точно таким же как указано в блоке <b>Структура кнопок</b>.
                                    </p>
                                </div>
                                <h3>Редакторы текста для конвертации в json</h3>
                                <div class="alert alert-warning">
                                    <p>Как это работает:</p>
                                    <p>
                                        <b>Ввод текста:</b><br>
                                        Вводите текст с обычными переносами строк<br>
                                        Используете Markdown для форматирования (**жирный**, _курсив_)<br>
                                        <br>
                                        <b>Преобразование:</b><br>
                                        Нажимаете "Преобразовать в JSON"<br>
                                        Текст автоматически экранируется и форматируется<br>
                                        Появляется предпросмотр как это будет выглядеть у пользователя<br>
                                        <br>
                                        <b>Копирование:</b><br>
                                        Готовый JSON можно скопировать кнопкой<br>
                                        Или он автоматически сохраняется в скрытое поле формы
                                    </p>
                                    
                                </div>
                                <!-- Редактор текста -->
                                <div class="mb-3">
                                    <label class="form-label">Текст ответа (с переносами строк):</label>
                                    <textarea id="rawText" class="form-control" rows="6" placeholder="Введите текст с переносами строк..."></textarea>
                                    <small class="text-muted">
                                        Поддерживается Markdown-разметка: **жирный**, _курсив_
                                    </small>
                                </div>
                                
                                <button type="button" class="btn btn-primary mb-3" onclick="convertToJson()">
                                    <i class="bi bi-code-square"></i> Преобразовать в JSON
                                </button>
                                
                                <!-- Поле результата -->
                                <div class="mb-3">
                                    <label class="form-label">JSON для копирования:</label>
                                    <textarea id="jsonOutput" class="form-control" rows="6" readonly></textarea>
                                    <button  type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="copyJson()">
                                        <i class="bi bi-clipboard"></i> Скопировать JSON
                                    </button>
                                </div>
                                
                                <!-- Основное поле хранения (скрытое) -->
                                <!-- <textarea name="faq_data" id="faqDataStorage" style="display:none;"><?= 
                                    htmlspecialchars(file_get_contents(FAQ_DATA_FILE))
                                ?></textarea> -->


                            </div>
                            
                            <!-- Вкладка кодов ошибок -->
                            <div class="tab-pane fade" id="errors">
                                <h2>Управление кодами ошибок</h2>
                                <p class="text-muted">Используйте JSON формат для редактирования базы ошибок</p>
                                <textarea name="errors_data" class="form-control json-editor"><?= htmlspecialchars($errors_json) ?></textarea>
                            </div>

                            <!-- Вкладка помогаторов -->
                            <div class="tab-pane fade" id="helpers">
                                <h2 class="mb-4">Управление ботом</h2>
                                
                                <div class="row">
                                    <!-- Колонка с командами -->
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0"><i class="bi bi-terminal"></i> Команды терминала</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Установка вебхука -->
                                                <div class="mb-3">
                                                    <label class="form-label">Установить вебхук:</label>
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" id="setWebhookCmd" 
                                                               value='curl "<?= TELEGRAM_API_URL ?>setWebhook?url=<?= BOT_WEBHOOK_URL ?>"' readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCommand('setWebhookCmd')">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Удаление вебхука -->
                                                <div class="mb-3">
                                                    <label class="form-label">Удалить вебхук:</label>
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" id="deleteWebhookCmd" 
                                                               value='curl "<?= TELEGRAM_DELETE_WEBHOOK ?>"' readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCommand('deleteWebhookCmd')">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Проверка вебхука -->
                                                <div class="mb-3">
                                                    <label class="form-label">Проверить вебхук:</label>
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" id="getWebhookInfoCmd" 
                                                               value='curl "<?= TELEGRAM_GET_WEBHOOK_INFO ?>"' readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCommand('getWebhookInfoCmd')">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Информация о боте -->
                                                <div class="mb-3">
                                                    <label class="form-label">Информация о боте:</label>
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" id="getMeCmd" 
                                                               value='curl "<?= TELEGRAM_GET_ME ?>"' readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCommand('getMeCmd')">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Колонка с быстрыми действиями -->
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header bg-success text-white">
                                                <h5 class="mb-0"><i class="bi bi-lightning"></i> Быстрые действия</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-2">
                                                    <button type="button" class="btn btn-primary mb-2" onclick="executeAction('setWebhook')">
                                                        <i class="bi bi-plug"></i> Установить вебхук
                                                    </button>
                                                    <button type="button" class="btn btn-danger mb-2" onclick="executeAction('deleteWebhook')">
                                                        <i class="bi bi-plug-fill"></i> Удалить вебхук
                                                    </button>
                                                    <button type="button" class="btn btn-info mb-2" onclick="executeAction('getWebhookInfo')">
                                                        <i class="bi bi-info-circle"></i> Проверить вебхук
                                                    </button>
                                                    <button type="button" class="btn btn-secondary mb-2" onclick="executeAction('getMe')">
                                                        <i class="bi bi-robot"></i> Информация о боте
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header bg-dark text-white">
                                                <h5 class="mb-0"><i class="bi bi-code-square"></i> Результат выполнения</h5>
                                            </div>
                                            <div class="card-body">
                                                <pre id="commandResult" class="bg-light p-3" style="min-height: 150px; max-height: 300px; overflow: auto;">Выберите действие...</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Информационная панель -->
                                <div class="card mt-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-question-circle"></i> Справка</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-link-45deg"></i> Текущие URL</h5>
                                                <ul class="list-group mb-3">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Вебхук
                                                        <span class="badge bg-primary rounded-pill font-monospace"><?= BOT_WEBHOOK_URL ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Базовая директория
                                                        <span class="badge bg-secondary rounded-pill font-monospace"><?= __DIR__ ?></span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-lightbulb"></i> Подсказки</h5>
                                                <div class="alert alert-warning">
                                                    <p class="mb-2"><strong>Как использовать:</strong></p>
                                                    <ul class="mb-0">
                                                        <li>Используйте кнопки для быстрого управления</li>
                                                        <li>Копируйте команды для ручного выполнения</li>
                                                        <li>Результаты отображаются в реальном времени</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Вкладка "Команды бота" -->
                            <div class="tab-pane fade" id="commands">
                                <h2>Управление командами меню</h2>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Текущие команды:</label>
                                            <pre id="currentCommands"><?= json_encode($commands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary" onclick="updateBotCommands(event)">
                                            <i class="bi bi-arrow-repeat"></i> Обновить команды
                                        </button>
                                    </div>
                                </div>

                                <p>
                                    <b>Итоговая логика работы:</b><br>
                                    <br>
                                    Разработчик редактирует команды в <code>/plugins/tcse/tg-bot-admin/set_commands.php</code><br>
                                    <br>
                                    Через админку нажимает "Обновить команды"<br>
                                    <br>
                                    Бот получает новый список команд в Telegram<br>
                                    <br>
                                    Пользователи видят обновлённое меню при вводе "/"
                                </p>
                            </div>

                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="save" class="btn btn-success">Сохранить все изменения</button>
                            <button type="button" class="btn btn-outline-warning ms-2" id="cancelChanges">Отменить изменения</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            // Проверка поддержки Fetch API
            if (!window.fetch) {
                document.getElementById('commandResult').innerHTML = 
                    '<div class="alert alert-warning">Ваш браузер не поддерживает Fetch API. Используйте команды терминала.</div>';
            }

            // Функция копирования команд
            function copyCommand(elementId) {
                const element = document.getElementById(elementId);
                element.select();
                document.execCommand('copy');
                
                // Визуальная обратная связь
                const originalText = element.value;
                element.value = 'Скопировано!';
                setTimeout(() => {
                    element.value = originalText;
                }, 2000);
            }

            // Функция выполнения действий через AJAX
            async function executeAction(action) {
                const resultElement = document.getElementById('commandResult');
                resultElement.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p>Выполняем запрос...</p></div>';
                
                try {
                    const response = await fetch('api_proxy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=${encodeURIComponent(action)}&csrf_token=${encodeURIComponent('<?= $_SESSION['csrf_token'] ?>')}`
                    });
                    
                    const data = await response.json();
                    resultElement.innerHTML = syntaxHighlight(JSON.stringify(data, null, 4));
                } catch (error) {
                    resultElement.innerHTML = `<div class="alert alert-danger">
                        Ошибка: ${error.message}<br>
                        Попробуйте использовать команды терминала
                    </div>`;
                }
            }

            // Функция для подсветки синтаксиса JSON
            function syntaxHighlight(json) {
                json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, 
                function (match) {
                    let cls = 'text-dark';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'text-primary';
                        } else {
                            cls = 'text-success';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'text-info';
                    } else if (/null/.test(match)) {
                        cls = 'text-danger';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                });
            }

            // Отмена изменений без перезагрузки
            document.getElementById('cancelChanges')?.addEventListener('click', function(e) {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-pane.active');
                const form = activeTab.querySelector('form');
                if (form) {
                    form.reset();
                }
                
                // Показываем уведомление вместо перезагрузки
                const alert = document.createElement('div');
                alert.className = 'alert alert-info alert-dismissible fade show mt-3';
                alert.innerHTML = 'Изменения отменены <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                activeTab.appendChild(alert);
            });

            // Предотвращаем отправку формы при нажатии на быстрые действия
            document.querySelectorAll('#helpersForm button[type="button"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            // для обработки команд бота
            async function updateBotCommands(event) {
            // Предотвращаем отправку формы и перезагрузку страницы
            event.preventDefault();
            event.stopPropagation();
            
            const resultElement = document.getElementById('currentCommands');
            resultElement.innerHTML = '<div class="spinner-border"></div> Обновление...';
            
            try {
                const response = await fetch('set_commands.php', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.ok) {
                    resultElement.innerHTML = '<div class="alert alert-success">Команды успешно обновлены!</div>' + 
                        syntaxHighlight(JSON.stringify(data.result, null, 2));
                } else {
                    resultElement.innerHTML = '<div class="alert alert-danger">Ошибка: ' + 
                        (data.description || 'Неизвестная ошибка') + '</div>';
                }
            } catch (error) {
                resultElement.innerHTML = '<div class="alert alert-danger">Ошибка: ' + error.message + '</div>';
                console.error('Ошибка при обновлении команд:', error);
            }
        }
        </script>

        <script>
        // Валидация JSON перед сохранением
        document.querySelector('form').addEventListener('submit', function(e) {
            try {
                JSON.parse(document.querySelector('[name="faq_buttons"]').value);
                JSON.parse(document.querySelector('[name="faq_data"]').value);
            } catch (error) {
                e.preventDefault();
                alert('Ошибка в JSON: ' + error.message);
                return false;
            }
        });
        </script>

        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        <script>
            // Преобразование текста в JSON-формат
            function convertToJson() {
                const rawText = document.getElementById('rawText').value;
                
                // Преобразуем Markdown в HTML
                let htmlText = rawText
                    .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')  // **жирный** -> <b>жирный</b>
                
                // Экранируем специальные символы
                const jsonReadyText = htmlText
                .replace(/\\/g, '\\\\')
                .replace(/"/g, '\\"')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '')
                .replace(/\//g, '\\/');  // Экранируем слэши
                
                // Формируем JSON-структуру
                const jsonOutput = `"Текст ответа": "${jsonReadyText}"`;
    
                // Выводим результат
                document.getElementById('jsonOutput').value = jsonOutput;
                document.getElementById('faqDataStorage').value = `{\n  ${jsonOutput}\n}`;
                
                // Безопасный предпросмотр
                const preview = document.createElement('div');
                preview.innerHTML = htmlText;
                Swal.fire({
                    title: 'Предпросмотр',
                    html: preview.innerHTML,
                    confirmButtonText: 'OK'
                });
            }

            // Копирование JSON в буфер
            function copyJson() {
                const jsonOutput = document.getElementById('jsonOutput');
                jsonOutput.select();
                document.execCommand('copy');
                
                // Визуальная обратная связь
                const btn = jsonOutput.nextElementSibling;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i> Скопировано!';
                setTimeout(() => { btn.innerHTML = originalText; }, 2000);
            }

            // Загрузка существующих данных при открытии
            document.addEventListener('DOMContentLoaded', function() {
                const storedData = document.getElementById('faqDataStorage').value;
                if (storedData) {
                    try {
                        const jsonData = JSON.parse(storedData);
                        const firstKey = Object.keys(jsonData)[0];
                        const textValue = jsonData[firstKey]
                            .replace(/\\n/g, '\n')
                            .replace(/\\"/g, '"')
                            .replace(/\\\\/g, '\\');
                        
                        document.getElementById('rawText').value = textValue;
                    } catch (e) {
                        console.error("Error parsing stored data:", e);
                    }
                }
            });
        </script>

    </body>
    </html>
    <?php
}