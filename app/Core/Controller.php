<?php

namespace App\Core;

/**
 * Base controller with view/json/redirect helpers and audit logging.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect(string $path): void
    {
        redirect($path);
    }

    protected function back(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? url('');
        header('Location: ' . $ref);
        exit;
    }

    /** Flash validation errors + old input then bounce back. */
    protected function withErrors(array $errors, array $input = []): void
    {
        Session::flashErrors($errors);
        Session::flashInput($input);
        $this->back();
    }

    protected function user(): ?array
    {
        return Auth::user();
    }

    /** Abort with a status + message (respects JSON requests). */
    protected function abort(int $status, string $message = ''): void
    {
        http_response_code($status);
        echo $message !== '' ? $message : (string) $status;
        exit;
    }

    /** Record an action in the audit trail. */
    protected function log(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
    {
        try {
            Database::instance()->query(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, meta, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    Auth::id(),
                    $action,
                    $entityType,
                    $entityId,
                    $meta ? json_encode($meta) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // Never let logging break the request.
        }
    }
}
