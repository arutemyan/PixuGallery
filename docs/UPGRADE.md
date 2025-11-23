# UPGRADE GUIDE — 次期バージョンの移行手順

このガイドは、次のメジャー/マイナーリリースで導入した「ランタイムディレクトリの集約」と「フェイルファースト」動作に伴う移行手順をまとめたものです。

主な変更点

- ランタイムのアーティファクト（キャッシュ、ログ等）を `data/` 以下に集約しました。
  - キャッシュ: `data/cache`
  - ログ: `data/log`（例: `data/log/app.log`）

- 設定の必須化（フェイルファースト）
  - 起動時にアプリケーションは `cache.cache_dir`、`app_logging.log_file`（および必要に応じて `security.logging.log_file`）が設定され、書き込み可能であることを想定します。設定が無い／書き込み出来ない場合は例外を投げて起動を中止します。

- セッションのシークレット管理を厳格化
  - `security.id_secret`（あるいは環境変数 `APP_ID_SECRET`）が無いとセッション初期化が失敗します。CIやテスト環境では必ず `security.id_secret` を設定してください。

影響範囲

- 設定ファイル: `config/config.default.php` のデフォルト値を更新しています。
- スクリプトやツール: `scripts/` 内の一部スクリプト、`secure_directories.php` 等が `data/` を参照するようになっています。
- テスト: 統合テストはテスト実行時に `config.local.php` を書き出すため、その中に `security.id_secret` を含めるように修正しました。

移行手順（簡易）

1. 事前準備
   - サーバ上でアプリケーション実行ユーザーが `data/` ディレクトリを作成・書き込みできることを確認してください。
   - 既存のキャッシュやログを移行する場合はバックアップを取り、`data/cache` と `data/log` に移動してください。

2. 設定の更新
   - `config/config.local.php` に次を追加／更新してください:

```php
return [
    'cache' => [
        'cache_dir' => __DIR__ . '/../data/cache',
    ],
    'app_logging' => [
        'log_file' => __DIR__ . '/../data/log/app.log',
    ],
    'security' => [
        'id_secret' => 'your_production_secret_here', // 本番では安全な方法で管理
        'logging' => [
            'log_file' => __DIR__ . '/../data/log/security.log',
        ],
    ],
];
```

   - 本番では `id_secret` は環境変数（`APP_ID_SECRET`）やシークレット管理ツールで管理することを推奨します。

3. パーミッションと保護
   - `data/cache` と `data/log` をアプリ実行ユーザーが書き込みできるように設定します（例: `chown www-data:www-data data/cache data/log`）。
   - ウェブ経由で `data/` 以下にアクセスされないようウェブサーバ設定（または `.htaccess` ）で保護してください。

4. CI の調整
   - CI 環境でテストやビルドのステップがある場合、`security.id_secret`（または `APP_ID_SECRET`）と `cache.cache_dir` / `app_logging.log_file` がテストランナーとサーバープロセス双方で参照可能であることを確認してください。

5. スクリプトとオペレーション
   - `secure_directories.php` を使い `.htaccess` を作成してデータディレクトリを保護できます。
   - 既存の運用スクリプトが `cache/` や `log/` を参照する場合は `data/cache` / `data/log` を参照するよう更新してください。

後方互換性とロールバック

- 旧構成（`cache/`, `log/` など別ディレクトリを使用）へのロールバックは可能ですが、その場合は `config.local.php` で明示的に旧パスを指定してください。アプリケーションは指定されたパスを優先します。

参考

- 設定ガイド: `docs/CONFIG.md`
- セキュリティとデプロイ: `docs/DEPLOYMENT_SECURITY.md`, `docs/SECURITY.md`
