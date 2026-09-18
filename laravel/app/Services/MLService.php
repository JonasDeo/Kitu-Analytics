<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MlService
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = env('ML_SERVICE_URL', 'http://ml:8001');
        $this->secret  = env('ML_SECRET_KEY', '');
    }

    public function get(string $path, array $params = [])
    {
        return Http::withHeaders(['X-ML-Secret' => $this->secret])
            ->timeout(30)
            ->get("{$this->baseUrl}{$path}", $params);
    }

    public function post(string $path, array $data = [])
    {
        return Http::withHeaders(['X-ML-Secret' => $this->secret])
            ->timeout(30)
            ->post("{$this->baseUrl}{$path}", $data);
    }

    public function attach(string $path, string $contents, string $filename, string $mime)
    {
        return Http::withHeaders(['X-ML-Secret' => $this->secret])
            ->timeout(30)
            ->attach('file', $contents, $filename, ['Content-Type' => $mime])
            ->post("{$this->baseUrl}{$path}");
    }
}