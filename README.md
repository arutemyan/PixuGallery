# Photo Site - イラストポートフォリオサイト

PHPベースのイラストレーター向けポートフォリオサイト

## 主要機能

- **画像ギャラリー** - グリッドレイアウトでの作品表示
- **NSFW/センシティブコンテンツ対応** - 年齢確認と自動ぼかし処理
- **管理画面** - 投稿管理、一括アップロード、編集機能
- **テーマカスタマイズ** - 色、フォント、ロゴ、ヘッダー画像のカスタマイズ
- **タグフィルタリング** - タグによる作品の絞り込み
- **閲覧数カウント** - 投稿ごとの閲覧数表示
- **レスポンシブデザイン** - スマホ・タブレット対応
- **WebP画像形式** - 高速で軽量な画像配信

## クイックスタート

### 1. 依存インストール

```bash
composer install
```

### 2. 開発サーバー起動

```bash
php -S localhost:8000 -t public/
```

ブラウザで `http://localhost:8000` にアクセス

### 3. 初期セットアップ

初回アクセス時に自動的にデータベースとテーブルが作成されます。

管理画面にアクセス: `http://localhost:8000/admin`

## プロジェクト構成

```
photo-site/
├── public/              # Webルート
│   ├── index.php        # トップページ（ギャラリー表示）
│   ├── detail.php       # 作品詳細ページ
│   ├── admin/           # 管理画面
│   ├── api/             # 公開API
│   ├── res/             # 静的リソース（CSS/JS）
│   └── uploads/         # アップロード画像保管
├── src/                 # アプリケーションコア
│   ├── Models/          # データモデル（Post, Theme, User等）
│   ├── Database/        # データベース接続
│   ├── Cache/           # キャッシュシステム
│   ├── Security/        # セキュリティ機能
│   └── Utils/           # ユーティリティ（画像処理等）
├── config/              # 設定ファイル
│   ├── config.php       # 統合設定ローダー
│   ├── config.default.php  # デフォルト設定
│   └── config.local.php    # ローカル設定（gitignore）
├── data/                # データベースファイル
│   ├── gallery.db       # メインデータ
│   └── counters.db      # 閲覧数カウンター
├── cache/               # キャッシュファイル
├── scripts/             # マイグレーション等
├── tests/               # テストコード
└── docs/                # ドキュメント
```

## 設定方法

詳細な設定方法は [docs/CONFIG.md](docs/CONFIG.md) を参照してください。

### 基本的な設定

開発環境用の設定ファイルを作成：

```bash
cp config/config.local.php.example config/config.local.php
```

`config/config.local.php` を編集して環境に合わせて設定を変更できます：

```php
<?php
return [
    'nsfw' => [
        'age_verification_minutes' => 1, // デバッグ用に1分
    ],
    'cache' => [
        'enabled' => false, // 開発時はキャッシュ無効
    ],
    'security' => [
        'https' => [
            'force' => false, // HTTPで開発
        ],
    ],
];
```

## 主な機能

### 画像アップロード

管理画面から画像をアップロードできます：
- 対応形式: JPEG, PNG, WebP
- 自動的にWebP形式に変換
- サムネイル自動生成
- NSFW画像の自動ぼかし処理

### NSFW対応

センシティブなコンテンツには：
- 年齢確認モーダル表示
- 自動ぼかし処理（2種類）
  - `blur`: ぼかし効果
  - `frosted`: すりガラス効果

設定で切り替え可能（`config/config.default.php` の `nsfw.filter_type`）

### テーマカスタマイズ

管理画面でカスタマイズ可能：
- カラースキーム（プライマリ、セカンダリ、アクセント等）
- フォントカラー（見出し、本文、フッター等）
- ロゴ画像
- ヘッダー画像
- サイトタイトル・サブタイトル

## デプロイ

### 共有ホスティング（さくらインターネット等）

```bash
# 1. FTPで全ファイルをアップロード

# 2. 必要なディレクトリの権限設定
chmod 755 data/
chmod 755 cache/
chmod 755 public/uploads/

# 3. 初回アクセスでデータベース自動作成
```

### 注意事項

- `config/config.local.php` は本番環境用の設定を作成してください
- `data/` と `cache/` ディレクトリは書き込み可能にしてください
- `public/uploads/` は画像アップロード用に書き込み可能にしてください

## セキュリティ

実装済みのセキュリティ対策：
- SQLインジェクション対策（Prepared Statements）
- XSS対策（エスケープ処理）
- CSRF対策（トークン検証）
- ディレクトリトラバーサル対策
- ファイルアップロードバリデーション
- セキュアなセッション管理
- レート制限

## 技術スタック

- **PHP 8.x** - strict_types、型宣言
- **SQLite3** - 軽量データベース（3DB分離構成）
- **WebP** - 次世代画像フォーマット
- **PSR-4** - オートローディング
- **Composer** - 依存関係管理

## スクリプト

```bash
# NSFWフィルター画像の生成
php scripts/generate_blur_thumbnails.php

# データベースマイグレーション
php scripts/migration_*.php
```

## ドキュメント

- [CONFIG.md](docs/CONFIG.md) - 設定ファイルの詳細ガイド
- [CLAUDE.md](CLAUDE.md) - 開発仕様書

## ライセンス

MIT License

---

**最終更新:** 2025-10-25
