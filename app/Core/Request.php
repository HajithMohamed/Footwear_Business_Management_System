<?php

namespace App\Core;

/**
 * Immutable-ish wrapper around the current HTTP request.
 */
class Request
{
    private array $get;
    private array $post;
    private array $files;
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->get   = $_GET;
        $this->post  = $_POST;
        $this->files = $_FILES;

        // JSON body support
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            if (is_array($decoded)) {
                $this->post = array_merge($this->post, $decoded);
            }
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Method spoofing for HTML forms (<input name="_method" value="PUT|DELETE">)
        if ($method === 'POST' && !empty($this->post['_method'])) {
            $method = strtoupper($this->post['_method']);
        }
        $this->method = $method;

        // Path relative to the app base URI
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_uri();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $this->path = '/' . trim($uri, '/');
    }

    public function method(): string { return $this->method; }
    public function path(): string   { return $this->path; }

    public function isPost(): bool { return $this->method === 'POST'; }
    public function isWrite(): bool { return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true); }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    /** All inputs (optionally only the given keys). */
    public function all(array $only = []): array
    {
        $data = array_merge($this->get, $this->post);
        unset($data['_token'], $data['_method']);
        if ($only) {
            return array_intersect_key($data, array_flip($only));
        }
        return $data;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(string $key): array
    {
        $f = $this->files[$key] ?? null;
        if (!$f) {
            return [];
        }
        // Normalise the multi-file ($_FILES) structure into a list of entries.
        if (is_array($f['name'])) {
            $out = [];
            foreach ($f['name'] as $i => $name) {
                if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $out[] = [
                    'name'     => $name,
                    'type'     => $f['type'][$i] ?? '',
                    'tmp_name' => $f['tmp_name'][$i] ?? '',
                    'error'    => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $f['size'][$i] ?? 0,
                ];
            }
            return $out;
        }
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
