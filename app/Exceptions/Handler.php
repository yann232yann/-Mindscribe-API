// app/Exceptions/Handler.php
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

protected function unauthenticated($request, AuthenticationException $exception)
{
    return response()->json(['message' => 'Non authentifié.'], 401);
}

public function render($request, Throwable $exception)
{
    if ($request->is('api/*')) {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors'  => $exception->errors(),
            ], 422);
        }
    }
    return parent::render($request, $exception);
}