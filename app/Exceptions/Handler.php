<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Return JSON for API requests, otherwise default HTML responses.
     */
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            // Handle Validation Exceptions
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(),
                ], 404);
            }
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(),
                ], 422);
            }

            // Handle Authentication Exceptions
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // For all other exceptions, return JSON error
            $status = 500;
            if (method_exists($exception, 'getStatusCode')) {
                $status = $exception->getStatusCode();
            }

            return response()->json([
                'error' => true,
                'message' => $exception->getMessage() ?: 'Server Error',
                'code' => $exception->getCode(),
            ], $status);
        }

        return parent::render($request, $exception);
    }
}
