<?php

namespace App;

use Exception;
use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

class App
{

    public function __construct(
        private ConfigProvider $configProvider,
    )
    {
    }

    public function run(
        string $path,
        ?string $httpMethod,
        ?string $queryParameters,
        ?string $authHeader
    ): void
    {
        if ($path === '/') {
            http_response_code(404);

            return;
        }

        $config = $this->configProvider->get();
        $apiToken = $config['apiToken'];

        if (! $authHeader || $authHeader !== 'Bearer ' . $apiToken) {
            http_response_code(401);
            
            return;
        }

        $request = substr($path, 1);

        $dbConfig = $config['db'];
        $fetcher = new DatabaseFetcher(new DatabaseConnection(
            $dbConfig['host'],
            $dbConfig['database'],
            $dbConfig['username'],
            $dbConfig['password'],
            DatabaseConnection::UTF8_MB4
        ));

        $queriedIds = $fetcher->query(
            $fetcher->createQuery(
                'unprocessable_request'
            )->select(
                'id'
            )->where(
                'request = :request'
            ),
            ['request' => $request]
        );

        if ($queriedIds) {
            http_response_code(404);

            return;
        }

        $youtubeVideoId = $request;

        if ($httpMethod === 'GET') {
            $videoId = $this->fetchVideo($fetcher, $youtubeVideoId);

            if ($videoId === null) {
                http_response_code(404);

                return;
            }

            http_response_code(200);
            echo json_encode($this->fetchSavedSubtitles($fetcher, $youtubeVideoId));

            return;
        }

        if ($httpMethod !== 'POST') {
            http_response_code(404);

            return;
        }

        set_time_limit(660);
        $downsubCurl = curl_init('https://downsub-api.ggio.fr/https://www.youtube.com/watch?v=' . $youtubeVideoId);
        curl_setopt_array($downsubCurl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json' , 'Authorization: Bearer ' . $apiToken],
            CURLOPT_TIMEOUT => 600
        ]);
        $downsubResponse = curl_exec($downsubCurl);
        $httpCode = curl_getinfo($downsubCurl)['http_code'];
        curl_close($downsubCurl);

        if ($httpCode === 400) {
            $fetcher->exec(
                $fetcher->createQuery(
                    'unprocessable_request'
                )->insertInto(
                    'request',
                    ':request'
                ),
                ['request' => $request]
            );
            http_response_code(404);

            return;
        }

        if ($httpCode === 401) {
            http_response_code(401);
            
            return;
        }

        if ($httpCode !== 200) {
            http_response_code(500);
            
            return;
        }

        if (! $downsubResponse) {
            http_response_code(500);
            
            return;
        }

        $downsubJsonResponse = json_decode($downsubResponse, true);

        if (! $downsubJsonResponse) {
            http_response_code(500);
            
            return;
        }

        try {
            $videoId = $this->fetchOrInsertVideo($fetcher, $youtubeVideoId);
        } catch (Exception) {
            http_response_code(500);

            return;
        }

        foreach ($downsubJsonResponse as $entry) {
            if (! isset($entry['language'])) {
                continue;
            }

            $languageName = $entry['language'];

            if (! isset($entry['subtitles'])) {
                continue;
            }

            $subtitles = $entry['subtitles'] ?? [];
            $content = json_encode($subtitles);

            $languageId = $this->fetchOrInsertLanguage($fetcher, $languageName);
            $this->fetchOrReplaceSubtitles($fetcher, $videoId, $languageId, $content);
        }

        http_response_code(200);
        echo json_encode($this->fetchSavedSubtitles($fetcher, $youtubeVideoId));

