<?php
/**
 * FPL-BOT - Official FPL API Service (100% Native PHP cURL)
 */

require_once __DIR__ . '/../config/database.php';

class FplService {
    private string $email;
    private string $password;
    private string $teamId;
    private string $customCookie;
    private string $cookieFile;
    private string $cacheDir;
    private PDO $db;

    public function __construct() {
        $config = require __DIR__ . '/../config/config.php';
        $this->email = $config['fpl']['email'] ?? '';
        $this->password = $config['fpl']['password'] ?? '';
        $this->teamId = (string)($config['fpl']['team_id'] ?? '');
        $this->customCookie = (string)($config['fpl']['cookie'] ?? env('FPL_COOKIE', ''));
        $this->cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fpl_cookie_' . md5($this->email ?: $this->teamId) . '.txt';
        $this->cacheDir = __DIR__ . '/../storage/cache';
        $this->db = Database::getConnection();

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        if (!empty($this->customCookie)) {
            $this->saveCustomCookieToFile($this->customCookie);
        }
    }

    public function getBootstrapStatic(bool $forceRefresh = false): array {
        $cacheKey = 'bootstrap_static';
        if (!$forceRefresh) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = 'https://fantasy.premierleague.com/api/bootstrap-static/';
        $res = $this->curlRequest($url);
        if ($res['status'] === 200 && !empty($res['body'])) {
            $data = json_decode($res['body'], true);
            if (is_array($data)) {
                $this->setCache($cacheKey, $data, 600);
                return $data;
            }
        }

        $cachedExpired = $this->getCache($cacheKey, true);
        if ($cachedExpired !== null) {
            return $cachedExpired;
        }

        return [];
    }

    public function getFixtures(bool $forceRefresh = false): array {
        $cacheKey = 'fixtures_all';
        if (!$forceRefresh) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = 'https://fantasy.premierleague.com/api/fixtures/';
        $res = $this->curlRequest($url);
        if ($res['status'] === 200 && !empty($res['body'])) {
            $data = json_decode($res['body'], true);
            if (is_array($data)) {
                $this->setCache($cacheKey, $data, 1800);
                return $data;
            }
        }

        $cachedExpired = $this->getCache($cacheKey, true);
        if ($cachedExpired !== null) {
            return $cachedExpired;
        }

        return [];
    }

    public function getCurrentAndNextGameweek(): array {
        $bootstrap = $this->getBootstrapStatic();
        $events = $bootstrap['events'] ?? [];

        $currentGw = null;
        $nextGw = null;

        foreach ($events as $event) {
            if (!empty($event['is_current'])) {
                $currentGw = $event;
            }
            if (!empty($event['is_next'])) {
                $nextGw = $event;
            }
        }

        if (!$nextGw && !empty($events)) {
            $nextGw = $events[0];
        }

        return [
            'current' => $currentGw,
            'next' => $nextGw,
        ];
    }

