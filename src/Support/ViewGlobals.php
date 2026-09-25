<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\MessageRepository;
use App\Repository\ServiceRepository;
use Slim\Views\Twig;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Tüm Twig şablonlarına ortak değişken ve fonksiyonları ekler.
 */
final class ViewGlobals
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ServiceRepository $services,
        private readonly array $appConfig,
        private readonly ?MessageRepository $messages = null
    ) {
    }

    public function apply(Twig $twig): void
    {
        $env = $twig->getEnvironment();

        $twig->getEnvironment()->addGlobal('site', $this->settings->all());
        $twig->getEnvironment()->addGlobal('app', [
            'name' => $this->appConfig['name'],
            'url'  => $this->appConfig['url'],
            'year' => (int) date('Y'),
        ]);
        // Menü için aktif hizmetler.
        $twig->getEnvironment()->addGlobal('menu_services', $this->services->allActive());

        // WhatsApp linki üreteci.
        $env->addFunction(new TwigFunction('whatsapp_link', function (?string $text = null): string {
            $phone = preg_replace('/\D+/', '', (string) $this->settings->get('whatsapp', ''));
            if ($phone !== '' && !str_starts_with($phone, '90')) {
                $phone = '90' . ltrim($phone, '0');
            }
            $url = 'https://api.whatsapp.com/send?phone=' . $phone;
            if ($text) {
                $url .= '&text=' . rawurlencode($text);
            }
            return $url;
        }));

        // Telefonu tel: formatına çevirir.
        $env->addFunction(new TwigFunction('tel_link', function (string $phone): string {
            $digits = preg_replace('/\D+/', '', $phone);
            if (!str_starts_with($digits, '90') && strlen($digits) === 10) {
                $digits = '90' . $digits;
            }
            return 'tel:+' . $digits;
        }));

        // Metni güvenli özet haline getirir.
        $env->addFilter(new TwigFilter('excerpt', function (?string $html, int $len = 160): string {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
            return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
        }));

        // Okunmamış mesaj sayısı (yalnızca admin şablonlarında çağrılır).
        $env->addFunction(new TwigFunction('unread_messages', function (): int {
            return $this->messages?->unreadCount() ?? 0;
        }));

        // İşletme (AutoRepair / LocalBusiness) yapısal verisi — Google zengin sonuçları.
        $env->addFunction(new TwigFunction('schema_business', function (): string {
            $s = $this->settings->all();
            $url = rtrim($this->appConfig['url'] ?? '', '/');

            $data = array_filter([
                '@context'    => 'https://schema.org',
                '@type'       => 'AutoRepair',
                'name'        => $s['brand_name'] ?? 'Çözüm Oto Elektrik',
                'description' => $s['site_description'] ?? null,
                'url'         => $url ?: null,
                'telephone'   => $s['phone_primary'] ?? null,
                'email'       => $s['email'] ?? null,
                'image'       => $s['og_image'] ?? ($url ? $url . '/favicon.svg' : null),
                'priceRange'  => '₺₺',
                'areaServed'  => 'İstanbul',
            ]);

            if (!empty($s['address'])) {
                $data['address'] = [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $s['address'],
                    'addressLocality' => 'Esenyurt',
                    'addressRegion'   => 'İstanbul',
                    'addressCountry'  => 'TR',
                ];
            }
            if (!empty($s['working_hours'])) {
                $data['openingHours'] = $s['working_hours'];
            }
            $sameAs = array_values(array_filter([
                $s['instagram'] ?? null, $s['facebook'] ?? null,
                $s['youtube'] ?? null, $s['linkedin'] ?? null, $s['twitter'] ?? null,
            ]));
            if ($sameAs) {
                $data['sameAs'] = $sameAs;
            }

            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }));

        // SSS yapısal verisi (FAQPage) — SSS listesinden üretir.
        $env->addFunction(new TwigFunction('schema_faq', function (array $faqs): ?string {
            if (!$faqs) {
                return null;
            }
            $items = [];
            foreach ($faqs as $f) {
                $items[] = [
                    '@type'          => 'Question',
                    'name'           => $f['question'] ?? '',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => strip_tags((string) ($f['answer'] ?? '')),
                    ],
                ];
            }
            return json_encode([
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }));
    }
}
