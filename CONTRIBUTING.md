# Contributing to pixugallery

以下は、開発・テスト・PR の流れ、そしてプロジェクトが依存する OSS についてになります。

**目次**
- 基本方針
- 開発環境の準備
- テストの実行
- Issue / PR の作り方
- コードスタイルとコミット規約
- セキュリティ報告
- CI とマージ基準
- OSS 支援（Funding）

---

**基本方針**
- オープンな貢献を歓迎します。まずは Issue で相談、もしくはフォーク→PR の流れでお願いします。
- 機密情報は一切コミットしないでください。もし誤ってコミットした場合は速やかに報告してください（下部に手順を記載）。

**開発環境の準備**
1. リポジトリをクローンして依存をインストールします。

```bash
git clone https://github.com/arutemyan/pixugallery.git
cd pixugallery
composer install
```

2. PHP バージョン: PHP 8.1+ を推奨します。ローカルで PHP が用意できない場合は Docker を利用してください。

3. 設定ファイル: `config.local.php` や `.env` などのローカル設定はリポジトリに含めず、`config/config.default.php` をコピーして `config/config.local.php` を作成して利用してください。

**テストの実行**
- ユニット/統合テストは PHPUnit を使用します。

```bash
vendor/bin/phpunit --configuration phpunit.xml
```

- CI と同じ条件で実行するには `phpunit.xml.dist` を使い、必要に応じて `TEST_DB_*` 環境変数を設定してください。

**Issue / PR の作り方**
- Issue: バグ報告や機能提案は Issue を立ててください。再現手順やログ、環境情報をできるだけ詳しく書い短い説明い。
- フォーク → ブランチ: フォークして `feature/<短い説明>`、`fix/<短い説明>`、`feature_<短い説明>`、`fix_<短い説明>` などの形式でブランチを作成してください。
- PR: PR には目的と変更点を明記し、関連する Issue があればリンクしてください。

**コードスタイルとコミット規約**
- コードスタイルは既存のスタイルに合わせてください

**セキュリティ報告**
- セキュリティ脆弱性を発見した場合は、公開Issueではなくまずプライベートに報告してください。

**CI とマージ基準**
- CI（GitHub Actions など）が成功していること

**OSS 支援（Funding）**
- このプロジェクトは一部の OSS に依存しています。

