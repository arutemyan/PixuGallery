# Development / Local setup

このドキュメントはローカル開発やテスト実行のための手順をまとめたものです。

## 要件

- PHP 8.1+
- Composer
- Node.js + pnpm または npm（フロントエンドビルドが必要な場合）

## クイックスタート

1. リポジトリをクローン

```bash
git clone <repo-url>
cd pixugallery
```

2. 依存をインストール

```bash
composer install
pnpm install # または npm install
```

3. 設定ファイルを用意

```bash
cp config/config.local.example.php config/config.local.php
# 必要に応じて編集（DB, admin.path, security など）
```

4. テストを実行

```bash
vendor/bin/phpunit
```

5. 開発サーバーを起動（簡易）

```bash
php -S localhost:8000 -t public/
# ブラウザで http://localhost:8000 を開く
```

## テストに関する注意

- 一部のテストは環境変数（`TEST_DB_*`）や一時ディレクトリを利用します。
- `tests/Unit/Services/SessionTest.php` などは `APP_ID_SECRET` を必要とするため、テスト実行環境では `APP_ID_SECRET` を一時的に設定するか、CI の設定で注入してください（ローカルでは `export APP_ID_SECRET=$(openssl rand -hex 32)` 等で設定できます）。

## コードスタイル

- リポジトリの既存スタイルに合わせてください。変更の際は小さな単位で PR を作成し、テストを追加してください。

## 開発フロー

- Issue を作成 → フォークしてブランチ（`feature/<name>` / `fix/<name>`） → PR を作成
- PR には説明と関連 Issue 番号を明記し、可能ならテストを追加してください。
