<!--
  README for Photo Site
  This file provides a concise project overview, quick start, and links to docs/design.
-->

# Photo Site

簡潔なプロジェクト概要と開発者向けの最小限の手引きをこの README に記載します。

## 概要

Photo Site はブラウザ上でイラストを作成・保存できるシンプルな投稿・閲覧サイトです。
管理画面にはペイントエディタ（レイヤー、タイムラプス記録など）を備え、作品はメタデータをデータベース（SQLite 等）に、描画データをファイルとして保存します。

主な用途:

- 管理者向けのペイントエディタ（public/admin/paint/）
- 公開ギャラリーと作品詳細（public/paint/）
- 管理 API と公開 API（保存／読み込み／一覧／タイムラプス）

## 機能フラグ

一部機能は設定で有効/無効を切り替えられます（`config/config.default.php` と `config/config.local.php`）。
例えば Paint 機能を無効化したい場合、`config/config.local.php` に設定を追加します。

例:

```php
<?php
return [
    'paint' => [ 'enabled' => false ],
    'admin' => [ 'enabled' => true ],
];
```

無効にした場合、該当ルートは 404 を返すなどアプリ側で判定されます。

## ファイル・ディレクトリの構成（抜粋）

- `public/` - ドキュメントルート（エントリポイント）
- `src/` - アプリケーションソース
- `tests/` - PHPUnit テスト
- `docs/` - ユーザー向けドキュメント
- `design/` - 設計・実装メモ

詳しい説明は `docs/` と `design/` を参照してください。

## クイックスタート（開発用）

依存をインストールして簡易サーバーで起動します（開発用）。

```bash
composer install
php -S localhost:8000 -t public/
```

注意: 実運用では `config/config.local.php` に適切な DB 設定・セキュリティ設定を行い、ウェブサーバ（nginx / Apache）で公開してください。

## テスト

ローカルで PHPUnit を実行してテストを回せます。テストは SQLite を使う構成になっているものがあります。

```bash
vendor/bin/phpunit
```

統合テストは一時的にビルトイン PHP サーバーを立ち上げるため、テスト実行中に一時ディレクトリが作成されます。

## 開発者向けノート

- `scripts/insert_admin_feature_check.php` : 管理 API の手続き的ファイルに自動で `_feature_check.php` を挿入するユーティリティ（利用時は注意して実行してください）。

## ドキュメント

- ユーザー向け: `docs/README.md`
- 設計資料: `design/README.md`

## ライセンス

MIT
## 機能フラグの管理と確認

このアプリケーションは `config/config.default.php` の設定で `paint.enabled` と `admin.enabled` により機能の ON/OFF を制御します。運用では `config/config.local.php` で上書きすることを推奨します。
 - Paint を無効にする例 (`config/config.local.php` を作成):

```php
<?php
 - 確認:

```bash
curl -I http://localhost:8000/paint/   # -> 404 when disabled
自動挿入ツール:

管理 API の中に存在する「手続き的（class を宣言していない）PHPファイル」に `_feature_check.php` を挿入する自動化スクリプトを `scripts/insert_admin_feature_check.php` に追加しました。
使い方:

````markdown
# Photo Site

簡潔なプロジェクト概要とリンクをこの README に残し、詳細なユーザー向けドキュメントは `docs/` に、設計・実装メモは `design/` に分離しました。

このリポジトリの主要なエントリ:

- docs (エンドユーザー向け)
  - `docs/README.md` - ユーザー向けドキュメントの起点（設定、クイックスタート、運用注意）
- design (設計・実装メモ、アーキテクチャ資料)
  - `design/README.md` - 設計資料の起点

クイックスタート（開発・テスト用）:

```bash
# Photo Site

Photo Site はシンプルなイラスト投稿・閲覧サイトです。管理画面にはブラウザ上で描けるペイント（タイムラプス記録付き）エディタがあり、作品はメタデータを SQLite に、描画データを専用ファイル（.illust）として保存します。

主な特徴
- 管理用ペイントエディタ（public/admin/paint/） — レイヤー、履歴、タイムラプス
- 公開ギャラリーと作品詳細（public/paint/） — サムネイル・タイムラプス再生
- API（管理・公開） — 保存/読み込み/一覧/タイムラプス配信
- ファイルベースの作品データ（.illust）と DB のハイブリッド設計

技術スタック
- PHP 8.x, SQLite
- フロントエンド: Vanilla ES6 JavaScript (モジュール), HTML5 Canvas
- 画像処理: GD または Imagick (WebP 出力を推奨)

すばやい開始方法（開発用）
```bash
composer install
php -S localhost:8000 -t public/
```

ドキュメント
- ユーザー向けドキュメント: docs/README.md
- 設計・内部資料: design/README.md

管理画面（開発時）: http://localhost:8000/admin

ライセンス: MIT

