<?php

namespace App\Console\Commands;

use App\Models\CrmToken;
use App\Support\Crm\CrmSession;
use Illuminate\Console\Command;
use Throwable;

/**
 * Zanjeer CRM (Livewire asosidagi) login sahifasiga headless Chrome orqali
 * kirib, XSRF-TOKEN va session cookie'larni crm_tokens jadvaliga saqlaydi.
 */
class LoginToCrm extends Command
{
    protected $signature = 'crm:login
                            {--email= : .env dagi CRM_LOGIN_EMAIL o\'rniga ishlatiladi}
                            {--password= : .env dagi CRM_LOGIN_PASSWORD o\'rniga ishlatiladi}
                            {--timeout=30 : Login yakunlanishini kutish vaqti (soniya)}
                            {--fresh : chromedriver allaqachon ishlab tursa ham, yangisini ishga tushirish}';

    protected $description = 'Zanjeer CRM ga headless brauzer orqali kirib, XSRF-TOKEN va session cookie larni bazaga saqlaydi';

    public function handle(): int
    {
        $config = config('crm.zanjeer');

        $email = $this->option('email') ?: $config['email'];
        $password = $this->option('password') ?: $config['password'];

        if (! $email || ! $password) {
            $this->error('CRM_LOGIN_EMAIL va CRM_LOGIN_PASSWORD .env faylida (yoki --email= / --password= bilan) berilishi shart.');

            return self::FAILURE;
        }

        $session = null;

        try {
            $this->info('chromedriver va brauzer tayyorlanmoqda...');
            $session = CrmSession::start($config, (bool) $this->option('fresh'));

            $this->info('Login sahifasi ochilmoqda: ' . $config['login_url']);
            $session->login($config, $email, $password, (int) $this->option('timeout'));

            $cookies = $session->driver->manage()->getCookies();

            $xsrfToken = null;
            $sessionCookie = null;
            $sessionCookieName = $config['session_cookie_name'];

            foreach ($cookies as $cookie) {
                if ($cookie->getName() === 'XSRF-TOKEN') {
                    $xsrfToken = $cookie->getValue();
                }

                if ($cookie->getName() === $sessionCookieName) {
                    $sessionCookie = $cookie->getValue();
                }
            }

            if (! $sessionCookie) {
                $this->error("'{$sessionCookieName}' nomli session cookie topilmadi. CRM_SESSION_COOKIE_NAME to'g'ri sozlanganini tekshiring (config('session.cookie') qiymatiga mos bo'lishi kerak).");

                return self::FAILURE;
            }

            CrmToken::updateOrCreate(
                ['name' => 'zanjeer_crm'],
                [
                    'xsrf_token' => $xsrfToken ? urldecode($xsrfToken) : null,
                    'session_cookie' => $sessionCookie,
                    'session_cookie_name' => $sessionCookieName,
                    'fetched_at' => now(),
                ]
            );

            $this->info('Tokenlar muvaffaqiyatli saqlandi (crm_tokens jadvali, name=zanjeer_crm).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Xatolik yuz berdi: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            $session?->quit();
        }
    }
}
