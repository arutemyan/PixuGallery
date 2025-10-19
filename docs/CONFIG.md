# 設定ファイルガイド

## ファイル構成

```
config/
├── config.php                  # 統合設定ローダー
├── config.default.php          # デフォルト設定（Git管理）
├── config.local.php            # ローカル設定（gitignore）
└── config.local.php.example    # 設定例
```

## 基本的な仕組み

- `config.default.php` - 全環境共通のデフォルト設定
- `config.local.php` - 環境固有の設定で上書き（オプション）
- 自動マージ: デフォルト設定 + ローカル設定 = 最終設定

## 使い方

### 1. ローカル設定ファイルを作成

```bash
cp config/config.local.php.example config/config.local.php
```

### 2. 必要な設定のみを記述

`config.local.php` には変更したい設定のみを記述します：

```php
<?php
return [
    'nsfw' => [
        'age_verification_minutes' => 1, // 開発時は1分
    ],
    'cache' => [
        'enabled' => false, // 開発時は無効
    ],
    'security' => [
        'https' => [
            'force' => false, // HTTPで開発
        ],
    ],
];
```

### 3. コードから設定を読み込む

```php
// 全設定を取得
$config = require __DIR__ . '/../config/config.php';

// セクションごとにアクセス
$nsfwConfig = $config['nsfw'];
$cacheDir = $config['cache']['cache_dir'];
```

## 主要な設定項目

### データベース設定（database）

| 項目 | 説明 | デフォルト |
|-----|------|-----------|
| `gallery.path` | メインDB | `data/gallery.db` |
| `counters.path` | カウンターDB | `data/counters.db` |
| `access_logs.path` | ログDB | `data/access_logs.db` |

### キャッシュ設定（cache）

| 項目 | 説明 | デフォルト |
|-----|------|-----------|
| `cache_dir` | キャッシュディレクトリ | `cache/` |
| `enabled` | 有効/無効 | `true` |
| `default_ttl` | 有効期限（秒） | `0`（無期限） |

### NSFW設定（nsfw）

| 項目 | 説明 | デフォルト |
|-----|------|-----------|
| `config_version` | 設定バージョン | `6` |
| `age_verification_minutes` | 年齢確認の有効期限 | `10080`（7日） |
| `filter_type` | フィルター種類 | `frosted` |
| `blur_settings` | ぼかし効果設定 | - |
| `frosted_settings` | すりガラス効果設定 | - |

### セキュリティ設定（security）

| 項目 | 説明 | デフォルト |
|-----|------|-----------|
| `https.force` | HTTPS強制 | `false` |
| `https.hsts_enabled` | HSTS有効化 | `false` |
| `csp.enabled` | CSP有効化 | `false` |
| `session.cookie_secure` | Secure属性 | `true` |

## よくある設定例

### 開発環境

```php
return [
    'nsfw' => ['age_verification_minutes' => 1],
    'cache' => ['enabled' => false],
    'security' => [
        'https' => ['force' => false],
        'session' => ['cookie_secure' => false],
    ],
];
```

### 本番環境

```php
return [
    'cache' => ['cache_dir' => '/var/cache/photo-site'],
    'security' => [
        'https' => [
            'force' => true,
            'hsts_enabled' => true,
        ],
        'csp' => ['enabled' => true],
    ],
];
```

## 注意事項

- `config.local.php` は **Gitにコミットしない**（.gitignoreに含まれます）
- `config.default.php` には **機密情報を含めない**（Git管理されます）
- 設定変更後は構文エラーがないか確認: `php -l config/config.local.php`

## トラブルシューティング

| 問題 | 解決方法 |
|-----|---------|
| 設定が反映されない | ファイルの場所とパーミッションを確認 |
| デフォルトに戻したい | `config.local.php` を削除またはリネーム |
| 特定の設定だけリセット | `config.local.php` から該当セクションを削除 |
