<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    /**
     * Signatures older than this many seconds are rejected, so a captured request can't be replayed later.
     */
    private const int TOLERANCE_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');
        $signature = $this->parse((string) $request->header('Pixelforge-Signature'));
        $timestamp = (int) ($signature['t'] ?? 0);

        // Sign the raw body: decoding and re-encoding the JSON would change the bytes.
        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        $isValid = $secret !== ''
            && hash_equals($expected, $signature['v1'] ?? '')
            && abs(now()->getTimestamp() - $timestamp) <= self::TOLERANCE_SECONDS;

        abort_unless($isValid, 400, 'Invalid Pixelforge signature.');

        return $next($request);
    }

    /**
     * Split "t=1762265009,v1=5257a8..." into its named parts.
     *
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$name] = $value;
        }

        return $parts;
    }
}
