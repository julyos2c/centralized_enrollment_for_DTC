<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $exception, $request) {
            if (! ($exception->getPrevious() instanceof \Illuminate\Session\TokenMismatchException)
                || ! $request->isMethod('post') || ! $request->routeIs('logout')) {
                return null;
            }

            // Never perform logout with an invalid CSRF token. Recover via a safe GET.
            $destination = auth()->check() ? route('logout.confirm') : route('landing');
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh before continuing.',
                    'redirect_url' => $destination,
                ], 419);
            }

            return redirect()->to($destination, 303);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
