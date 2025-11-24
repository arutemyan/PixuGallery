<?php
require_once __DIR__ . '/tab_utils.php';
App\Admin\Tabs\checkAccess();
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gear-fill me-2"></i>サイト設定
            </div>
            <div class="card-body">
                <div id="settingsAlert" class="alert d-none" role="alert"></div>

                <form id="settingsForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <!-- アコーディオン形式の設定 -->
                    <div class="accordion" id="settingsAccordion">

                        <!-- コンテンツ表示設定 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingDisplay">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDisplay" aria-expanded="true" aria-controls="collapseDisplay">
                                    <i class="bi bi-eye me-2"></i>コンテンツ表示設定
                                </button>
                            </h2>
                            <div id="collapseDisplay" class="accordion-collapse collapse show" aria-labelledby="headingDisplay" data-bs-parent="#settingsAccordion">
                                <div class="accordion-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="showViewCount" checked>
                                        <label class="form-check-label" for="showViewCount">
                                            <strong>閲覧回数を表示する</strong>
                                        </label>
                                        <div class="form-text mt-2">
                                            オフにすると、すべての投稿で閲覧回数が非表示になります
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OGP/SNSシェア設定 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOGP">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOGP" aria-expanded="false" aria-controls="collapseOGP">
                                    <i class="bi bi-share me-2"></i>OGP/SNSシェア設定
                                </button>
                            </h2>
                            <div id="collapseOGP" class="accordion-collapse collapse" aria-labelledby="headingOGP" data-bs-parent="#settingsAccordion">
                                <div class="accordion-body">
                                    <p class="text-muted small mb-3">
                                        TwitterやFacebookなどのSNSでシェアされた際に表示される情報を設定します
                                    </p>

                                    <div class="mb-3">
                                        <label for="ogpTitle" class="form-label">OGPタイトル</label>
                                        <input type="text" class="form-control" id="ogpTitle" name="ogp_title" placeholder="空欄の場合はサイトタイトルを使用">
                                        <div class="form-text">SNSでシェアされた際のタイトル（60文字以内推奨）</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="ogpDescription" class="form-label">OGP説明文</label>
                                        <textarea class="form-control" id="ogpDescription" name="ogp_description" rows="3" placeholder="空欄の場合はサイト説明を使用"></textarea>
                                        <div class="form-text">SNSでシェアされた際の説明文（120文字以内推奨）</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">OGP画像</label>
                                        <div id="ogpImagePreview" class="mb-2">
                                            <img src="" alt="OGP画像プレビュー" id="ogpImagePreviewImg" class="ogp-image-preview">
                                        </div>
                                        <input type="file" class="form-control" id="ogpImageFile" accept="image/*">
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-primary" id="uploadOgpImage">
                                                <i class="bi bi-upload me-1"></i>アップロード
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-ogp-button" id="deleteOgpImage">
                                                <i class="bi bi-trash me-1"></i>削除
                                            </button>
                                        </div>
                                        <div class="form-text">推奨サイズ: 1200x630px（横長）。Twitterでは2:1の比率が推奨されます</div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="twitterCard" class="form-label">Twitter Cardタイプ</label>
                                            <select class="form-select" id="twitterCard" name="twitter_card">
                                                <option value="summary">summary（正方形）</option>
                                                <option value="summary_large_image" selected>summary_large_image（大きな画像）</option>
                                            </select>
                                            <div class="form-text">Twitterでの表示タイプ</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="twitterSite" class="form-label">Twitterアカウント</label>
                                            <div class="input-group">
                                                <span class="input-group-text">@</span>
                                                <input type="text" class="form-control" id="twitterSite" name="twitter_site" placeholder="username">
                                            </div>
                                            <div class="form-text">@なしで入力</div>
                                        </div>
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
</div>