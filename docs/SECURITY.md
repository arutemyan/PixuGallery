# セキュリティガイド

このドキュメントでは、pixugallery アプリケーションのセキュリティ機能と推奨設定について説明します。

## 目次

1. [セキュリティ機能の概要](#セキュリティ機能の概要)
2. [SQLインジェクション対策](#sqlインジェクション対策)
3. [XSS対策](#xss対策)
4. [CSRF対策](#csrf対策)
5. [セッション管理](#セッション管理)
6. [CORS設定](#cors設定)
7. [認証・認可](#認証認可)
8. [レート制限](#レート制限)
9. [ファイルアップロード](#ファイルアップロード)
10. [セキュリティヘッダー](#セキュリティヘッダー)
11. [本番環境での推奨設定](#本番環境での推奨設定)
12. [セキュリティ監査ログ](#セキュリティ監査ログ)

---

## セキュリティ機能の概要

このアプリケーションは、以下のセキュリティ機能を実装しています：

- **SQLインジェクション対策**: PDO Prepared Statements による完全なパラメータバインディング
- **XSS対策**: すべての出力に対する適切なエスケープ処理
- **CSRF対策**: トークンベースの CSRF 保護
- **セッション管理**: セキュアなセッション設定とセッション固定攻撃対策
- **CORS設定**: 設定可能なオリジンベースの CORS 制御
- **レート制限**: ブルートフォース攻撃対策のためのレート制限
- **ファイルアップロード検証**: MIME タイプとファイル拡張子の検証
- **パストラバーサル対策**: ファイルパスの検証
- **セキュリティヘッダー**: CSP、HSTS、X-Frame-Options など

---

## SQLインジェクション対策

### 実装内容

すべてのデータベースクエリは PDO Prepared Statements を使用しています。

**良い例**:
```php
// ✅ Prepared Statement を使用
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
```

**悪い例**:
```php
// ❌ 文字列連結は絶対に使用しない
$sql = "SELECT * FROM posts WHERE id = " . $id;
$stmt = $db->query($sql);
```

### PostgreSQL のスキーマ設定

PostgreSQL を使用する場合、スキーマ名は厳密に検証されます：

```php
// スキーマ名のバリデーション
if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
    throw new \PDOException("Invalid PostgreSQL schema name");
}
```

### 検証項目

- ✅ すべての動的パラメータは Prepared Statements でバインド
- ✅ PostgreSQL スキーマ名の検証
- ✅ LIKE クエリのワイルドカードエスケープ
- ✅ `PDO::ATTR_EMULATE_PREPARES => false` を設定

---

## XSS対策

### HTML エスケープ

すべてのユーザー入力を出力する際は `escapeHtml()` 関数を使用：

```php
// テンプレート内での使用
<?= escapeHtml($userInput) ?>
```

### 実装

```php
function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
```

### JSON レスポンス

JSON API は自動的に適切にエスケープされます（`json_encode` の使用）。

---

## CSRF対策

### 実装内容

すべての状態変更操作（POST、PUT、DELETE、PATCH）には CSRF トークン検証が必要です。

### 使用方法

**フォームでの使用**:
```php
// トークン生成
$csrfToken = CsrfProtection::generateToken();

// HTMLフォーム
<input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
```

**JavaScript での使用**:
```javascript
// ヘッダーで送信
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(data)
});
```

### 検証

管理画面 API では自動的に検証されます：

```php
// AdminControllerBase で自動検証
protected function validateCsrf(): void
{
    if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
        $this->sendError('CSRFトークンが無効です', 403);
    }
}
```

---

## セッション管理

### セキュアな設定

```php
// config/config.default.php
'session' => [
    'cookie_lifetime' => 0,
    'cookie_secure' => true,      // HTTPS のみ
    'cookie_httponly' => true,    // JavaScript からアクセス不可
    'cookie_samesite' => 'Strict', // CSRF 対策
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'force_secure_cookie' => false, // 本番環境では true を推奨
],
```

### セッション固定攻撃対策

ログイン時にセッション ID を再生成：

```php
$sess->regenerate(true); // 古いセッションを削除
```

### セッションタイムアウト

```php
ini_set('session.gc_maxlifetime', '3600'); // 1時間
```

### 暗号化されたセッション ID

- AES-256-GCM によるセッション ID のマスキング
- キーローテーション機能
- 複数キーによる復号化サポート

---

## CORS設定

### 基本設定

```php
// config/config.default.php
'cors' => [
    'enabled' => true,
    // 本番環境では具体的なドメインを指定
    'allowed_origins' => ['https://example.com'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'X-CSRF-Token'],
    'allow_credentials' => false,
    'max_age' => 3600,
],
```

### セキュリティ上の注意

**開発環境**:
```php
'allowed_origins' => ['*'], // すべてのオリジンを許可
'allow_credentials' => false, // * の場合は必ず false
```

**本番環境**:
```php
'allowed_origins' => [
    'https://example.com',
    'https://www.example.com'
],
'allow_credentials' => true, // Cookie を含むリクエストを許可
```

### 動的オリジン検証

```php
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}
```

---

## 認証・認可

### 管理画面の認証

```php
// 自動認証チェック
class AdminControllerBase extends ControllerBase
{
    protected function checkAuthentication(): void
    {
        if ($sess->get('admin_logged_in') !== true) {
            $this->sendError('Unauthorized', 403);
        }
    }
}
```

### パスワードハッシュ

```php
// パスワードのハッシュ化
$hash = password_hash($password, PASSWORD_DEFAULT);

// パスワードの検証
if (password_verify($password, $hash)) {
    // 認証成功
}
```

### パスワード要件

- 最低8文字
- 小文字、大文字、数字を各1文字以上含む

---

## レート制限

### ログイン試行の制限

```php
// 15分間で5回まで
$rateLimiter = new RateLimiter($dir, 5, 900);

if (!$rateLimiter->check($clientIp, 'login')) {
    // レート制限超過
    $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'login');
    http_response_code(429);
}
```

### API レート制限

```php
// 1分間に100リクエストまで
$rateLimiter = new RateLimiter($dir, 100, 60);
```

### Retry-After ヘッダー

```php
if ($retryAfter) {
    header('Retry-After: ' . ($retryAfter - time()));
}
```

---

## ファイルアップロード

### 検証項目

1. **MIME タイプ検証**（finfo_file 使用）
2. **ファイルサイズ制限**
3. **拡張子とMIMEタイプの整合性チェック**
4. **PHPファイルアップロード防止**
5. **is_uploaded_file によるアップロード検証**

### 実装例

```php
$validation = validateFileUpload($_FILES['file'], 10, [
    'image/jpeg',
    'image/png',
    'image/webp'
]);

if (!$validation['valid']) {
    throw new Exception($validation['error']);
}
```

### 禁止拡張子

```php
$forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'];
```

---

## セキュリティヘッダー

### 実装内容

```php
// X-Content-Type-Options
header('X-Content-Type-Options: nosniff');

// X-Frame-Options
header('X-Frame-Options: SAMEORIGIN');

// X-XSS-Protection
header('X-XSS-Protection: 1; mode=block');

// Referrer-Policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// HSTS (HTTPS時のみ)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Permissions-Policy
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
```

### Content-Security-Policy

```php
'csp' => [
    'enabled' => true,
    'report_only' => false, // 本番前はtrueでテスト
],
```

**公開ページ（厳格）**:
```
default-src 'self';
script-src 'self' 'unsafe-inline' cdn.jsdelivr.net;
style-src 'self' 'unsafe-inline' cdn.jsdelivr.net;
img-src 'self' data: blob:;
```

**管理画面（緩和）**:
```
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net;
img-src 'self' data: blob: https:;
```

---

## 本番環境での推奨設定

### config.local.php の作成

```php
<?php
return [
    'app' => [
        'environment' => 'production',
        'use_bundled_assets' => true,
    ],
    
    'admin' => [
        // 推測されにくいパスに変更
        'path' => 'xK9mP2nQ7_admin',
    ],
    
    'security' => [
        'https' => [
            'force' => true,           // HTTPS を強制
            'hsts_enabled' => true,    // HSTS を有効化
            'hsts_max_age' => 31536000,
        ],
        
        'csp' => [
            'enabled' => true,
            'report_only' => false,
        ],
        
        'session' => [
            'force_secure_cookie' => true, // 常に secure cookie
        ],
        
        'cors' => [
            'allowed_origins' => [
                'https://example.com',
                'https://www.example.com'
            ],
            'allow_credentials' => true,
        ],
    ],
    
    'database' => [
        'driver' => 'mysql', // または postgresql
        'mysql' => [
            'host' => 'localhost',
            'database' => 'photo_site',
            'username' => 'photo_user',
            'password' => 'STRONG_PASSWORD_HERE',
        ],
    ],
];
```

### ファイル権限

```bash
# データディレクトリ
chmod 700 data/
chmod 600 data/*.db

# 設定ファイル
chmod 600 config/config.php
chmod 600 config/config.local.php

# セッションキー
chmod 700 config/session_keys/
chmod 600 config/session_keys/*.php
```

### .htaccess 設定

```apache
# データディレクトリへの直接アクセスを拒否
<Directory "/path/to/data">
    Require all denied
</Directory>

<Directory "/path/to/config">
    Require all denied
</Directory>
```

### Web サーバー設定

**Nginx の例**:
```nginx
# データディレクトリへのアクセス拒否
location ~ ^/(data|config|logs|vendor)/ {
    deny all;
    return 404;
}

# PHP ファイルへの直接アクセス制限
location ~ \.php$ {
    # public/ 以外のPHPファイルは実行不可
}
```

---

## セキュリティ監査ログ

### ログ記録

```php
logSecurityEvent('Login attempt failed', [
    'username' => $username,
    'ip' => $_SERVER['REMOTE_ADDR']
]);
```

### ログファイル

- 場所: `logs/security.log`
- 自動サニタイズ: パスワード、トークンなどは `[REDACTED]` に置換
- パーミッション: 600（所有者のみ読み書き可）

### 監視すべきイベント

- ログイン試行（成功/失敗）
- CSRF トークン検証失敗
- レート制限超過
- 無効なファイルアップロード試行
- 認証エラー

---

## セキュリティチェックリスト

### 開発時

- [ ] すべてのDBクエリで Prepared Statements を使用
- [ ] すべての出力で escapeHtml() を使用
- [ ] すべての POST/PUT/DELETE で CSRF トークンを検証
- [ ] ファイルアップロードの検証を実装
- [ ] レート制限を適切に設定
- [ ] エラーメッセージに機密情報を含めない

### デプロイ前

- [ ] HTTPS を有効化し、force_https を true に設定
- [ ] 管理画面のパスを変更
- [ ] CORS の allowed_origins を具体的なドメインに設定
- [ ] CSP を有効化（report_only でテスト後、本番化）
- [ ] セッション設定で force_secure_cookie を true に設定
- [ ] データベース接続情報を安全に管理
- [ ] ファイルパーミッションを適切に設定

### 定期的な確認

- [ ] セキュリティログを確認
- [ ] 依存パッケージの更新
- [ ] パスワードポリシーの見直し
- [ ] アクセス制御の監査
- [ ] セッションキーのローテーション

---

## 脆弱性の報告

セキュリティ上の問題を発見した場合は、公開のイシュートラッカーではなく、
プロジェクトメンテナーに直接連絡してください。

---

## 参考資料

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [CORS Security](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)

---

**最終更新**: 2025-11-09
