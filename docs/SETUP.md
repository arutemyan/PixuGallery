# Setup / Deployment

このドキュメントは PixuGallery をサーバに設置する際の手順と注意点をまとめたものです。

重要: 本アプリケーションは起動時に `APP_ID_SECRET`（HMAC 用シークレット）を必須としています。
`APP_ID_SECRET` が設定されていないとアプリは 503 を返して起動しません。

目次
- 要件
- 設定ファイル
- シークレット（`APP_ID_SECRET`）の準備
- デプロイ時の推奨運用
- ファイルパーミッション

---

## 要件

- PHP 8.1 以上
- Composer
- SQLite（小規模/開発）または MySQL/PostgreSQL
- Node.js + pnpm/npm（フロントエンドをビルドする場合）

## 設定ファイル

サンプルをコピーしてローカル設定を作成します。

```bash
# 推奨: デフォルト設定をコピーしてローカル設定を作成します
cp config/config.default.php config/config.local.php
# 編集して各種設定（DB, admin.path, security など）を適切に設定
```

`config/config.local.php` は機密情報を含むため決してリポジトリにコミットしないでください。

## シークレット（`APP_ID_SECRET`）の準備（必須）

このアプリは ID トークンの署名に HMAC を使います。署名用のシークレットは必須です。

推奨長さ: 256-bit（32 バイト）。

生成例（パイプラインや運用ホストで実行して secret manager に保存してください）:

```bash
# URL-safe base64（推奨）
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='

# あるいは hex 表現
openssl rand -hex 32
```

設定方法（優先順）:

1. 環境変数 `APP_ID_SECRET` をプロセスに渡す（推奨）。
2. `config/config.local.php` の `security.id_secret` に設定する（運用上の理由で環境変数が使えない場合のみ）。

注意: `APP_ID_SECRET` をブラウザ等に表示したり、公開リポジトリにコミットしたりしないでください。

### シークレット管理の推奨

- 可能なら Vault / AWS Secrets Manager / Kubernetes Secret 等のシークレットストアを利用してください。
- コンテナ環境では Secret をマウントするか環境変数で注入してください。
- systemd であれば `Environment=` や drop-in ファイルで設定することを推奨します。

## デプロイ時の推奨運用

- SSH で直接サーバに入って手作業で秘密を書き込む運用は避けてください。CI/CD や構成管理で秘密を注入してください。
- 本番では HTTPS を有効化し `security.session.force_secure_cookie` を有効にしてください。
- 複数ノード運用では、シークレットは集中したシークレットストアで管理してください。

## ファイルパーミッション

- `data/` や DB ファイル: `700` / `600` 等で保護
- `config/config.local.php`: `600`
- `logs/`: `600`（運用ログは機密情報を含まないようにサニタイズする）

---

詳細なセキュリティ要件やコマンド例は `docs/SECURITY.md` を参照してください。