        return;
    }

    protected function fetchVideo(DatabaseFetcher $fetcher, string $youtubeId): ?int
    {
        $queriedVideos = $fetcher->query(
            $fetcher->createQuery(
                'video'
            )->select(
                'id'
            )->where(
                'youtube_id = :youtube_id'
            ),
            ['youtube_id' => $youtubeId]
        );

        return $queriedVideos ? ((int) $queriedVideos[0]['id']) : null;
    }

    protected function fetchOrInsertVideo(DatabaseFetcher $fetcher, string $youtubeId): int
    {
        $queriedVideoId = $this->fetchVideo($fetcher, $youtubeId);

        if ($queriedVideoId) {
            return $queriedVideoId;
        }

        $fetcher->exec(
            $fetcher->createQuery(
                'video'
            )->insertInto(
                'youtube_id',
                ':youtube_id'
            ),
            ['youtube_id' => $youtubeId]
        );

        $queriedVideoId = $this->fetchVideo($fetcher, $youtubeId);

        if ($queriedVideoId) {
            return $queriedVideoId;
        }

        throw new Exception('Failed to return a video id');
    }

    protected function fetchLanguage(DatabaseFetcher $fetcher, string $name): ?int
    {
        $queriedLanguages = $fetcher->query(
            $fetcher->createQuery(
                'language'
            )->select(
                'id'
            )->where(
                'name = :name'
            ),
            ['name' => $name]
        );

        return $queriedLanguages ? ((int) $queriedLanguages[0]['id']) : null;
    }

    protected function fetchOrInsertLanguage(DatabaseFetcher $fetcher, string $name): int
    {
        $queriedLanguageId = $this->fetchLanguage($fetcher, $name);

        if ($queriedLanguageId) {
            return $queriedLanguageId;
        }

        $fetcher->exec(
            $fetcher->createQuery(
                'language'
            )->insertInto(
                'name',
                ':name'
            ),
            ['name' => $name]
        );

        $queriedLanguageId = $this->fetchLanguage($fetcher, $name);

        if ($queriedLanguageId) {
            return $queriedLanguageId;
        }

        throw new Exception('Failed to return a language id');
    }

    protected function fetchSubtitles(DatabaseFetcher $fetcher, int $videoId, int $languageId, string $content): ?int
    {
        $queriedSubtitles = $fetcher->query(
            $fetcher->createQuery(
                'subtitle'
            )->select(
                'id'
            )->where(
                'video_id = :video_id AND language_id = :language_id AND content = :content AND deleted_at is NULL'
            ),
            [
                'video_id' => $videoId,
                'language_id' => $languageId,
                'content' => $content
            ]
        );

        return $queriedSubtitles ? ((int) $queriedSubtitles[0]['id']) : null;
    }

    protected function fetchOrReplaceSubtitles(DatabaseFetcher $fetcher, int $videoId, int $languageId, string $content): int
    {
        $queriedSubtitlesId = $this->fetchSubtitles($fetcher, $videoId, $languageId, $content);

        if ($queriedSubtitlesId) {
            return $queriedSubtitlesId;
        }

        $fetcher->exec(
            $fetcher->createQuery(
                'subtitle'
            )->update(
                'deleted_at = NOW()'
            )->where(
                'video_id = :video_id AND language_id = :language_id AND deleted_at is NULL'
            ),
            [
                'video_id' => $videoId,
                'language_id' => $languageId
            ]
        );

        $fetcher->exec(
            $fetcher->createQuery(
                'subtitle'
            )->insertInto(
                'video_id,language_id,content',
                ':video_id,:language_id,:content'
            ),
            [
                'video_id' => $videoId,
                'language_id' => $languageId,
                'content' => $content
            ]
        );

        $queriedSubtitlesId = $this->fetchSubtitles($fetcher, $videoId, $languageId, $content);

        if ($queriedSubtitlesId) {
            return $queriedSubtitlesId;
        }

        throw new Exception('Failed to return a subtitle id');
    }

    protected function fetchSavedSubtitles(DatabaseFetcher $fetcher, string $youtubeId): array
    {
        return array_map(
            function (array $entry): array {
                $entry['subtitles'] = json_decode($entry['subtitles'], true);

                return $entry;
            },
            $fetcher->rawQuery(
                'SELECT l.name as language, s.content as subtitles
                FROM `video` as v
                LEFT JOIN `subtitle` as s ON s.video_id = v.id AND s.deleted_at is NULL
                LEFT JOIN `language` as l ON l.id = s.language_id
                WHERE v.youtube_id = :youtube_id',
                ['youtube_id' => $youtubeId]
            )
        );
    }
}
