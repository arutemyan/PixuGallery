# お絵描き機能アーキテクチャ設計

## 全体アーキテクチャ

### システム構成
- **フロントエンド**: HTML5 Canvas APIベースの描画エンジン
- **バックエンド**: PHP 8.x + SQLite3 (既存システム準拠)
- **ストレージ**: ハイブリッド (DB: メタデータ, ファイル: 作品データ)
- **ライブラリ**: 
  - フロントエンド: Vanilla JS + Canvas API (ライブラリ最小化)
  - バックエンド: 既存フレームワーク準拠

### ディレクトリ構造
```
public/
├── admin/
│   └── paint/
│       ├── index.php          # メインお絵描きインターフェース
│       ├── canvas.php         # キャンバス操作ページ
│       └── api/
│           ├── save.php       # 保存API
│           ├── load.php       # 読み込みAPI
│           ├── timelapse.php  # タイムラプスAPI
│           └── layers.php     # レイヤー操作API
├── paintview.php              # 公開ビュー
└── res/
    └── js/
        └── paint/
            ├── canvas.js      # キャンバス操作
            ├── tools.js       # 描画ツール
            ├── layers.js      # レイヤー管理
            ├── timelapse.js   # タイムラプス記録/再生
            └── ui.js          # UI制御
```

## フロントエンドアーキテクチャ

### コンポーネント構成
- **CanvasManager**: メインキャンバス管理
- **ToolManager**: 描画ツール管理 (ペン/消しゴム)
- **LayerManager**: レイヤー管理 (4レイヤー固定)
- **PaletteManager**: カラーパレット管理
- **HistoryManager**: Undo/Redo履歴管理 (最大50履歴)
- **TimelapseRecorder**: タイムラプス記録
- **UIManager**: UI制御 (ツールバー、設定パネル)

### データフロー
1. ユーザー操作 → ToolManager → CanvasManager → LayerManager
2. 描画操作 → HistoryManager (履歴保存) → TimelapseRecorder (記録)
3. Undo/Redo → HistoryManager → CanvasManager (状態復元)
4. 保存時 → API呼び出し → サーバー保存

### 技術選定
- **Canvas API**: 描画処理 (Fabric.js等は使用せず軽量化)
- **LocalStorage**: 一時保存
- **Web Workers**: 重い処理 (タイムラプス圧縮) の分離

## バックエンドアーキテクチャ

### MVCパターン
- **Model**: Paint (メタデータ), IllustFile (.illustファイル操作)
- **View**: PHPテンプレート (Blade相当なし、素PHP)
- **Controller**: APIエンドポイント (save.php, load.php等)

### API設計
- **RESTful**: シンプルなREST API
- **認証**: 既存adminセッション利用
- **バリデーション**: 既存SecurityUtil利用
- **エラーハンドリング**: JSONレスポンス

### データベース設計
```sql
-- イラストテーブル (メタデータのみ)
CREATE TABLE paint (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    canvas_width INTEGER NOT NULL DEFAULT 800,
    canvas_height INTEGER NOT NULL DEFAULT 600,
    background_color TEXT DEFAULT '#FFFFFF',
    data_path TEXT,  -- .illustファイルのパス
    image_path TEXT, -- エクスポート画像のパス
    thumbnail_path TEXT, -- サムネイル画像のパス
    timelapse_path TEXT, -- タイムラプスファイルのパス
    timelapse_size INTEGER DEFAULT 0,
    file_size INTEGER DEFAULT 0,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_paint_user_id ON paint(user_id);
CREATE INDEX idx_paint_status ON paint(status);
CREATE INDEX idx_paint_created_at ON paint(created_at);
```

-- レイヤーテーブルは不要 (.illustファイル内で管理)
