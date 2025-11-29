<?php

namespace Roddy\StateForge;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

class ClientIdentifier
{
    protected string $storagePath;
    protected ?string $clientId = null;

    public function __construct()
    {
        $this->storagePath = config('stateforge.persistence.file.path', storage_path('app/private/stateforge'));
        $this->ensureStorageDirectory();
        $this->loadOrCreateClientId();
    }

    protected function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    protected function loadOrCreateClientId(): void
    {
        // First, try to get from cookie (survives browser restarts)
        $this->clientId = request()->cookie('stateforge_client_id');

        if ($this->clientId) {
            return;
        }

        // Try to get from existing client info file using browser fingerprint
        $fingerprint = $this->generateBrowserFingerprint();
        $clientInfo = $this->findClientByFingerprint($fingerprint);

        if ($clientInfo) {
            $this->clientId = $clientInfo['client_id'];
        } else {
            // Create new client ID
            $this->clientId = 'client_' . Str::random(32);
            $this->saveClientInfo($fingerprint);
        }
    }

    protected function generateBrowserFingerprint(): string
    {
        $components = [
            'user_agent' => request()->userAgent(),
            'accept_language' => request()->header('Accept-Language'),
            'accept_encoding' => request()->header('Accept-Encoding'),
            'ip' => request()->ip(),
        ];

        return md5(serialize($components));
    }

    protected function findClientByFingerprint(string $fingerprint): ?array
    {
        $infoFile = "{$this->storagePath}/clients.json";

        if (!file_exists($infoFile)) {
            return null;
        }

        $clients = json_decode(file_get_contents($infoFile), true) ?? [];

        foreach ($clients as $clientId => $clientInfo) {
            if ($clientInfo['fingerprint'] === $fingerprint) {
                // Update last seen
                $clients[$clientId]['last_seen'] = now()->toISOString();
                file_put_contents($infoFile, json_encode($clients, JSON_PRETTY_PRINT));

                return $clientInfo;
            }
        }

        return null;
    }

    protected function saveClientInfo(string $fingerprint): void
    {
        $infoFile = "{$this->storagePath}/clients.json";
        $clients = [];

        if (file_exists($infoFile)) {
            $clients = json_decode(file_get_contents($infoFile), true) ?? [];
        }

        $clients[$this->clientId] = [
            'client_id' => $this->clientId,
            'fingerprint' => $fingerprint,
            'created_at' => now()->toISOString(),
            'last_seen' => now()->toISOString(),
            'user_agent' => request()->userAgent(),
            'ip' => request()->ip(),
        ];

        file_put_contents($infoFile, json_encode($clients, JSON_PRETTY_PRINT));
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            'stateforge_client_id',
            $this->clientId,
            60 * 24 * 365, // 1 year
            '/',
            null,
            false,
            false,
            false,
            'lax'
        );
    }

    public function cleanupExpiredClients(int $days = 30): void
    {
        $infoFile = "{$this->storagePath}/clients.json";

        if (!file_exists($infoFile)) {
            return;
        }

        $clients = json_decode(file_get_contents($infoFile), true) ?? [];
        $cutoff = now()->subDays($days);

        foreach ($clients as $clientId => $clientInfo) {
            $lastSeen = \Carbon\Carbon::parse($clientInfo['last_seen']);
            if ($lastSeen->lt($cutoff)) {
                unset($clients[$clientId]);
                // Also delete store files for this client
                $this->deleteClientStores($clientId);
            }
        }

        file_put_contents($infoFile, json_encode($clients, JSON_PRETTY_PRINT));
    }

    protected function deleteClientStores(string $clientId): void
    {
        $pattern = "{$this->storagePath}/{$clientId}_*.json";
        $files = glob($pattern);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
