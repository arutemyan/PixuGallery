<?php
require_once __DIR__ . '/tab_utils.php';
App\Admin\Tabs\checkAccess();

// Config読み込み
$config = \App\Config\ConfigManager::getInstance()->getConfig();

/**
 * 機密情報をマスクする
 */
function maskSensitiveData(array $config, string $parentKey = ''): array
{
    $sensitiveKeys = [
        'password', 'user', 'dbname', 'database', 'username', 
        'passwd', 'secret', 'token', 'api_key', 'apikey',
        'private_key', 'auth', 'credentials', 'salt', 'hash',
        'port', 'host', 'hostname', 'endpoint'
    ];

    $masked = [];
    foreach ($config as $key => $value) {
        $fullKey = $parentKey ? $parentKey . '.' . $key : $key;

        // 機密キーワードのチェック（大文字小文字を区別しない）
        $isSensitive = false;
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (stripos($key, $sensitiveKey) !== false) {
                $isSensitive = true;
                break;
            }
        }

        if (is_array($value)) {
            $masked[$key] = maskSensitiveData($value, $fullKey);
        } else if ($isSensitive) {
            $masked[$key] = '[[[[--MASKED--]]]]';
        } else {
            $masked[$key] = $value;
        }
    }

    return $masked;
}

/**
 * 配列を再帰的にHTMLテーブルで表示
 */
function renderConfigTable(array $config, int $depth = 0): string
{
    $html = '';
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);

    foreach ($config as $key => $value) {
        if (is_array($value)) {
            $html .= '<tr class="table-section">';
            $html .= '<td class="config-key"><strong>' . $indent . escapeHtml($key) . '</strong></td>';
            $html .= '<td class="config-value text-muted">[配列 - ' . count($value) . ' 項目]</td>';
            $html .= '<td class="config-type">array</td>';
            $html .= '</tr>';
            $html .= renderConfigTable($value, $depth + 1);
        } else {
            $type = gettype($value);
            $displayValue = $value;

            if (is_bool($value)) {
                $displayValue = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $displayValue = '<span class="badge bg-secondary">null</span>';
            } elseif (is_string($value) && $value === '[[[[--MASKED--]]]]') {
                $displayValue = '********';
            } else {
                $displayValue = '<code>' . escapeHtml(var_export($value, true)) . '</code>';
            }

            $html .= '<tr>';
            $html .= '<td class="config-key">' . $indent . escapeHtml($key) . '</td>';
            $html .= '<td class="config-value">' . $displayValue . '</td>';
            $html .= '<td class="config-type"><small class="text-muted">' . escapeHtml($type) . '</small></td>';
            $html .= '</tr>';
        }
    }

    return $html;
}

$maskedConfig = maskSensitiveData($config);

/**
 * PHP運用パラメータを取得
 */
function getPhpRuntimeParams(): array
{
    return [
        'PHP情報' => [
            'PHPバージョン' => PHP_VERSION,
            'Zend Engine' => zend_version(),
            'SAPI' => php_sapi_name(),
        ],
        '実行制限' => [
            'max_execution_time' => ini_get('max_execution_time') . ' 秒',
            'max_input_time' => ini_get('max_input_time') . ' 秒',
            'memory_limit' => ini_get('memory_limit'),
        ],
        'アップロード設定' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads') ? 'ON' : 'OFF',
        ],
        'エラー設定' => [
            'display_errors' => ini_get('display_errors') ? 'ON' : 'OFF',
            'display_startup_errors' => ini_get('display_startup_errors') ? 'ON' : 'OFF',
            'error_reporting' => ini_get('error_reporting'),
            'log_errors' => ini_get('log_errors') ? 'ON' : 'OFF',
            'error_log' => ini_get('error_log') ?: '(未設定)',
        ],
        'セッション設定' => [
            'session.save_handler' => ini_get('session.save_handler'),
            'session.save_path' => ini_get('session.save_path'),
            'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime') . ' 秒',
            'session.cookie_lifetime' => ini_get('session.cookie_lifetime') . ' 秒',
        ],
        'その他' => [
            'date.timezone' => ini_get('date.timezone') ?: '(未設定)',
            'default_charset' => ini_get('default_charset'),
            'mbstring.internal_encoding' => ini_get('mbstring.internal_encoding'),
            'mbstring.http_output' => ini_get('mbstring.http_output'),
        ],
    ];
}

/**
 * OPcache情報を取得
 */
