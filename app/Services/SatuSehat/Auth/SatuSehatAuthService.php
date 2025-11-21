<?php

namespace App\Services\SatuSehat\Auth;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SatuSehatAuthService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $cacheKey;

    public function __construct()
    {
        $cfg = Config::get('satusehat');

        $this->baseUrl      = rtrim($cfg['auth']['base_url'], '/');
        $this->clientId     = $cfg['auth']['client_id'];
        $this->clientSecret = $cfg['auth']['client_secret'];

        // Cache key unik per baseUrl+clientId biar aman antar env/akun
        $this->cacheKey = 'satusehat:access_token:' . md5($this->baseUrl . '|' . $this->clientId);
    }

    /**
     * Ambil token dari cache bila ada; kalau tidak ada/expired, request baru lalu simpan ke cache.
     * Return hanya string token, biar gampang dipakai di header Authorization.
     */
    public function getAccessToken(): string
    {
        if ($token = Cache::get($this->cacheKey)) {
            return $token;
        }

        // Ambil token baru
        $url = $this->baseUrl . '/oauth2/v1/accesstoken?grant_type=client_credentials';

        $resp = Http::asForm()
            ->retry(1, 200) // optional, ringan aja
            ->post($url, [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if ($resp->failed()) {
            // Biar stacktrace jelas
            throw new RequestException($resp);
        }

        $json       = $resp->json();
        $token      = $json['access_token'] ?? null;
        $expiresIn  = (int) ($json['expires_in'] ?? 0);

        if (!$token || $expiresIn <= 0) {
            // Respons tidak sesuai harapan
            throw new \RuntimeException('SatuSehat token response invalid.');
        }

        // Simpan ke cache dengan buffer 60 detik (jangan mepet expired)
        $ttl = max(60, $expiresIn - 60);
        Cache::put($this->cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * Helper buat langsung bikin header Authorization.
     */
    public function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->getAccessToken()];
    }

    /**
     * Kalau perlu force refresh (misal API balas 401), panggil ini.
     */
    public function forceRefresh(): string
    {
        Cache::forget($this->cacheKey);
        return $this->getAccessToken();
    }
}
