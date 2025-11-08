<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\FeatureDisabledException;
use App\Services\Session;
use App\Http\Router;

/**
 * Admin API 共通処理（軽量）
 * - feature check (exception -> JSON 404)
 * - session 初期化
 * - 認証ミドルウェア追加ヘルパ
 */
class AdminApiControllerBase
{
    /**
     * 初期化: セッション開始と feature チェックを行う。
     * エラー時は JSON 404 を返して exit する。
     */
    public static function init(): void
    {
        // セッション開始（Session wrapper を優先）
        try {
            Session::start();
        } catch (\Throwable $e) {
            // fallback to initSecureSession if present
            if (function_exists('\initSecureSession')) {
                \initSecureSession();
            } elseif (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }

        // feature check
        try {
            \App\Utils\FeatureGate::ensureEnabled('admin');
        } catch (FeatureDisabledException $e) {
            Router::error('Not Found', 404);
            exit;
        } catch (\Throwable $e) {
            Router::error('Internal Server Error', 500);
            exit;
        }
    }

    /**
     * Router に認証ミドルウェアを追加するヘルパ
     */
    public static function addAuthMiddleware(Router $router): void
    {
        $router->middleware(function ($method, $path) {
            // Prefer Session wrapper
            try {
                $sess = Session::getInstance();
                if ($sess->get('admin_logged_in') === true) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore and fallback
            }

            // fallback raw session
            if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                return true;
            }

            Router::error('認証が必要です。ログインしてください。', 401);
            return false;
        });
    }
}
