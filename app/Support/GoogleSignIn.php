<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Continue with Google" for website customers (OpenID Connect, authorization-code flow).
 * The code is swapped for tokens server-to-server over HTTPS, and the profile is read from
 * Google's userinfo endpoint with that token, so nothing the browser sends is trusted.
 */
class GoogleSignIn
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const PROFILE = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function callbackUrl(): string
    {
        return route('shop.google.callback');
    }

    /** Address to send the visitor to; remembers a one-time state + PKCE verifier in the session. */
    public static function redirectUrl(): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        session(['google_oauth' => ['state' => $state, 'verifier' => $verifier]]);

        return self::AUTH.'?'.http_build_query([
            'client_id'             => config('services.google.client_id'),
            'redirect_uri'          => self::callbackUrl(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ]);
    }

    /**
     * Google's answer → ['sub', 'email', 'email_verified', 'name'].
     *
     * @throws RuntimeException when the answer is not valid
     */
    public static function user(?string $code, ?string $state): array
    {
        $saved = session()->pull('google_oauth');
        if (! $code || ! $state || ! $saved || ! hash_equals($saved['state'], $state)) {
            throw new RuntimeException('state');
        }

        $token = Http::asForm()->timeout(15)->post(self::TOKEN, [
            'code'          => $code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => self::callbackUrl(),
            'grant_type'    => 'authorization_code',
            'code_verifier' => $saved['verifier'],
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new RuntimeException('token');
        }

        $p = Http::withToken($token->json('access_token'))->timeout(15)->get(self::PROFILE);
        if (! $p->successful() || ! $p->json('sub')) {
            throw new RuntimeException('profile');
        }

        return [
            'sub'            => (string) $p->json('sub'),
            'email'          => strtolower((string) $p->json('email')),
            'email_verified' => (bool) $p->json('email_verified'),
            'name'           => trim((string) ($p->json('name') ?: $p->json('given_name') ?: '')),
        ];
    }
}
