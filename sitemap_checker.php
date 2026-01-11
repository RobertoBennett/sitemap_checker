<?php
/**
 * Sitemap SEO Checker Pro v2.1
 * Добавлена проверка Follow/Nofollow и адаптивный дизайн
 * Добавлены: настройки скорости сканирования, ограничения, исключения, выбор User-Agent, кнопка стоп
 */

// Настройки
$config = [
    'user_agent' => 'Mozilla/5.0 (compatible; YandexWebmaster/2.0; +http://yandex.com/bots)',
    'timeout' => 15,
    'max_urls' => 200, // Ограничение для тестов
    'min_urls' => 1,   // Минимальное количество URL для сканирования
    'output_format' => 'html', // html, json, csv
    'check_headers' => true, 
    'check_meta' => true,
    'check_canonical' => true,
    'follow_redirects' => true,
    'check_language' => true,
    // Скорость сканирования (задержка между запросами в секундах)
    'scan_speed' => 0, // 0 - без задержки, 0.5 - полсекунды, 2 - две секунды
    'max_scan_speed' => 5, // Максимальная задержка
    'min_scan_speed' => 0, // Минимальная задержка
    // Исключения
    'exclude_patterns' => ['/admin', '/login', '/wp-admin'], // Регулярные выражения для исключения URL
    // Лимиты SEO
    'max_title_length' => 75,
    'max_description_length' => 160,
    'min_title_length' => 10,
    'min_description_length' => 50,
    'slow_page_threshold' => 3,
];

// Список популярных User-Agent
$user_agents = [
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Googlebot Mobile' => 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'YandexBot' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
    'YandexWebmaster' => 'Mozilla/5.0 (compatible; YandexWebmaster/2.0; +http://yandex.com/bots)',
    'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'Baiduspider' => 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)',
    'DuckDuckBot' => 'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)',
    'Facebook' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'Twitter' => 'Twitterbot/1.0',
    'LinkedIn' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)',
    'WhatsApp' => 'WhatsApp/2.0',
    'Apple' => 'Applebot/0.1; +http://www.apple.com/go/applebot',
    'Semrush' => 'SemrushBot/7~bl; +https://www.semrush.com/bot.html',
    'Ahrefs' => 'AhrefsBot/7.0; +http://ahrefs.com/robot/',
    'MJ12bot' => 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)',
    'Chrome Desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Firefox Desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
    'Safari Desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    'Mobile Chrome' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.210 Mobile Safari/537.36',
    'Mobile Safari' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
    'Custom' => $config['user_agent'],
];

// Переменная для остановки сканирования
$stop_scan = false;

// --- Функции помощники ---

function detect_language($text) {
    if (empty($text)) return 'unknown';
    $russian_chars = preg_match('/[а-яА-ЯЁё]/u', $text);
    $english_chars = preg_match('/[a-zA-Z]/', $text);
    if ($russian_chars && !$english_chars) return 'ru';
    if ($english_chars && !$russian_chars) return 'en';
    if ($russian_chars && $english_chars) return 'mixed';
    return 'other';
}

function utf8_strlen($string) {
    return mb_strlen($string, 'UTF-8');
}

function fetch_url($url, $config) {
    global $stop_scan;
    
    // Проверяем флаг остановки
    if ($stop_scan) {
        return [
            'success' => false,
            'response' => '',
            'info' => [],
            'error' => 'Сканирование остановлено пользователем'
        ];
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $config['user_agent'],
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_FOLLOWLOCATION => $config['follow_redirects'],
        CURLOPT_HEADER => $config['check_headers'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip, deflate'
    ]);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $response !== false,
        'response' => $response,
        'info' => $info,
        'error' => $error
    ];
}

// --- Парсинг Sitemap ---

