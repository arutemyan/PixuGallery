<?php

declare(strict_types=1);

/**
 * 統合設定ローダー
 *
 * このファイルは全ての設定を一元管理します
 * 実際の設定は config.default.php と config.local.php で管理されます
 */

require_once __DIR__ . '/loader.php';

return loadConfig('config', __DIR__);
