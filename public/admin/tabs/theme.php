<?php
require_once __DIR__ . '/tab_utils.php';
App\Admin\Tabs\checkAccess();
?>
<div class="row">
    <!-- 左側：設定フォーム -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-palette-fill me-2"></i>テーマ設定
            </div>
            <div class="card-body">
                <div id="themeAlert" class="alert alert-success d-none" role="alert"></div>
                <div id="themeError" class="alert alert-danger d-none" role="alert"></div>

                <form id="themeForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <!-- アコーディオン形式のテーマ設定 -->
                    <div class="accordion" id="themeAccordion">

                        <!-- ========== ヘッダー設定 ========== -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingHeader">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHeader" aria-expanded="true" aria-controls="collapseHeader">
                                    <i class="bi bi-layout-text-window me-2"></i>ヘッダー設定
                                </button>
                            </h2>
                            <div id="collapseHeader" class="accordion-collapse collapse show" aria-labelledby="headingHeader" data-bs-parent="#themeAccordion">
                                <div class="accordion-body">

                    <!-- サイト基本情報 -->
                    <div class="mb-3">
                        <label for="siteTitle" class="form-label">サイトタイトル</label>
                        <input type="text" class="form-control" id="siteTitle" name="site_title" placeholder="例: イラストポートフォリオ">
                        <div class="form-text">サイトのメインタイトル（ヘッダーに表示）</div>
                    </div>

                    <div class="mb-3">
                        <label for="siteSubtitle" class="form-label">サブタイトル</label>
                        <input type="text" class="form-control" id="siteSubtitle" name="site_subtitle" placeholder="例: Illustration Portfolio">
                        <div class="form-text">サイトのサブタイトル（英語表記など）</div>
                    </div>

                    <div class="mb-3">
                        <label for="siteDescription" class="form-label">サイト説明</label>
                        <textarea class="form-control" id="siteDescription" name="site_description" rows="2" placeholder="例: イラストレーターのポートフォリオサイト"></textarea>
                        <div class="form-text">SEO用のサイト説明文</div>
                    </div>

                    <!-- ヘッダー画像 -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ロゴ画像</label>
                            <div id="logoImagePreview" class="mb-2">
                                <img src="" alt="ロゴプレビュー" class="img-preview d-none" id="logoPreviewImg">
                            </div>
                            <input type="file" class="form-control form-control-sm" id="logoImage" accept="image/*">
                            <div class="mt-2">
                                <button type="button" class="btn btn-primary" id="uploadLogo">
                                    <i class="bi bi-upload me-1"></i>アップロード
                                </button>
                                <button type="button" class="btn btn-sm btn-danger d-none" id="deleteLogo">
                                    <i class="bi bi-trash me-1"></i>削除
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">背景画像</label>
                            <div id="headerImagePreview" class="mb-2">
                                <img src="" alt="ヘッダー背景プレビュー" class="img-preview d-none" id="headerPreviewImg">
                            </div>
                            <input type="file" class="form-control form-control-sm" id="headerImage" accept="image/*">
                            <div class="mt-2">
                                <button type="button" class="btn btn-primary" id="uploadHeader">
                                    <i class="bi bi-upload me-1"></i>アップロード
                                </button>
                                <button type="button" class="btn btn-sm btn-danger d-none" id="deleteHeader">
                                    <i class="bi bi-trash me-1"></i>削除
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ヘッダー色 -->
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="primaryColor" class="form-label small mb-1">プライマリ色</label>
                            <input type="color" class="form-control form-control-color w-100" id="primaryColor" name="primary_color" value="#8B5AFA">
                        </div>
                        <div class="color-item">
                            <label for="secondaryColor" class="form-label small mb-1">セカンダリ色</label>
                            <input type="color" class="form-control form-control-color w-100" id="secondaryColor" name="secondary_color" value="#667eea">
                        </div>
                        <div class="color-item">
                            <label for="headingColor" class="form-label small mb-1">見出し色</label>
                            <input type="color" class="form-control form-control-color w-100" id="headingColor" name="heading_color" value="#ffffff">
                        </div>
                    </div>

                    <!-- カスタムHTML（上級者向け） -->
                    <div class="mb-4">
                        <label for="headerText" class="form-label small text-muted">
                            <i class="bi bi-code-slash me-1"></i>カスタムHTML（上級者向け）
                        </label>
                        <input type="text" class="form-control form-control-sm" id="headerText" name="header_html" placeholder="空欄の場合はサイトタイトルを表示">
                        <div class="form-text">空欄の場合は上記のサイトタイトルが自動表示されます</div>
                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- ========== コンテンツ設定 ========== -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingContent">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseContent" aria-expanded="false" aria-controls="collapseContent">
                                    <i class="bi bi-file-earmark-text me-2"></i>コンテンツ設定
                                </button>
                            </h2>
                            <div id="collapseContent" class="accordion-collapse collapse" aria-labelledby="headingContent" data-bs-parent="#themeAccordion">
                                <div class="accordion-body">

                    <!-- 背景・テキスト色 -->
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="backgroundColor" class="form-label small mb-1">背景色</label>
                            <input type="color" class="form-control form-control-color w-100" id="backgroundColor" name="background_color" value="#1a1a1a">
                        </div>
                        <div class="color-item">
                            <label for="textColor" class="form-label small mb-1">本文色</label>
                            <input type="color" class="form-control form-control-color w-100" id="textColor" name="text_color" value="#ffffff">
                        </div>
                        <div class="color-item">
                            <label for="accentColor" class="form-label small mb-1">アクセント色</label>
                            <input type="color" class="form-control form-control-color w-100" id="accentColor" name="accent_color" value="#FFD700">
                        </div>
                    </div>

                    <!-- リンク色 -->
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="linkColor" class="form-label small mb-1">リンク色</label>
                            <input type="color" class="form-control form-control-color w-100" id="linkColor" name="link_color" value="#8B5AFA">
                        </div>
                        <div class="color-item">
                            <label for="linkHoverColor" class="form-label small mb-1">リンクホバー色</label>
                            <input type="color" class="form-control form-control-color w-100" id="linkHoverColor" name="link_hover_color" value="#a177ff">
                        </div>
                    </div>

                    <!-- タグ色 -->
                    <h6 class="mt-3 mb-2 small text-muted">タグ設定</h6>
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="tagBgColor" class="form-label small mb-1">タグ背景色</label>
                            <input type="color" class="form-control form-control-color w-100" id="tagBgColor" name="tag_bg_color" value="#8B5AFA">
                        </div>
                        <div class="color-item">
                            <label for="tagTextColor" class="form-label small mb-1">タグ文字色</label>
                            <input type="color" class="form-control form-control-color w-100" id="tagTextColor" name="tag_text_color" value="#ffffff">
                        </div>
                            <div class="color-item col-span-2">
                            <label class="form-label small mb-1">プレビュー</label>
                            <div class="p-8">
                                <span id="tagColorPreview" class="badge badge-tag-sample">サンプルタグ</span>
                            </div>
                        </div>
                    </div>

                    <!-- フィルタ設定 -->
                    <h6 class="mt-3 mb-2 small text-muted">フィルタ設定（選択時の色）</h6>
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="filterActiveBgColor" class="form-label small mb-1">フィルタ選択時背景色</label>
                            <input type="color" class="form-control form-control-color w-100" id="filterActiveBgColor" name="filter_active_bg_color" value="#8B5AFA">
                        </div>
                        <div class="color-item">
                            <label for="filterActiveTextColor" class="form-label small mb-1">フィルタ選択時文字色</label>
                            <input type="color" class="form-control form-control-color w-100" id="filterActiveTextColor" name="filter_active_text_color" value="#ffffff">
                        </div>
                            <div class="color-item col-span-2">
                            <label class="form-label small mb-1">プレビュー</label>
                            <div class="p-8">
                                <span id="filterActiveColorPreview" class="badge badge-tag-sample">選択中フィルタ</span>
                            </div>
                        </div>
                    </div>

                    <!-- カード設定 -->
                    <h6 class="mt-3 mb-2 small text-muted">カード設定</h6>
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="cardBgColor" class="form-label small mb-1">カード背景色</label>
                            <input type="color" class="form-control form-control-color w-100" id="cardBgColor" name="card_bg_color" value="#252525">
                        </div>
                        <div class="color-item">
                            <label for="cardBorderColor" class="form-label small mb-1">カード枠線色</label>
                            <input type="color" class="form-control form-control-color w-100" id="cardBorderColor" name="card_border_color" value="#333333">
                        </div>
                        <div class="color-item col-span-2">
                            <label for="cardShadowOpacity" class="form-label small mb-1">カード影の濃さ</label>
                            <input type="range" class="form-range" id="cardShadowOpacity" name="card_shadow_opacity" min="0" max="1" step="0.1" value="0.3">
                            <div class="form-text small">現在: <span id="shadowValue">0.3</span></div>
                        </div>
                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- ========== ナビゲーション設定 ========== -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThemeNavigation">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThemeNavigation" aria-expanded="false" aria-controls="collapseThemeNavigation">
                                    <i class="bi bi-arrow-left-circle me-2"></i>ナビゲーション設定
                                </button>
                            </h2>
                            <div id="collapseThemeNavigation" class="accordion-collapse collapse" aria-labelledby="headingThemeNavigation" data-bs-parent="#themeAccordion">
                                <div class="accordion-body">
                                    <p class="text-muted small mb-3">
                                        詳細ページの「一覧に戻る」ボタンのデザインをカスタマイズできます
                                    </p>

                                    <div class="mb-3">
                                        <label for="backButtonText" class="form-label">ボタンテキスト</label>
                                        <input type="text" class="form-control" id="backButtonText" name="back_button_text" placeholder="一覧に戻る" maxlength="20">
                                        <div class="form-text">ボタンに表示するテキスト（20文字以内）</div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="backButtonBgColor" class="form-label">背景色</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="color" class="form-control form-control-color" id="backButtonBgColor" value="#8B5AFA">
                                                <div class="flex-grow-1">
                                                    <label for="backButtonBgAlpha" class="form-label small mb-1">透過 (Alpha)</label>
                                                    <input type="range" class="form-range" id="backButtonBgAlpha" min="0" max="100" step="1" value="100">
                                                    <div class="form-text small">現在: <span id="backButtonBgAlphaValue">100%</span></div>
                                                </div>
                                            </div>
                                            <input type="hidden" id="backButtonBgComposed" name="back_button_bg_color" value="#8B5AFA">
                                            <div class="form-text">背景色と透過を組み合わせて保存します</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="backButtonTextColor" class="form-label">テキスト色</label>
                                            <input type="color" class="form-control form-control-color" id="backButtonTextColor" name="back_button_text_color" value="#FFFFFF">
                                            <div class="form-text">ボタンのテキスト色</div>
                                        </div>
                                    </div>

                                    <!-- プレビュー -->
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <label class="form-label small text-muted">プレビュー:</label>
                                        <div id="backButtonPreview" class="header-back-button header-back-button--preview">
                                            一覧に戻る
                                        </div>
                                    </div>

                                    <!-- 詳細ボタン設定 -->
                                    <div class="mt-4">
                                        <p class="text-muted small mb-2">カードやオーバーレイで使う「詳細表示」ボタンのスタイル</p>

                                        <div class="mb-3">
                                            <label for="detailButtonText" class="form-label">ボタンテキスト</label>
                                            <input type="text" class="form-control" id="detailButtonText" name="detail_button_text" placeholder="詳細表示" maxlength="20">
                                            <div class="form-text">詳細ボタンに表示するテキスト（空欄可）</div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="detailButtonBgColor" class="form-label">背景色</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" class="form-control form-control-color" id="detailButtonBgColor" value="#8B5AFA">
                                                    <div class="flex-grow-1">
                                                        <label for="detailButtonBgAlpha" class="form-label small mb-1">透過 (Alpha)</label>
                                                        <input type="range" class="form-range" id="detailButtonBgAlpha" min="0" max="100" step="1" value="100">
                                                        <div class="form-text small">現在: <span id="detailButtonBgAlphaValue">100%</span></div>
                                                    </div>
                                                </div>
                                                <input type="hidden" id="detailButtonBgComposed" name="detail_button_bg_color" value="#8B5AFA">
                                                <div class="form-text">背景色と透過を組み合わせて保存します</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="detailButtonTextColor" class="form-label">テキスト色</label>
                                                <input type="color" class="form-control form-control-color" id="detailButtonTextColor" name="detail_button_text_color" value="#FFFFFF">
                                                <div class="form-text">ボタンのテキスト色</div>
                                            </div>
                                        </div>

                                        <!-- プレビュー -->
                                        <div class="mt-3 p-3 bg-light rounded">
                                            <label class="form-label small text-muted">プレビュー:</label>
                                            <div id="detailButtonPreview" class="header-back-button header-back-button--preview">
                                                詳細表示
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ========== フッター設定 ========== -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFooter">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFooter" aria-expanded="false" aria-controls="collapseFooter">
                                    <i class="bi bi-layout-text-window-reverse me-2"></i>フッター設定
                                </button>
                            </h2>
                            <div id="collapseFooter" class="accordion-collapse collapse" aria-labelledby="headingFooter" data-bs-parent="#themeAccordion">
                                <div class="accordion-body">

                    <!-- フッター色 -->
                    <div class="color-grid mb-3">
                        <div class="color-item">
                            <label for="footerBgColor" class="form-label small mb-1">背景色</label>
                            <input type="color" class="form-control form-control-color w-100" id="footerBgColor" name="footer_bg_color" value="#2a2a2a">
                        </div>
                        <div class="color-item">
                            <label for="footerTextColor" class="form-label small mb-1">文字色</label>
                            <input type="color" class="form-control form-control-color w-100" id="footerTextColor" name="footer_text_color" value="#cccccc">
                        </div>
                    </div>

                    <!-- フッターHTML -->
                    <div class="mb-4">
                        <label for="footerText" class="form-label">フッターテキスト</label>
                        <textarea class="form-control" id="footerText" name="footer_html" rows="3" placeholder="例: © 2025 Portfolio Site. All rights reserved."></textarea>
                        <div class="form-text">フッターに表示されるテキスト（HTMLタグも使用可）</div>
                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>すべて保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 右側：リアルタイムプレビュー -->
    <div class="col-lg-6">
        <div class="card sticky-preview">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-display me-2"></i>リアルタイムプレビュー
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-preview-size="100%" title="デスクトップ">
                        <i class="bi bi-laptop"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-preview-size="768px" title="タブレット">
                        <i class="bi bi-tablet"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-preview-size="375px" title="モバイル">
                        <i class="bi bi-phone"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0 preview-bg">
                <div id="previewContainer" class="preview-container">
                    <div id="previewFrame" class="preview-frame">
                        <iframe
                            id="sitePreview"
                            src="/"
                            class="preview-iframe"
                            title="サイトプレビュー"
                        ></iframe>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    色やテキストを変更すると、リアルタイムでプレビューに反映されます
                </div>
            </div>
        </div>
    </div>
</div>