    public function getMyTeam(string $customTeamId = ''): array {
        $teamId = !empty($customTeamId) ? $customTeamId : $this->teamId;

        // 1. Cek data custom squad yang tersimpan di MySQL lokal
        try {
            $stmt = $this->db->query("SELECT * FROM `user_squad` ORDER BY `position` ASC");
            $savedPicks = $stmt->fetchAll();
            if (count($savedPicks) === 15) {
                $picks = [];
                foreach ($savedPicks as $sp) {
                    $picks[] = [
                        'element' => (int)$sp['element_id'],
                        'position' => (int)$sp['position'],
                        'is_captain' => (bool)$sp['is_captain'],
                        'is_vice_captain' => (bool)$sp['is_vice_captain'],
                        'multiplier' => $sp['is_captain'] ? 2 : 1
                    ];
                }
                return [
                    'status' => 'success',
                    'source' => 'custom_saved_squad',
                    'data' => [
                        'picks' => $picks,
                        'transfers' => [
                            'bank' => 0,
                            'limit' => 1,
                            'made' => 0
                        ]
                    ]
                ];
            }
        } catch (Exception $e) {}

        // 2. Cek endpoint resmi jika terautentikasi
        if (!empty($this->customCookie) || file_exists($this->cookieFile)) {
            $authenticatedUrl = "https://fantasy.premierleague.com/api/my-team/{$teamId}/";
            $res = $this->curlRequest($authenticatedUrl, 'GET', null, true);

            if ($res['status'] === 200) {
                $data = json_decode($res['body'], true);
                if (is_array($data) && !empty($data['picks']) && count($data['picks']) === 15) {
                    return [
                        'status' => 'success',
                        'authenticated' => true,
                        'data' => $data
                    ];
                }
            }
        }

        // 3. Ambil data publik tim manajer jika musim sudah berjalan
        $gwInfo = $this->getCurrentAndNextGameweek();
        $gw = $gwInfo['current']['id'] ?? ($gwInfo['next']['id'] ?? 1);

        $publicUrl = "https://fantasy.premierleague.com/api/entry/{$teamId}/event/{$gw}/picks/";
        $publicRes = $this->curlRequest($publicUrl);

        if ($publicRes['status'] === 200) {
            $picksData = json_decode($publicRes['body'], true);
            if (!empty($picksData['picks']) && count($picksData['picks']) === 15) {
                return [
                    'status' => 'success',
                    'authenticated' => false,
                    'data' => [
                        'picks' => $picksData['picks'] ?? [],
                        'transfers' => [
                            'bank' => $picksData['entry_history']['bank'] ?? 0,
                            'limit' => $picksData['entry_history']['event_transfers'] ?? 1,
                            'made' => 0,
                        ]
                    ]
                ];
            }
        }

        // 4. Default Template Skuad Resmi 15 Pemain (2 GK, 5 DEF, 5 MID, 3 FWD)
        return $this->getMockTeamData();
    }

