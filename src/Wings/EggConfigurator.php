<?php

declare(strict_types=1);

namespace Pterodactyl\Wings;

use Pterodactyl\Core\Database;

/**
 * Builds the process configuration and parsed config files for Wings daemon.
 */
class EggConfigurator
{
    public static function build(array $server): array
    {
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        if (!$egg) {
            return [
                'startup' => ['done' => [], 'user_interaction' => [], 'strip_ansi' => false],
                'stop' => ['type' => 'command', 'value' => 'stop'],
                'configs' => [],
            ];
        }

        // Parse startup configuration
        $startupContent = $egg['config_startup'] ?? $egg['inherit_config_startup'] ?? '';
        $startupRaw = !empty($startupContent) ? json_decode((string)$startupContent, true) : [];
        $done = $startupRaw['done'] ?? [];
        $startup = [
            'done' => is_string($done) ? [$done] : (is_array($done) ? $done : []),
            'user_interaction' => [],
            'strip_ansi' => (bool)($startupRaw['strip_ansi'] ?? false),
        ];

        // Parse stop configuration
        $stopRaw = (string)($egg['config_stop'] ?? $egg['inherit_config_stop'] ?? 'stop');
        if (str_starts_with($stopRaw, '^')) {
            $stop = [
                'type' => 'signal',
                'value' => strtoupper(substr($stopRaw, 1)),
            ];
        } else {
            $stop = [
                'type' => 'command',
                'value' => $stopRaw,
            ];
        }

        // Parse and replace config files
        $filesContent = $egg['config_files'] ?? $egg['inherit_config_files'] ?? '';
        $configsRaw = !empty($filesContent) ? json_decode((string)$filesContent, true) : [];
        $configs = [];

        if (is_array($configsRaw)) {
            $structure = ServerConfigurator::buildLegacy($server);

            foreach ($configsRaw as $file => $data) {
                if (!is_array($data) || !isset($data['find'])) {
                    continue;
                }

                $append = array_merge($data, ['file' => $file, 'replace' => []]);

                foreach ($data['find'] as $find => $replace) {
                    if (is_array($replace)) {
                        foreach ($replace as $match => $replaceWith) {
                            $append['replace'][] = [
                                'match' => $find,
                                'if_value' => $match,
                                'replace_with' => self::replacePlaceholders((string)$replaceWith, $structure),
                            ];
                        }
                    } else {
                        $append['replace'][] = [
                            'match' => $find,
                            'replace_with' => self::replacePlaceholders((string)$replace, $structure),
                        ];
                    }
                }

                unset($append['find']);
                $configs[] = $append;
            }
        }

        return [
            'startup' => $startup,
            'stop' => $stop,
            'configs' => $configs,
        ];
    }

    private static function replacePlaceholders(string $value, array $structure): string
    {
        return preg_replace_callback('/\{\{([\w.-]+)\}\}/', function ($matches) use ($structure) {
            $key = $matches[1];

            // Normalize legacy keys
            if ($key === 'config.docker.interface') {
                return '{{config.docker.network.interface}}';
            }
            if ($key === 'server.build.env.SERVER_MEMORY' || $key === 'env.SERVER_MEMORY') {
                $key = 'server.build.memory';
            } elseif ($key === 'server.build.env.SERVER_IP' || $key === 'env.SERVER_IP') {
                $key = 'server.build.default.ip';
            } elseif ($key === 'server.build.env.SERVER_PORT' || $key === 'env.SERVER_PORT') {
                $key = 'server.build.default.port';
            }

            if (str_starts_with($key, 'config.')) {
                return "{{{$key}}}";
            }

            if (str_starts_with($key, 'server.')) {
                $path = substr($key, 7);
                return (string)self::getNestedValue($structure, $path);
            }

            if (str_starts_with($key, 'env.')) {
                $envKey = substr($key, 4);
                return (string)($structure['build']['env'][$envKey] ?? '');
            }

            return $matches[0];
        }, $value);
    }

    private static function getNestedValue(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $cur = $data;
        foreach ($segments as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return '';
            }
            $cur = $cur[$seg];
        }
        return $cur;
    }
}
