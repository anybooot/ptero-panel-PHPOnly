<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator Settings (General, Mail, Advanced).
 */
class SettingsHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $appName = self::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));
        $appLocale = self::getSetting('settings::app:locale', Config::get('APP_LOCALE', 'en'));

        $html = View::render('admin.settings.index', [
            'user' => $user,
            'appName' => $appName,
            'appLocale' => $appLocale,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'settings',
            'activeSettingTab' => 'general',
        ]);

        return Response::html($html);
    }

    public function updateGeneral(Request $request): Response
    {
        $appName = trim((string)$request->input('app:name', 'Pterodactyl'));
        $appLocale = trim((string)$request->input('app:locale', 'en'));

        self::setSetting('settings::app:name', $appName);
        self::setSetting('settings::app:locale', $appLocale);

        Session::flash('success', 'General settings updated successfully.');
        return Response::redirect('/admin/settings');
    }

    public function mail(Request $request): Response
    {
        $user = $request->getAttribute('user');

        $mailHost = self::getSetting('settings::mail:host', Config::get('MAIL_HOST', 'smtp.example.com'));
        $mailPort = self::getSetting('settings::mail:port', Config::get('MAIL_PORT', 25));
        $mailUser = self::getSetting('settings::mail:username', Config::get('MAIL_USERNAME', ''));
        $mailEnc = self::getSetting('settings::mail:encryption', Config::get('MAIL_ENCRYPTION', 'tls'));
        $mailFrom = self::getSetting('settings::mail:from:address', Config::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $mailFromName = self::getSetting('settings::mail:from:name', Config::get('MAIL_FROM_NAME', 'Pterodactyl Panel'));

        $html = View::render('admin.settings.mail', [
            'user' => $user,
            'mailHost' => $mailHost,
            'mailPort' => $mailPort,
            'mailUser' => $mailUser,
            'mailEnc' => $mailEnc,
            'mailFrom' => $mailFrom,
            'mailFromName' => $mailFromName,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'settings',
            'activeSettingTab' => 'mail',
        ]);

        return Response::html($html);
    }

    public function updateMail(Request $request): Response
    {
        self::setSetting('settings::mail:host', (string)$request->input('mail:host', ''));
        self::setSetting('settings::mail:port', (string)$request->input('mail:port', '25'));
        self::setSetting('settings::mail:username', (string)$request->input('mail:username', ''));
        if ($pwd = (string)$request->input('mail:password', '')) {
            self::setSetting('settings::mail:password', $pwd);
        }
        self::setSetting('settings::mail:encryption', (string)$request->input('mail:encryption', 'tls'));
        self::setSetting('settings::mail:from:address', (string)$request->input('mail:from:address', ''));
        self::setSetting('settings::mail:from:name', (string)$request->input('mail:from:name', ''));

        Session::flash('success', 'Mail settings updated.');
        return Response::redirect('/admin/settings/mail');
    }

    public function advanced(Request $request): Response
    {
        $user = $request->getAttribute('user');

        $recaptchaEnabled = self::getSetting('settings::recaptcha:enabled', 'false') === 'true';
        $recaptchaSite = self::getSetting('settings::recaptcha:website_key', '');
        $recaptchaSecret = self::getSetting('settings::recaptcha:secret_key', '');
        $behindProxy = self::getSetting('settings::app:behind_proxy', 'false') === 'true';
        $twoFactorRequirement = (int)self::getSetting('settings::auth:2fa_required', 0);
        $consoleCount = (int)self::getSetting('settings::pterodactyl:console:count', 200);
        $consoleFrequency = (int)self::getSetting('settings::pterodactyl:console:frequency', 0);
        $allocationsEnabled = self::getSetting('settings::pterodactyl:client_features:allocations:enabled', 'true') === 'true';
        $allocRangeStart = (int)self::getSetting('settings::pterodactyl:client_features:allocations:range_start', 1024);
        $allocRangeEnd = (int)self::getSetting('settings::pterodactyl:client_features:allocations:range_end', 65535);
        $pwdMinLength = (int)self::getSetting('settings::auth:password_min_length', 8);
        $pwdRequireMixed = self::getSetting('settings::auth:password_require_uppercase', 'false') === 'true';
        $pwdRequireNumbers = self::getSetting('settings::auth:password_require_numbers', 'false') === 'true';
        $pwdRequireSymbols = self::getSetting('settings::auth:password_require_symbols', 'false') === 'true';

        $html = View::render('admin.settings.advanced', [
            'user' => $user,
            'recaptchaEnabled' => $recaptchaEnabled,
            'recaptchaSite' => $recaptchaSite,
            'recaptchaSecret' => $recaptchaSecret,
            'behindProxy' => $behindProxy,
            'twoFactorRequirement' => $twoFactorRequirement,
            'consoleCount' => $consoleCount,
            'consoleFrequency' => $consoleFrequency,
            'allocationsEnabled' => $allocationsEnabled,
            'allocRangeStart' => $allocRangeStart,
            'allocRangeEnd' => $allocRangeEnd,
            'pwdMinLength' => $pwdMinLength,
            'pwdRequireMixed' => $pwdRequireMixed,
            'pwdRequireNumbers' => $pwdRequireNumbers,
            'pwdRequireSymbols' => $pwdRequireSymbols,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'settings',
            'activeSettingTab' => 'advanced',
        ]);

        return Response::html($html);
    }

    public function updateAdvanced(Request $request): Response
    {
        $recaptchaEnabled = $request->boolean('recaptcha:enabled', false) ? 'true' : 'false';
        self::setSetting('settings::recaptcha:enabled', $recaptchaEnabled);
        self::setSetting('settings::recaptcha:website_key', (string)$request->input('recaptcha:website_key', ''));
        if ($secret = (string)$request->input('recaptcha:secret_key', '')) {
            self::setSetting('settings::recaptcha:secret_key', $secret);
        }

        self::setSetting('settings::app:behind_proxy', $request->boolean('app:behind_proxy', false) ? 'true' : 'false');
        self::setSetting('settings::auth:2fa_required', (string)(int)$request->input('auth:2fa_required', 0));
        self::setSetting('settings::pterodactyl:console:count', (string)max(50, min(1000, (int)$request->input('pterodactyl:console:count', 200))));
        self::setSetting('settings::pterodactyl:console:frequency', (string)max(0, min(5000, (int)$request->input('pterodactyl:console:frequency', 0))));
        self::setSetting('settings::pterodactyl:client_features:allocations:enabled', $request->boolean('pterodactyl:client_features:allocations:enabled', false) ? 'true' : 'false');
        self::setSetting('settings::pterodactyl:client_features:allocations:range_start', (string)(int)$request->input('pterodactyl:client_features:allocations:range_start', 1024));
        self::setSetting('settings::pterodactyl:client_features:allocations:range_end', (string)(int)$request->input('pterodactyl:client_features:allocations:range_end', 65535));
        self::setSetting('settings::auth:password_min_length', (string)max(8, min(64, (int)$request->input('auth:password_min_length', 8))));
        self::setSetting('settings::auth:password_require_uppercase', $request->boolean('auth:password_require_uppercase', false) ? 'true' : 'false');
        self::setSetting('settings::auth:password_require_numbers', $request->boolean('auth:password_require_numbers', false) ? 'true' : 'false');
        self::setSetting('settings::auth:password_require_symbols', $request->boolean('auth:password_require_symbols', false) ? 'true' : 'false');

        Session::flash('success', 'Advanced settings updated successfully.');
        return Response::redirect('/admin/settings/advanced');
    }

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $row = Database::queryOne("SELECT `value` FROM `settings` WHERE `key` = :key LIMIT 1", ['key' => $key]);
        return $row['value'] ?? $default;
    }

    public static function setSetting(string $key, string $value): void
    {
        $existing = Database::queryOne("SELECT `id` FROM `settings` WHERE `key` = :key LIMIT 1", ['key' => $key]);
        if ($existing) {
            Database::execute("UPDATE `settings` SET `value` = :val WHERE `id` = :id", ['val' => $value, 'id' => $existing['id']]);
        } else {
            Database::insert('settings', ['key' => $key, 'value' => $value]);
        }
    }
}
