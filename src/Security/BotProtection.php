<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use Pterodactyl\Core\Config;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Multi-layer Bot and Automated Scanner Protection.
 * Includes honeypot fields, submission time-gating, and scanner User-Agent blocking.
 */
class BotProtection
{
    /**
     * Known malicious automated vulnerability scanners and exploit tools.
     */
    private const BLOCKED_USER_AGENTS = [
        'sqlmap',
        'nikto',
        'acunetix',
        'wpscan',
        'dirbuster',
        'gobuster',
        'masscan',
        'nmap',
        'zgrab',
        'censys',
        'shodan',
        'havij',
        'libwww-perl',
        'paros',
        'webinspect',
        'netsparker',
    ];

    /**
     * Generates HTML honeypot fields and time token to be embedded in forms.
     */
    public static function generateHoneypotHtml(): string
    {
        $now = time();
        $appKey = (string)Config::get('APP_KEY', 'ptero_bot_salt');
        $hash = hash_hmac('sha256', (string)$now, $appKey);
        $timeToken = base64_encode("{$now}|{$hash}");

        // Invisible inputs styled with multiple techniques to fool bots while invisible to users
        return <<<HTML
<div style="display:none !important; position:absolute !important; left:-9999px !important; visibility:hidden !important;" aria-hidden="true">
    <input type="text" name="_hp_bio" value="" tabindex="-1" autocomplete="off">
    <input type="hidden" name="_hp_token" value="{$timeToken}">
</div>
HTML;
    }

    /**
     * Validates that an incoming form request is from a legitimate human browser.
     * Returns null if valid, or an error message string if a bot is detected.
     */
    public static function validateForm(Request $request, int $minSeconds = 1, int $maxSeconds = 7200): ?string
    {
        // 1. Honeypot check: If the hidden honeypot field has been filled in, it's a bot
        $honeypot = $request->input('_hp_bio');
        if ($honeypot !== null && $honeypot !== '') {
            return 'Bot submission detected: honeypot field was filled.';
        }

        // 2. Time-gate check: Validate submission speed
        $token = (string)$request->input('_hp_token', '');
        if ($token !== '') {
            $decoded = base64_decode($token, true);
            if ($decoded && str_contains($decoded, '|')) {
                [$timestampStr, $hash] = explode('|', $decoded, 2);
                $timestamp = (int)$timestampStr;
                $appKey = (string)Config::get('APP_KEY', 'ptero_bot_salt');
                $expectedHash = hash_hmac('sha256', (string)$timestamp, $appKey);

                if (hash_equals($expectedHash, $hash)) {
                    $elapsed = time() - $timestamp;
                    if ($elapsed < $minSeconds) {
                        return 'Form submitted too rapidly. Please try again.';
                    }
                    if ($elapsed > $maxSeconds) {
                        return 'Form session expired. Please refresh and try again.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * Checks if the request comes from a known malicious scanning tool.
     */
    public static function isMaliciousScanner(Request $request): bool
    {
        $ua = strtolower($request->getHeader('user-agent') ?? '');
        if ($ua === '') {
            return false;
        }

        foreach (self::BLOCKED_USER_AGENTS as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }
}