function parse_sitemap($url, $config) {
    global $stop_scan;
    
    if ($stop_scan) {
        return ['error' => 'Сканирование остановлено'];
    }
    
    $result = fetch_url($url, $config);
    
    if (!$result['success']) {
        return ['error' => 'Не удалось загрузить sitemap: ' . $result['error']];
    }
    
    $content = $result['response'];
    
    // Gzip check
    if (substr($content, 0, 2) === "\x1f\x8b") {
        $content = gzdecode($content);
        if ($content === false) return ['error' => 'Ошибка распаковки gzipped sitemap'];
    }
    
    if (strpos($content, '<sitemapindex') !== false) {
        return parse_sitemap_index($content, $config);
    } else {
        return parse_regular_sitemap($content);
    }
}

function parse_sitemap_index($content, $config) {
    global $stop_scan;
    
    preg_match_all('/<loc>(.*?)<\/loc>/s', $content, $matches);
    if (empty($matches[1])) return ['error' => 'Не найдено sitemap файлов в индексе'];
    
    $all_urls = [];
    foreach ($matches[1] as $sitemap_url) {
        if ($stop_scan) break;
        
        $sitemap_url = trim($sitemap_url);
        $urls = parse_sitemap($sitemap_url, $config);
        if (isset($urls['urls'])) {
            $all_urls = array_merge($all_urls, $urls['urls']);
        }
        if (count($all_urls) >= $config['max_urls']) {
            $all_urls = array_slice($all_urls, 0, $config['max_urls']);
            break;
        }
    }
    return ['urls' => array_unique($all_urls)];
}

function parse_regular_sitemap($content) {
    $urls = [];
    preg_match_all('/<loc>(.*?)<\/loc>/s', $content, $matches);
    if (!empty($matches[1])) {
        $urls = array_map('trim', $matches[1]);
    }
    if (empty($urls)) { // Plain text fallback
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (filter_var($line, FILTER_VALIDATE_URL)) $urls[] = $line;
        }
    }
    return ['urls' => array_unique($urls)];
}

// --- Фильтрация URL по исключениям ---

function filter_urls($urls, $config) {
    if (empty($config['exclude_patterns'])) {
        return $urls;
    }
    
    $filtered_urls = [];
    foreach ($urls as $url) {
        $exclude = false;
        foreach ($config['exclude_patterns'] as $pattern) {
            if (preg_match($pattern, $url)) {
                $exclude = true;
                break;
            }
        }
        if (!$exclude) {
            $filtered_urls[] = $url;
        }
    }
    
    return $filtered_urls;
}

// --- Основная логика проверки страницы ---

