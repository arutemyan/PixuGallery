<?php

declare(strict_types=1);

namespace App\Http;

/**
 * シンプルなHTTPルーター
 *
 * RESTful APIのルーティングを提供
 */
class Router
{
    /** @var array ルートテーブル */
    private array $routes = [];

    /** @var array ミドルウェア */
    private array $middlewares = [];

    /**
     * GETルートを登録
     */
    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * POSTルートを登録
     */
    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * PUTルートを登録
     */
    public function put(string $pattern, callable $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    /**
     * DELETEルートを登録
     */
    public function delete(string $pattern, callable $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * ルートを登録
     */
    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * ミドルウェアを追加
     */
    public function middleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * リクエストをルーティング
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getPath();

        // PUTメソッドの擬似サポート (_method パラメータ)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        // ミドルウェアを実行
        foreach ($this->middlewares as $middleware) {
            $result = $middleware($method, $path);
            if ($result === false) {
                return; // ミドルウェアがfalseを返したら処理を中断
            }
        }

        // ルートをマッチング
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $path);
            if ($params !== null) {
                // ハンドラーを実行
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        // ルートが見つからない
        $this->notFound();
    }

    /**
     * パスを取得（クエリ文字列を除く）
     */
    private function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // クエリ文字列を除去
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        return $path;
    }

    /**
     * ルートパターンとパスをマッチング
     *
     * @return array|null マッチした場合はパラメータ配列、しない場合はnull
     */
    private function matchRoute(string $pattern, string $path): ?array
    {
        // パターンを正規表現に変換
        // :id などのパラメータを ([^/]+) に変換
        $regex = preg_replace('/:[a-zA-Z0-9_]+/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // 最初の要素（完全一致）を除去
            array_shift($matches);
            return $matches;
        }

        return null;
    }

    /**
     * 404 Not Found
     */
    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'エンドポイントが見つかりません'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSONレスポンスを送信
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * エラーレスポンスを送信
     */
    public static function error(string $message, int $statusCode = 500): void
    {
        self::json([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}
