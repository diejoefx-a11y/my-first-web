<?php
/**
 * FPL-BOT - YouTube Consensus & AI Video Insight Service
 */

require_once __DIR__ . '/../config/database.php';

class YoutubeService {
    private PDO $db;
    private string $geminiApiKey;

    public function __construct() {
        $config = require __DIR__ . '/../config/config.php';
        $this->db = Database::getConnection();
        $this->geminiApiKey = $config['ai']['gemini_api_key'] ?? '';
    }

    /**
     * Ekstrak Video ID dari URL YouTube
     */
    public function extractVideoId(string $url): ?string {
        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Ambil metadata video (Judul & Channel) via oEmbed API resmi (No API key needed)
     */
    public function getVideoMetadata(string $url): array {
        $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $oembedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $res) {
            $data = json_decode($res, true);
            return [
                'title' => $data['title'] ?? 'YouTube FPL Video',
                'channel' => $data['author_name'] ?? 'FPL Analyst'
            ];
        }

        return [
            'title' => 'YouTube FPL Video (' . substr($url, 0, 30) . '...)',
            'channel' => 'FPL Community'
        ];
    }

    /**
     * Simpan analisis YouTube untuk Gameweek tertentu
     */
    public function saveVideoInsight(int $gameweek, string $videoUrl, array $recommendedBuys = [], array $recommendedSells = [], array $recommendedCaptains = [], string $notes = ''): array {
        $meta = $this->getVideoMetadata($videoUrl);

        // Jika user tidak mengisi manual rekomendasi dan ada Gemini API key, coba auto-analyze
        if (empty($recommendedBuys) && !empty($this->geminiApiKey)) {
            $aiResult = $this->analyzeWithGemini($meta['title'] . "\n" . $notes);
            if (!empty($aiResult['buys'])) $recommendedBuys = $aiResult['buys'];
            if (!empty($aiResult['sells'])) $recommendedSells = $aiResult['sells'];
            if (!empty($aiResult['captains'])) $recommendedCaptains = $aiResult['captains'];
            if (!empty($aiResult['summary'])) $notes = $aiResult['summary'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO `youtube_consensus` (`gameweek`, `video_url`, `video_title`, `channel_name`, `recommended_buys`, `recommended_sells`, `recommended_captains`, `summary_notes`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $gameweek,
            $videoUrl,
            $meta['title'],
            $meta['channel'],
            json_encode($recommendedBuys),
            json_encode($recommendedSells),
            json_encode($recommendedCaptains),
            $notes
        ]);

        return [
            'status' => 'success',
            'message' => 'Analisis YouTube berhasil disimpan!',
            'data' => [
                'gameweek' => $gameweek,
                'title' => $meta['title'],
                'channel' => $meta['channel'],
                'buys' => $recommendedBuys,
                'sells' => $recommendedSells,
                'captains' => $recommendedCaptains
            ]
        ];
    }

    /**
     * Ambil analisis konsensus untuk Gameweek aktif dengan Fitur Fallback
     */
    public function getConsensusForGameweek(int $gameweek): ?array {
        // Coba ambil untuk GW saat ini
        $stmt = $this->db->prepare("
            SELECT * FROM `youtube_consensus` 
            WHERE `gameweek` = ? 
            ORDER BY `id` DESC LIMIT 1
        ");
        $stmt->execute([$gameweek]);
        $row = $stmt->fetch();

        // Fallback: Jika belum ada input untuk GW ini, gunakan video tersimpan paling baru
        $isFallback = false;
        if (!$row) {
            $stmt = $this->db->query("
                SELECT * FROM `youtube_consensus` 
                ORDER BY `gameweek` DESC, `id` DESC LIMIT 1
            ");
            $row = $stmt->fetch();
            $isFallback = true;
        }

        if ($row) {
            return [
                'id' => $row['id'],
                'gameweek' => (int)$row['gameweek'],
                'video_url' => $row['video_url'],
                'video_title' => $row['video_title'],
                'channel_name' => $row['channel_name'],
                'recommended_buys' => json_decode($row['recommended_buys'] ?? '[]', true) ?: [],
                'recommended_sells' => json_decode($row['recommended_sells'] ?? '[]', true) ?: [],
                'recommended_captains' => json_decode($row['recommended_captains'] ?? '[]', true) ?: [],
                'summary_notes' => $row['summary_notes'],
                'is_fallback' => $isFallback,
                'created_at' => $row['created_at']
            ];
        }

        return null;
    }

    /**
     * AI Parser via Google Gemini API
     */
    private function analyzeWithGemini(string $text): array {
        if (empty($this->geminiApiKey)) return [];

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $this->geminiApiKey;

        $prompt = "Berikut adalah judul/catatan video FPL:\n\"$text\"\nEkstrak nama pemain FPL dalam format JSON persis:\n" .
                  '{"buys": ["Nama Pemain"], "sells": ["Nama Pemain"], "captains": ["Nama Pemain"], "summary": "Ringkasan 1 kalimat"}';

        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $data = json_decode($res, true);
            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            // Ekstrak blok json
            if (preg_match('/\{.*\}/s', $rawText, $m)) {
                return json_decode($m[0], true) ?: [];
            }
        }

        return [];
    }
}