function check_page_seo($url, $config) {
    global $stop_scan;
    
    if ($stop_scan) {
        return [
            'url' => $url,
            'error' => 'Сканирование остановлено',
            'http_code' => 0,
            'response_time' => 0
        ];
    }
    
    $result = fetch_url($url, $config);
    
    if (!$result['success']) {
        return [
            'url' => $url,
            'error' => $result['error'],
            'http_code' => $result['info']['http_code'] ?? 0,
            'response_time' => 0
        ];
    }
    
    $http_code = $result['info']['http_code'];
    $response_time = $result['info']['total_time'];
    $content = $result['response'];
    
    $header_size = $result['info']['header_size'] ?? 0;
    $headers = $header_size > 0 ? substr($content, 0, $header_size) : '';
    $body = $header_size > 0 ? substr($content, $header_size) : $content;
    
    $seo_data = [
        'url' => $url,
        'http_code' => $http_code,
        'response_time' => round($response_time, 2),
        'content_length' => strlen($body),
        
        // Robots flags
        'is_noindex' => false,
        'is_nofollow' => false, // Page level check
        'robots_directives' => [],
        
        // Links stats
        'links_total' => 0,
        'links_nofollow' => 0,
        
        'canonical' => '',
        'title' => '',
        'meta_description' => '',
        'h1_count' => 0,
        'language' => 'unknown',
        'seo_issues' => []
    ];
    
    // 1. Проверка заголовков X-Robots-Tag
    if ($config['check_headers'] && $headers) {
        if (preg_match_all('/X-Robots-Tag:\s*(.*)/i', $headers, $matches)) {
            foreach ($matches[1] as $tag_val) {
                if (stripos($tag_val, 'noindex') !== false) {
                    $seo_data['is_noindex'] = true;
                    $seo_data['robots_directives'][] = 'X-Header: noindex';
                }
                if (stripos($tag_val, 'nofollow') !== false) {
                    $seo_data['is_nofollow'] = true;
                    $seo_data['robots_directives'][] = 'X-Header: nofollow';
                }
            }
        }
    }
    
    // 2. Парсинг HTML
    if ($body) {
        $dom = new DOMDocument();
        // Подавляем ошибки парсинга HTML5 тегов
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        // Проверка Meta Robots
        $meta_tags = $dom->getElementsByTagName('meta');
        foreach ($meta_tags as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $content_attr = strtolower($meta->getAttribute('content'));
            
            if ($name === 'robots') {
                if (strpos($content_attr, 'noindex') !== false) {
                    $seo_data['is_noindex'] = true;
                    $seo_data['robots_directives'][] = 'Meta: noindex';
                }
                if (strpos($content_attr, 'nofollow') !== false) {
                    $seo_data['is_nofollow'] = true;
                    $seo_data['robots_directives'][] = 'Meta: nofollow';
                }
            }
            if ($name === 'description') {
                $seo_data['meta_description'] = trim($meta->getAttribute('content'));
            }
        }
        
        // Canonical
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical') {
                $seo_data['canonical'] = $link->getAttribute('href');
            }
        }
        
        // Title
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $seo_data['title'] = trim($titles->item(0)->textContent);
        }
        
        // H1
        $h1s = $dom->getElementsByTagName('h1');
        $seo_data['h1_count'] = $h1s->length;

        // --- НОВОЕ: Подсчет ссылок и проверка rel="nofollow" ---
        $a_tags = $dom->getElementsByTagName('a');
        $seo_data['links_total'] = $a_tags->length;
        
        foreach ($a_tags as $a) {
            $rel = strtolower($a->getAttribute('rel'));
            // Проверка на наличие "nofollow" в атрибуте rel (может быть "external nofollow" и т.д.)
            if (strpos($rel, 'nofollow') !== false) {
                $seo_data['links_nofollow']++;
            }
        }
        
        // Определение языка
        if ($config['check_language']) {
            $content_text = $seo_data['title'] . ' ' . $seo_data['meta_description'];
            $seo_data['language'] = detect_language($content_text);
        }
        
        // --- Валидация и поиск проблем ---
        
        // Лимиты (корректировка для RU)
        $title_limit = $config['max_title_length'];
        $desc_limit = $config['max_description_length'];
        if ($seo_data['language'] === 'ru') {
            $title_limit += 5; 
            $desc_limit += 20;
        }

        // Title checks
        if (empty($seo_data['title'])) {
            $seo_data['seo_issues'][] = 'Missing title';
        } else {
            $len = utf8_strlen($seo_data['title']);
            if ($len < $config['min_title_length']) $seo_data['seo_issues'][] = "Title short ($len)";
            elseif ($len > $title_limit) $seo_data['seo_issues'][] = "Title long ($len)";
        }
        
        // Description checks
        if (empty($seo_data['meta_description'])) {
            $seo_data['seo_issues'][] = 'Missing description';
        } else {
            $len = utf8_strlen($seo_data['meta_description']);
            if ($len < $config['min_description_length']) $seo_data['seo_issues'][] = "Desc short ($len)";
            elseif ($len > $desc_limit) $seo_data['seo_issues'][] = "Desc long ($len)";
        }
        
        // H1 checks
        if ($seo_data['h1_count'] === 0) $seo_data['seo_issues'][] = 'No H1';
        elseif ($seo_data['h1_count'] > 1) $seo_data['seo_issues'][] = "Multiple H1 ({$seo_data['h1_count']})";
        
        // Canonical check
        if ($config['check_canonical'] && !empty($seo_data['canonical'])) {
            $c_url = parse_url($seo_data['canonical']);
            $curr_url = parse_url($url);
            $c_norm = ($c_url['host'] ?? '') . ($c_url['path'] ?? '');
            $curr_norm = ($curr_url['host'] ?? '') . ($curr_url['path'] ?? '');
            if ($c_norm !== $curr_norm) $seo_data['seo_issues'][] = 'Canonical mismatch';
        }

        // Content check
        $body_tags = $dom->getElementsByTagName('body');
        if ($body_tags->length > 0) {
            $text_len = utf8_strlen(trim($body_tags->item(0)->textContent));
            if ($text_len < 200) $seo_data['seo_issues'][] = 'Low content (<200)';
        }
        
        // Slow load
        if ($seo_data['response_time'] > $config['slow_page_threshold']) {
            $seo_data['seo_issues'][] = "Slow ({$seo_data['response_time']}s)";
        }
    }
    
    return $seo_data;
}