function getOpcacheInfo(): ?array
{
    if (!function_exists('opcache_get_status')) {
        return null;
    }

    $status = opcache_get_status(false);
    if ($status === false) {
        return ['状態' => '無効'];
    }

    $config = opcache_get_configuration();

    return [
        '状態' => $status['opcache_enabled'] ? '有効' : '無効',
        'メモリ使用量' => round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . ' MB / ' .
                          round($config['directives']['opcache.memory_consumption'] / 1024 / 1024, 2) . ' MB',
        'キャッシュヒット率' => isset($status['opcache_statistics']['hits']) && isset($status['opcache_statistics']['misses']) ?
            round($status['opcache_statistics']['hits'] / ($status['opcache_statistics']['hits'] + $status['opcache_statistics']['misses']) * 100, 2) . '%' : 'N/A',
        'キャッシュされたスクリプト数' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
        'Max cached keys' => $status['opcache_statistics']['max_cached_keys'] ?? 0,
    ];
}

$phpRuntimeParams = getPhpRuntimeParams();
$opcacheInfo = getOpcacheInfo();
?>

<style>
    .config-key {
        font-weight: 500;
        white-space: nowrap;
    }
    .config-value {
        word-break: break-all;
    }
    .config-type {
        width: 100px;
    }
    .table-section td {
        background-color: #f8f9fa;
        border-top: 2px solid #dee2e6;
    }
    pre {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        max-height: 600px;
        overflow: auto;
    }
    .view-toggle {
        margin-bottom: 20px;
    }
</style>

<!-- 警告 -->
<div class="row mt-4">
    <div class="col">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>注意:</strong> この情報は機密性が高いため、スクリーンショットや外部への共有は避けてください。
        </div>
    </div>
</div>


<div class="row mb-3">
    <div class="col">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            PHPの運用パラメータとアプリケーション設定を表示します。機密情報は自動的にマスクされます。
        </div>
    </div>
</div>

<!-- PHP Runtime Parameters -->
<div class="row mb-4">
    <div class="col">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-gear-fill me-2"></i>PHP運用パラメータ
            </div>
            <div class="card-body">
                <?php foreach ($phpRuntimeParams as $section => $params): ?>
                    <h6 class="mt-3 mb-2 text-primary">
                        <i class="bi bi-caret-right-fill me-1"></i><?= escapeHtml($section) ?>
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-3">
                            <tbody>
                                <?php foreach ($params as $key => $value): ?>
                                    <tr>
                                        <td class="config-key" style="width: 40%;"><?= escapeHtml($key) ?></td>
                                        <td class="config-value"><code><?= escapeHtml($value) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php if ($opcacheInfo !== null): ?>
                    <h6 class="mt-3 mb-2 text-primary">
                        <i class="bi bi-caret-right-fill me-1"></i>OPcache
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                                <?php foreach ($opcacheInfo as $key => $value): ?>
                                    <tr>
                                        <td class="config-key" style="width: 40%;"><?= escapeHtml($key) ?></td>
                                        <td class="config-value"><code><?= escapeHtml($value) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<!-- Application Config Section -->
<div class="row mb-3">
    <div class="col">
        <h5 class="text-secondary">
            <i class="bi bi-file-code me-2"></i>アプリケーション設定 (config/config.php)
        </h5>
    </div>
</div>

<!-- 表示切り替え -->
<div class="row mb-3">
    <div class="col">
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="configViewMode" id="configViewTable" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="configViewTable">
                <i class="bi bi-table me-1"></i>テーブル表示
            </label>

            <input type="radio" class="btn-check" name="configViewMode" id="configViewJson" autocomplete="off">
            <label class="btn btn-outline-primary" for="configViewJson">
                <i class="bi bi-code-square me-1"></i>JSON表示
            </label>
        </div>
    </div>
</div>

<!-- テーブル表示 -->
<div id="configTableView" class="row">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>設定一覧
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>キー</th>
                                <th>値</th>
                                <th>型</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderConfigTable($maskedConfig) ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JSON表示 -->
<div id="configJsonView" class="row" style="display: none;">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-code-square me-2"></i>JSON形式</span>
            </div>
            <div class="card-body">
                <pre><code id="configJsonContent"><?= escapeHtml(json_encode($maskedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
            </div>
        </div>
    </div>
</div>

<script>
// このタブ専用のスクリプト
(function() {
    // 表示切り替え
    document.getElementById('configViewTable')?.addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('configTableView').style.display = 'block';
            document.getElementById('configJsonView').style.display = 'none';
        }
    });

    document.getElementById('configViewJson')?.addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('configTableView').style.display = 'none';
            document.getElementById('configJsonView').style.display = 'block';
        }
    });
})();
</script>