    /**
     * Template Skuad 15 Pemain Resmi FPL (2 GK, 5 DEF, 5 MID, 3 FWD)
     */
    private function getMockTeamData(): array {
        $bootstrap = $this->getBootstrapStatic();
        $elements = $bootstrap['elements'] ?? [];

        $elemByName = [];
        foreach ($elements as $el) {
            $elemByName[strtolower($el['web_name'])] = $el;
        }

        // 15 Pemain Template Resmi
        $targetNames = [
            // 2 GK
            'raya', 'dubravka',
            // 5 DEF
            'gabriel', 'gvardiol', 'pedro porro', 'mykolenko', 'robinson',
            // 5 MID
            'saka', 'palmer', 'semenyo', 'rogers', 'winks',
            // 3 FWD
            'haaland', 'isak', 'jo\u00e3o pedro'
        ];

        $picks = [];
        $pos = 1;
        foreach ($targetNames as $name) {
            $el = $elemByName[$name] ?? null;
            if (!$el) {
                foreach ($elements as $e) {
                    if (strpos(strtolower($e['web_name']), str_replace(' ', '', $name)) !== false) {
                        $el = $e;
                        break;
                    }
                }
            }

            if ($el) {
                $picks[] = [
                    'element' => $el['id'],
                    'position' => $pos,
                    'is_captain' => ($pos === 13), // Haaland Kapten
                    'is_vice_captain' => ($pos === 8), // Saka VC
                    'multiplier' => ($pos === 13) ? 2 : 1
                ];
                $pos++;
            }
            if (count($picks) === 15) break;
        }

        // Jika ada yang kurang dari 15, lengkapi berdasarkan tipe posisi
        if (count($picks) < 15) {
            $existing = array_column($picks, 'element');
            $typeSlots = [1 => 2, 2 => 5, 3 => 5, 4 => 3];
            $currentTypes = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            
            foreach ($picks as $p) {
                foreach ($elements as $el) {
                    if ($el['id'] === $p['element']) {
                        $currentTypes[$el['element_type']]++;
                        break;
                    }
                }
            }

            foreach ($typeSlots as $reqType => $maxReq) {
                while ($currentTypes[$reqType] < $maxReq) {
                    foreach ($elements as $el) {
                        if ($el['element_type'] === $reqType && !in_array($el['id'], $existing)) {
                            $existing[] = $el['id'];
                            $picks[] = [
                                'element' => $el['id'],
                                'position' => count($picks) + 1,
                                'is_captain' => false,
                                'is_vice_captain' => false,
                                'multiplier' => 1
                            ];
                            $currentTypes[$reqType]++;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'status' => 'success',
            'source' => 'balanced_template_15',
            'data' => [
                'picks' => $picks,
                'transfers' => [
                    'bank' => 0,
                    'limit' => 1,
                    'made' => 0,
                ]
            ]
        ];
    }

    public function login(): bool {
        if (!empty($this->customCookie)) return true;
        if (empty($this->email) || empty($this->password)) return false;

        $loginUrl = 'https://account.premierleague.com/as/authorization.oauth2';
        $postFields = http_build_query([
            'login' => $this->email,
            'password' => $this->password,
            'app' => 'plfpl-web',
            'redirect_uri' => 'https://fantasy.premierleague.com/'
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin: https://account.premierleague.com',
            'Referer: https://account.premierleague.com/',
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 || $httpCode === 302);
    }

    public function updateLineup(array $picks): array {
        $url = "https://fantasy.premierleague.com/api/my-team/{$this->teamId}/";
        $payload = json_encode(['picks' => $picks]);

        $res = $this->curlRequest($url, 'POST', $payload, true, [
            'Content-Type: application/json',
            'Referer: https://fantasy.premierleague.com/my-team'
        ]);

        if ($res['status'] === 200) {
            return ['status' => 'success', 'message' => 'Susunan pemain & Kapten berhasil diperbarui di FPL'];
        }

        return [
            'status' => 'error',
            'message' => 'Gagal update lineup: HTTP ' . $res['status'],
            'response' => $res['body']
        ];
    }

    public function executeTransfer(int $playerOutId, int $playerInId, int $chip = 0): array {
        $gwInfo = $this->getCurrentAndNextGameweek();
        $event = $gwInfo['next']['id'] ?? ($gwInfo['current']['id'] ?? 1);

        $url = "https://fantasy.premierleague.com/api/transfers/";
        $payload = json_encode([
            'chip' => $chip ? 'wildcard' : null,
            'entry' => (int)$this->teamId,
            'event' => $event,
            'transfers' => [
                [
                    'element_in' => $playerInId,
                    'element_out' => $playerOutId,
                    'purchase_price' => 0,
                    'selling_price' => 0
                ]
            ]
        ]);

        $res = $this->curlRequest($url, 'POST', $payload, true, [
            'Content-Type: application/json',
            'Referer: https://fantasy.premierleague.com/transfers'
        ]);

        if ($res['status'] === 200) {
            return ['status' => 'success', 'message' => 'Transfer berhasil dieksekusi ke FPL!'];
        }

        return [
            'status' => 'error',
            'message' => 'Transfer gagal dieksekusi: HTTP ' . $res['status'],
            'response' => $res['body']
        ];
    }

    private function saveCustomCookieToFile(string $cookieString): void {
        $lines = "# Netscape HTTP Cookie File\n";
        $pairs = explode(';', $cookieString);
        foreach ($pairs as $p) {
            $p = trim($p);
            if (strpos($p, '=') !== false) {
                list($k, $v) = explode('=', $p, 2);
                $lines .= ".premierleague.com\tTRUE\t/\tTRUE\t" . (time() + 86400 * 30) . "\t" . trim($k) . "\t" . trim($v) . "\n";
                $lines .= "fantasy.premierleague.com\tFALSE\t/\tTRUE\t" . (time() + 86400 * 30) . "\t" . trim($k) . "\t" . trim($v) . "\n";
            }
        }
        @file_put_contents($this->cookieFile, $lines);
    }

    private function curlRequest(string $url, string $method = 'GET', ?string $postData = null, bool $useCookies = false, array $customHeaders = []): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

        if ($useCookies && file_exists($this->cookieFile)) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }

        $headers = [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }

        if (!empty($customHeaders)) {
            $headers = array_merge($headers, $customHeaders);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body,
            'error' => $error
        ];
    }

    private function getCache(string $key, bool $allowExpired = false): ?array {
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $parsed = json_decode($content, true);
            if ($parsed && isset($parsed['expires_at'], $parsed['data'])) {
                if ($allowExpired || $parsed['expires_at'] > time()) {
                    return $parsed['data'];
                }
            }
        }
        return null;
    }

    private function setCache(string $key, array $data, int $ttlSeconds = 600): void {
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.json';
        $payload = json_encode([
            'expires_at' => time() + $ttlSeconds,
            'data' => $data
        ]);
        @file_put_contents($file, $payload);
    }
}