// --- Вывод интерфейса ---

function output_results($results, $format) {
    global $user_agents, $config;
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return;
    }
    // HTML Вывод
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sitemap SEO Checker v2.1</title>
        <style>
            :root {
                --primary: #3498db;
                --success: #27ae60;
                --danger: #e74c3c;
                --warning: #f39c12;
                --light: #f8f9fa;
                --dark: #2c3e50;
            }
            * { box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; background: #ecf0f1; color: #333; margin: 0; }
            
            .container { 
                max-width: 1600px; 
                margin: 0 auto; 
                background: white; 
                padding: 20px; 
                border-radius: 8px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            }
            
            h1 { color: var(--dark); margin-top: 0; font-size: 1.5rem; border-bottom: 2px solid var(--primary); padding-bottom: 10px; }
            
            /* Controls */
            .controls { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
            input[type="text"], select { padding: 10px; border: 1px solid #ced4da; border-radius: 4px; flex-grow: 1; min-width: 200px; }
            button { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
            button:hover { filter: brightness(90%); }
            button.export-btn { background: var(--success); }
            button.stop-btn { background: var(--danger); display: none; }
            button.stop-btn.active { display: inline-block; }
            
            /* Advanced Settings */
            .advanced-settings {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                padding: 15px;
                margin-bottom: 20px;
                display: none;
            }
            .advanced-settings.show {
                display: block;
            }
            .settings-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            .setting-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .setting-group label {
                font-size: 12px;
                color: #666;
                font-weight: 600;
            }
            .toggle-advanced {
                background: none;
                border: none;
                color: var(--primary);
                cursor: pointer;
                padding: 5px 0;
                font-size: 12px;
                text-align: left;
                margin-top: 10px;
            }
            .toggle-advanced:hover {
                text-decoration: underline;
            }
            
            /* Stats Grid */
            .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
            .stat-card { background: var(--light); padding: 10px; border-radius: 4px; border-left: 3px solid var(--primary); }
            .stat-card h3 { margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; color: #7f8c8d; }
            .stat-value { font-size: 18px; font-weight: bold; }
            
            /* Table Wrapper for Responsiveness */
            .table-wrapper { 
                overflow-x: auto; 
                -webkit-overflow-scrolling: touch; 
                margin-top: 20px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
            
            table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1000px; /* Force scroll on small screens */ }
            th, td { border-bottom: 1px solid #dee2e6; padding: 8px 12px; text-align: left; vertical-align: top; }
            th { background: #e9ecef; position: sticky; top: 0; font-weight: 600; color: var(--dark); z-index: 10; }
            tr:hover { background-color: #f1f1f1; }
            
            /* Status Badges */
            .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-right: 4px; margin-bottom: 2px; }
            .bg-success { background: #d4edda; color: #155724; }
            .bg-danger { background: #f8d7da; color: #721c24; }
            .bg-warning { background: #fff3cd; color: #856404; }
            .bg-info { background: #d1ecf1; color: #0c5460; }
            
            .seo-issue { background: #ffeeba; color: #856404; font-size: 11px; padding: 1px 5px; border-radius: 3px; display: inline-block; margin: 1px; border: 1px solid #ffe8a1; }
            
            /* Progress Bar */
            .progress { height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin: 15px 0; display: none; }
            .progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.2s; }
            
            /* Filters */
            .filter-controls { background: var(--light); padding: 10px; border-radius: 4px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; font-size: 13px; }
            select { padding: 4px; border-radius: 3px; border: 1px solid #ced4da; }

            /* Mobile Adaptation */
            @media (max-width: 768px) {
                body { padding: 10px; }
                .container { padding: 10px; }
                .controls { flex-direction: column; }
                .controls button { width: 100%; }
                .filter-controls { flex-direction: column; align-items: flex-start; }
                h1 { font-size: 1.2rem; }
                .settings-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 Sitemap Checker Pro <small style="font-size: 12px; color: #7f8c8d; font-weight: normal;">v2.1 Advanced</small></h1>
            
            <div class="controls">
                <input type="text" id="sitemapUrl" placeholder="URL sitemap.xml (например: https://site.com/sitemap.xml)" value="">
                <select id="userAgent">
                    <?php foreach ($user_agents as $name => $ua): ?>
                        <option value="<?php echo htmlspecialchars($ua); ?>" <?php echo $ua === $config['user_agent'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="startCheck()">🚀 Проверить</button>
                <button class="stop-btn" id="stopBtn" onclick="stopScan()">⏹ Стоп</button>
                <button class="export-btn" onclick="exportCSV()">💾 CSV</button>
            </div>
            
            <button class="toggle-advanced" onclick="toggleAdvanced()">⚙️ Дополнительные настройки</button>
            
            <div class="advanced-settings" id="advancedSettings">
                <h3 style="margin-top: 0; font-size: 14px;">Настройки сканирования</h3>
                <div class="settings-grid">
                    <div class="setting-group">
                        <label for="scanSpeed">Скорость сканирования (сек):</label>
                        <input type="number" id="scanSpeed" min="<?php echo $config['min_scan_speed']; ?>" max="<?php echo $config['max_scan_speed']; ?>" step="0.1" value="<?php echo $config['scan_speed']; ?>">
                    </div>
                    <div class="setting-group">
                        <label for="maxUrls">Макс. URL для проверки:</label>
                        <input type="number" id="maxUrls" min="<?php echo $config['min_urls']; ?>" max="1000" value="<?php echo $config['max_urls']; ?>">
                    </div>
                    <div class="setting-group">
                        <label for="minUrls">Мин. URL для проверки:</label>
                        <input type="number" id="minUrls" min="1" max="1000" value="<?php echo $config['min_urls']; ?>">
                    </div>
                    <div class="setting-group">
                        <label for="timeout">Таймаут (сек):</label>
                        <input type="number" id="timeout" min="1" max="60" value="<?php echo $config['timeout']; ?>">
                    </div>
                    <div class="setting-group">
                        <label for="excludePatterns">Исключить URL (регулярки):</label>
                        <textarea id="excludePatterns" rows="2" placeholder="/admin, /login"><?php echo implode(', ', $config['exclude_patterns']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="stats" id="statsPanel"></div>
            
            <div class="progress" id="progressContainer">
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <div class="filter-controls">
                <label>Статус: 
                    <select id="filterStatus" onchange="filterResults()">
                        <option value="all">Все</option>
                        <option value="noindex">Noindex</option>
                        <option value="nofollow">Nofollow (Page)</option>
                        <option value="errors">Ошибки (4xx/5xx)</option>
                        <option value="slow">Медленные</option>
                    </select>
                </label>
                <label><input type="checkbox" id="showSeoIssues" onchange="filterResults()"> Только проблемы</label>
                <label>Язык:
                    <select id="filterLanguage" onchange="filterResults()">
                        <option value="all">Любой</option>
                        <option value="ru">RU</option>
                        <option value="en">EN</option>
                    </select>
                </label>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">URL</th>
                            <th>Robots</th>
                            <th>HTTP</th>
                            <th>Time</th>
                            <th>Links (NF)</th>
                            <th>Lang</th>
                            <th style="width: 20%;">Title / H1</th>
                            <th style="width: 20%;">Issues</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <tr><td colspan="8" style="text-align: center; color: #777; padding: 20px;">Введите URL карты сайта и нажмите Проверить</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        let allResults = [];
        let isScanning = false;
        let stopRequested = false;
        
        function toggleAdvanced() {
            document.getElementById('advancedSettings').classList.toggle('show');
        }
        
        async function startCheck() {
            if (isScanning) {
                alert('Сканирование уже выполняется');
                return;
            }
            
            const urlInput = document.getElementById('sitemapUrl');
            const url = urlInput.value.trim();
            if (!url) { urlInput.focus(); return alert('Укажите URL!'); }
            
            // Получаем настройки из формы
            const settings = {
                user_agent: document.getElementById('userAgent').value,
                scan_speed: parseFloat(document.getElementById('scanSpeed').value),
                max_urls: parseInt(document.getElementById('maxUrls').value),
                min_urls: parseInt(document.getElementById('minUrls').value),
                timeout: parseInt(document.getElementById('timeout').value),
                exclude_patterns: document.getElementById('excludePatterns').value
                    .split(',')
                    .map(p => p.trim())
                    .filter(p => p)
            };
            
            // Валидация
            if (settings.max_urls < settings.min_urls) {
                return alert('Максимальное количество URL не может быть меньше минимального!');
            }
            if (settings.scan_speed < 0 || settings.scan_speed > 10) {
                return alert('Скорость сканирования должна быть от 0 до 10 секунд');
            }
            
            resetScanState();
            document.getElementById('stopBtn').classList.add('active');
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('resultsBody').innerHTML = '<tr><td colspan="8" style="text-align: center;">📥 Загрузка карты сайта...</td></tr>';
            
            try {
                const fd = new FormData();
                fd.append('action', 'parse_sitemap');
                fd.append('url', url);
                
                const resp = await fetch('?', { method: 'POST', body: fd });
                const data = await resp.json();
                
                if (data.error) throw new Error(data.error);
                if (!data.urls || data.urls.length === 0) throw new Error('URL не найдены в sitemap');
                
                // Применяем фильтрацию исключений
                if (settings.exclude_patterns.length > 0) {
                    const fdFilter = new FormData();
                    fdFilter.append('action', 'filter_urls');
                    fdFilter.append('urls', JSON.stringify(data.urls));
                    fdFilter.append('patterns', JSON.stringify(settings.exclude_patterns));
                    
                    const filterResp = await fetch('?', { method: 'POST', body: fdFilter });
                    const filterData = await filterResp.json();
                    
                    if (filterData.error) throw new Error(filterData.error);
                    if (filterData.urls) {
                        data.urls = filterData.urls;
                    }
                }
                
                // Проверяем ограничение по количеству URL
                if (data.urls.length > settings.max_urls) {
                    data.urls = data.urls.slice(0, settings.max_urls);
                }
                
                if (data.urls.length < settings.min_urls) {
                    throw new Error(`Найдено только ${data.urls.length} URL, требуется минимум ${settings.min_urls}`);
                }
                
                await processUrls(data.urls, settings);
                
            } catch (e) {
                alert('Ошибка: ' + e.message);
                resetScanState();
            }
        }
        
        function resetScanState() {
            isScanning = false;
            stopRequested = false;
            document.getElementById('stopBtn').classList.remove('active');
            document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('progressBar').style.width = '0%';
        }
        
        async function processUrls(urls, settings) {
            allResults = [];
            isScanning = true;
            const total = urls.length;
            let processed = 0;
            
            if (total > 100 && !confirm(`Найдено ${total} страниц. Это займет время. Продолжить?`)) {
                resetScanState();
                return;
            }
            
            document.getElementById('resultsBody').innerHTML = '';
            
            for (const url of urls) {
                if (stopRequested) {
                    alert('Сканирование остановлено пользователем');
                    break;
                }
                
                const fd = new FormData();
                fd.append('action', 'check_page');
                fd.append('url', url);
                fd.append('user_agent', settings.user_agent);
                fd.append('timeout', settings.timeout);
                
                try {
                    const resp = await fetch('?', { method: 'POST', body: fd });
                    const data = await resp.json();
                    allResults.push(data);
                    
                    processed++;
                    updateProgress(processed, total);
                    updateStats();
                    
                    addResultRow(data);
                    
                    // Задержка между запросами
                    if (settings.scan_speed > 0) {
                        await new Promise(resolve => setTimeout(resolve, settings.scan_speed * 1000));
                    }
                    
                } catch (e) {
                    console.error(e);
                }
            }
            
            isScanning = false;
            document.getElementById('stopBtn').classList.remove('active');
            document.getElementById('progressContainer').style.display = 'none';
            
            if (stopRequested) {
                alert(`Сканирование остановлено. Проверено ${processed} из ${total} страниц.`);
            } else {
                alert(`Сканирование завершено! Проверено ${processed} страниц.`);
            }
        }
        
        function stopScan() {
            if (!isScanning) return;
            
            stopRequested = true;
            document.getElementById('stopBtn').textContent = '⏹ Останавливается...';
            
            // Также отправляем запрос на сервер для установки флага остановки
            fetch('?', {
                method: 'POST',
                body: new FormData()
            }).then(() => {
                setTimeout(() => {
                    document.getElementById('stopBtn').textContent = '⏹ Стоп';
                }, 500);
            });
        }
        
        function updateProgress(curr, total) {
            document.getElementById('progressBar').style.width = (curr / total * 100) + '%';
        }
        
        function updateStats() {
            const s = {
                total: allResults.length,
                noindex: allResults.filter(r => r.is_noindex).length,
                nofollow: allResults.filter(r => r.is_nofollow).length,
                errors: allResults.filter(r => r.http_code >= 400 || r.http_code === 0).length,
                issues: allResults.filter(r => r.seo_issues.length > 0).length
            };
            
            document.getElementById('statsPanel').innerHTML = `
                <div class="stat-card"><h3>Всего</h3><div class="stat-value">${s.total}</div></div>
                <div class="stat-card" style="border-color: ${s.noindex ? '#e74c3c' : '#27ae60'}"><h3>Noindex</h3><div class="stat-value">${s.noindex}</div></div>
                <div class="stat-card" style="border-color: ${s.nofollow ? '#f39c12' : '#27ae60'}"><h3>Nofollow</h3><div class="stat-value">${s.nofollow}</div></div>
                <div class="stat-card" style="border-color: ${s.errors ? '#e74c3c' : '#27ae60'}"><h3>Errors</h3><div class="stat-value">${s.errors}</div></div>
                <div class="stat-card"><h3>Issues</h3><div class="stat-value">${s.issues}</div></div>
            `;
        }

        function getRowHtml(r) {
            const isError = r.http_code >= 400 || r.http_code === 0;
            const issuesHtml = r.seo_issues.map(i => `<span class="seo-issue">${i}</span>`).join(' ');
            
            let robotsBadges = '';
            if (r.is_noindex) robotsBadges += '<span class="badge bg-danger">NOINDEX</span>';
            else robotsBadges += '<span class="badge bg-success">INDEX</span>';
            
            if (r.is_nofollow) robotsBadges += '<br><span class="badge bg-warning">NOFOLLOW</span>';
            else robotsBadges += '<span class="badge bg-success">FOLLOW</span>';

            const linkInfo = r.links_total > 0 
                ? `${r.links_total} <small style="color:${r.links_nofollow > 0 ? 'red' : '#aaa'}">(${r.links_nofollow} nf)</small>`
                : '0';

            return `
                <td>
                    <a href="${r.url}" target="_blank" style="font-weight:bold; color:#3498db; text-decoration:none; display:block; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${r.url}</a>
                </td>
                <td>${robotsBadges}</td>
                <td><span class="badge ${isError ? 'bg-danger' : 'bg-success'}">${r.http_code}</span></td>
                <td style="color: ${r.response_time > 3 ? 'red' : 'inherit'}">${r.response_time}s</td>
                <td>${linkInfo}</td>
                <td><span class="badge bg-info">${r.language.toUpperCase()}</span></td>
                <td style="font-size:12px;">
                    <div title="${r.title}" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><b>T:</b> ${r.title || '-'}</div>
                    <div style="color:#777;"><b>H1:</b> ${r.h1_count}</div>
                </td>
                <td>${issuesHtml}</td>
            `;
        }

        function addResultRow(data) {
            if (!shouldShow(data)) return;
            
            const tr = document.createElement('tr');
            tr.innerHTML = getRowHtml(data);
            document.getElementById('resultsBody').appendChild(tr);
        }

        function filterResults() {
            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            
            if (allResults.length === 0) return;

            allResults.forEach(r => {
                if (shouldShow(r)) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = getRowHtml(r);
                    tbody.appendChild(tr);
                }
            });
        }

        function shouldShow(r) {
            const status = document.getElementById('filterStatus').value;
            const onlyIssues = document.getElementById('showSeoIssues').checked;
            const lang = document.getElementById('filterLanguage').value;

            if (status === 'noindex' && !r.is_noindex) return false;
            if (status === 'nofollow' && !r.is_nofollow) return false;
            if (status === 'errors' && (r.http_code < 400 && r.http_code !== 0)) return false;
            if (status === 'slow' && r.response_time <= 3) return false;
            
            if (onlyIssues && r.seo_issues.length === 0) return false;
            if (lang !== 'all' && r.language !== lang) return false;

            return true;
        }
        
        function exportCSV() {
            if (allResults.length === 0) return alert('Нет данных');
            
            const headers = ['URL', 'Robots', 'HTTP', 'Time', 'Total Links', 'Nofollow Links', 'Title', 'Issues'];
            const rows = allResults.map(r => [
                r.url,
                (r.is_noindex ? 'NOINDEX' : 'INDEX') + ' / ' + (r.is_nofollow ? 'NOFOLLOW' : 'FOLLOW'),
                r.http_code,
                r.response_time,
                r.links_total,
                r.links_nofollow,
                `"${(r.title || '').replace(/"/g, '""')}"`,
                `"${r.seo_issues.join('; ')}"`
            ]);
            
            const csvContent = [headers.join(',')].concat(rows.map(e => e.join(','))).join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "sitemap_audit.csv";
            link.click();
        }
        </script>
    </body>
    </html>
    <?php
}

// --- Обработчик AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'parse_sitemap') {
        echo json_encode(parse_sitemap($_POST['url'] ?? '', $config));
        exit;
    }
    
    if ($action === 'filter_urls') {
        $urls = json_decode($_POST['urls'] ?? '[]', true);
        $patterns = json_decode($_POST['patterns'] ?? '[]', true);
        
        if (!is_array($urls) || !is_array($patterns)) {
            echo json_encode(['error' => 'Неверные данные']);
            exit;
        }
        
        $config['exclude_patterns'] = $patterns;
        $filtered_urls = filter_urls($urls, $config);
        
        echo json_encode(['urls' => $filtered_urls]);
        exit;
    }
    
    if ($action === 'check_page') {
        // Обновляем конфиг из POST запроса
        if (!empty($_POST['user_agent'])) {
            $config['user_agent'] = $_POST['user_agent'];
        }
        if (!empty($_POST['timeout'])) {
            $config['timeout'] = (int)$_POST['timeout'];
        }
        
        echo json_encode(check_page_seo($_POST['url'] ?? '', $config));
        exit;
    }
    
    // Обработчик остановки сканирования
    if ($action === 'stop_scan') {
        $stop_scan = true;
        echo json_encode(['status' => 'stopped']);
        exit;
    }
}

// Если GET запрос, рисуем страницу
output_results([], $config['output_format']);
