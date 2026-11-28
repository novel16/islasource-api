<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\SiteSetting;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Validator;

class SiteSettingService
{
    public const array ALLOWED_BUILTIN_IMAGES = [
        '/images/hero-seafood.jpg',
        '/images/island.jpg',
        '/images/squid.jpg',
        '/images/crab.jpg',
        '/images/scallops.jpg',
        '/images/dried-fish.jpg',
    ];

    public const array LOCKED_TERM_SECTIONS = [
        ['id' => 'delivery-pickup', 'number' => '01'],
        ['id' => 'sourcing-quotes', 'number' => '02'],
        ['id' => 'payments-refunds', 'number' => '03'],
        ['id' => 'inspection', 'number' => '04'],
        ['id' => 'weather', 'number' => '05'],
        ['id' => 'privacy', 'number' => '06'],
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'business_name' => 'IslaSource Seafoods',
            'contact_email' => 'islasource.support@gmail.com',
            'contact_phone' => '',
            'business_address' => 'Baod, Bantayan, Cebu, Philippines',
            'island_min_kg' => 3,
            'cebu_min_kg' => 5,
            'default_markup_percent' => '20.00',
            'default_handling_centavos' => 0,
            'default_cebu_delivery_centavos' => 0,
            'default_validity_hours' => Quotation::DEFAULT_VALIDITY_HOURS,
            'default_quotation_terms' => Quotation::DEFAULT_TERMS,
            'homepage' => [
                'advisory_enabled' => false,
                'advisory_text' => '',
                'hero_eyebrow' => 'SEAFOOD SOURCING & RESALE',
                'hero_headline' => "Island seafood.\nSourced\nfor you.",
                'hero_subtext' => 'Meet IslaSource Seafoods. We source directly from local fishermen and seafood suppliers after your quote request, then arrange your order and delivery. Island connections, with fair pricing in mind.',
                'hero_image' => '/images/hero-seafood.jpg',
                'hero_image_alt' => 'Fresh whole fish displayed on a bed of ice',
                'hero_caption' => 'The good things come from the sea.',
                'story_heading' => "Your connection\nto the source.",
                'story_paragraphs' => [
                    'We’re a seafood sourcing and resale business based in Bantayan Island, Cebu. We work directly with local fishermen and suppliers to find seafood for home cooks, restaurants, and resellers.',
                    'We don’t keep our own seafood inventory. After your quote request, we check supplier availability, product condition, and current pricing. You review the details before we procure your order.',
                    'Buying to order and working directly with suppliers helps us aim for affordable, competitive quotes. Final prices and supply are confirmed for each request.',
                ],
                'story_image' => '/images/island.jpg',
                'story_image_alt' => 'A wooden boardwalk through a green mangrove forest on Bantayan',
            ],
            'terms_sections' => [
                [
                    'id' => 'delivery-pickup',
                    'number' => '01',
                    'title' => 'Service coverage, free island delivery & Cebu terminal pickup',
                    'paragraphs' => [
                        'IslaSource Seafoods is based in Baod, Bantayan, Cebu and currently serves mainland Bantayan Island and mainland Cebu only. Offshore islets requiring boat transfer (such as Kinatarcan or Hilantagaan) and provinces outside Cebu are not covered by standard online requests.',
                        'Minimum order quantities and fulfillment channels depend on your location:',
                    ],
                    'bullets' => [
                        'Within Mainland Bantayan Island (Bantayan, Santa Fe & Madridejos — Free Direct Delivery, 3 kg minimum): Orders of at least 3 kg are delivered directly to your barangay or landmark at no delivery charge (Free) from our base in Baod, Bantayan.',
                        'Mainland Cebu via Ceres Bus Cargo (Cebu North Bus Terminal pickup — 5 kg minimum, Recommended for Fresh & Frozen Seafood): Fastest transit from Bantayan Island to Cebu City. Fresh and frozen seafood packed on ice are sent via Ceres Bus for customer pickup directly at Cebu North Bus Terminal.',
                        'Mainland Cebu via Island Trucking such as Jessamae Trucking (Hub pickup — 5 kg minimum, Recommended for Dried Seafood): Transit typically takes around 1 day, making trucking ideal for dried fish (buwad). Customers claim their parcel directly at the designated Cebu trucking hub.',
                        'Timely pickup responsibility: Once dispatched, we share bus/waybill details and estimated arrival via your Order Messages and email. Customers are responsible for receiving or claiming cargo promptly—IslaSource Seafoods is not liable for spoilage caused by delayed customer pickup.',
                    ],
                ],
                [
                    'id' => 'sourcing-quotes',
                    'number' => '02',
                    'title' => 'On-demand sourcing & quotation validity',
                    'paragraphs' => [
                        'We do not hold pre-stocked seafood inventory. Catalog listings represent island seafood we source directly from Bantayan Island fishermen and local producers after receiving your guest quote request.',
                        'Prices, catch sizes, and availability vary daily with sea conditions. Confirmed pricing and availability apply only to your personal quotation and remain valid until the stated expiration timestamp.',
                    ],
                    'bullets' => [],
                ],
                [
                    'id' => 'payments-refunds',
                    'number' => '03',
                    'title' => '100% upfront payment (PayMongo QR Ph) & refund policy',
                    'paragraphs' => [
                        'Submitting a quote request is free and carries no obligation. Once you accept a quotation, 100% full upfront payment via PayMongo QR Ph Hosted Checkout is required before we purchase your seafood from island suppliers. Cash on delivery (COD) and partial deposits are not offered.',
                    ],
                    'bullets' => [
                        'Before supplier purchase: You may decline a quotation or cancel an unpaid order at no cost. If you have already paid via QR Ph and sudden rough seas or supply shortages prevent us from buying your catch, you may choose either a rescheduled catch date or a full refund.',
                        'After supplier purchase & packing: Because fresh and frozen seafood are perishable and bought specifically for your confirmed order, paid orders that have already been procured from suppliers or packed on Bantayan Island are strictly non-cancellable and non-refundable.',
                    ],
                ],
                [
                    'id' => 'inspection',
                    'number' => '04',
                    'title' => 'Inspection upon terminal pickup (2-hour window)',
                    'paragraphs' => [
                        'Please check your package as soon as you claim it at Cebu North Bus Terminal, the trucking hub, or your agreed Bantayan Island pickup point.',
                        'If an item is missing or arrives with a verifiable packing issue, message us on your private Order page (with clear photos or video of the unopened/opened styrofoam pack and waybill) within 2 hours of pickup so we can review and assist fairly.',
                    ],
                    'bullets' => [],
                ],
                [
                    'id' => 'weather',
                    'number' => '05',
                    'title' => 'Weather, sea conditions & ferry schedules',
                    'paragraphs' => [
                        'Island fishing and Bantayan–Cebu ferry crossings depend on Coast Guard clearances and weather conditions. If rough seas delay fishing or transit, we will notify you promptly via email or your Order page with the updated schedule.',
                    ],
                    'bullets' => [],
                ],
                [
                    'id' => 'privacy',
                    'number' => '06',
                    'title' => 'Guest privacy & payment security',
                    'paragraphs' => [
                        'No customer account is required. We collect only your name, email address, mobile number, Cebu/Bantayan pickup location, and order notes to prepare quotations, coordinate terminal pickup, and send transactional email updates.',
                        'QR Ph payments are processed directly by PayMongo on their encrypted checkout page. IslaSource Seafoods never sees or stores your bank login, card, or e-wallet PIN credentials, and we never sell your contact details to third parties.',
                    ],
                    'bullets' => [],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $defaults = self::defaults();
        $record = SiteSetting::query()->find(1);

        if ($record === null || ! is_array($record->payload)) {
            return $defaults;
        }

        $stored = $record->payload;
        $merged = array_replace($defaults, array_intersect_key($stored, $defaults));

        if (isset($stored['homepage']) && is_array($stored['homepage'])) {
            $merged['homepage'] = array_replace(
                $defaults['homepage'],
                array_intersect_key($stored['homepage'], $defaults['homepage']),
            );
        }

        if (isset($stored['terms_sections']) && is_array($stored['terms_sections']) && count($stored['terms_sections']) === count(self::LOCKED_TERM_SECTIONS)) {
            $sections = [];
            foreach (self::LOCKED_TERM_SECTIONS as $index => $locked) {
                $candidate = $stored['terms_sections'][$index] ?? [];
                $fallback = $defaults['terms_sections'][$index];
                $sections[] = [
                    'id' => $locked['id'],
                    'number' => $locked['number'],
                    'title' => is_string($candidate['title'] ?? null) ? $candidate['title'] : $fallback['title'],
                    'paragraphs' => is_array($candidate['paragraphs'] ?? null) ? array_values($candidate['paragraphs']) : $fallback['paragraphs'],
                    'bullets' => is_array($candidate['bullets'] ?? null) ? array_values($candidate['bullets']) : $fallback['bullets'],
                ];
            }
            $merged['terms_sections'] = $sections;
        }

        $merged['island_min_kg'] = $this->cleanNumber($merged['island_min_kg']);
        $merged['cebu_min_kg'] = $this->cleanNumber($merged['cebu_min_kg']);

        return $merged;
    }

    /** @return array<string, mixed> */
    public function publicSettings(): array
    {
        $all = $this->all();

        return [
            'business_name' => $all['business_name'],
            'contact_email' => $all['contact_email'],
            'contact_phone' => $all['contact_phone'],
            'business_address' => $all['business_address'],
            'island_min_kg' => $all['island_min_kg'],
            'cebu_min_kg' => $all['cebu_min_kg'],
            'homepage' => $all['homepage'],
            'terms_sections' => $all['terms_sections'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input, User $admin): array
    {
        $normalized = $this->validateAndNormalize($input);

        SiteSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'payload' => $normalized,
                'updated_by' => $admin->id,
            ],
        );

        return $this->all();
    }

    /** @return array<string, mixed> */
    public function reset(User $admin): array
    {
        $defaults = self::defaults();

        SiteSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'payload' => $defaults,
                'updated_by' => $admin->id,
            ],
        );

        return $this->all();
    }

    public static function isValidSiteImage(string $value): bool
    {
        return in_array($value, self::ALLOWED_BUILTIN_IMAGES, true)
            || preg_match('/\A\/storage\/products\/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)\z/i', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateAndNormalize(array $input): array
    {
        $imageRule = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! self::isValidSiteImage(trim($value))) {
                $fail('Select a built-in storefront image or upload a JPG, PNG, or WebP photo.');
            }
        };

        $rules = [
            'business_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email:rfc', 'max:150'],
            'contact_phone' => ['present', 'nullable', 'string', 'max:30'],
            'business_address' => ['required', 'string', 'max:200'],
            'island_min_kg' => ['required', 'numeric', 'min:0.5', 'max:500'],
            'cebu_min_kg' => ['required', 'numeric', 'min:0.5', 'max:500'],
            'default_markup_percent' => ['required', 'numeric', 'min:0', 'max:200'],
            'default_handling_centavos' => ['required', 'integer', 'min:0', 'max:50000000'],
            'default_cebu_delivery_centavos' => ['required', 'integer', 'min:0', 'max:50000000'],
            'default_validity_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'default_quotation_terms' => ['required', 'string', 'max:1000'],
            'homepage' => ['required', 'array'],
            'homepage.advisory_enabled' => ['required', 'boolean'],
            'homepage.advisory_text' => ['present', 'nullable', 'string', 'max:300'],
            'homepage.hero_eyebrow' => ['required', 'string', 'max:80'],
            'homepage.hero_headline' => ['required', 'string', 'max:160'],
            'homepage.hero_subtext' => ['required', 'string', 'max:500'],
            'homepage.hero_image' => ['required', 'string', $imageRule],
            'homepage.hero_image_alt' => ['required', 'string', 'max:160'],
            'homepage.hero_caption' => ['present', 'nullable', 'string', 'max:140'],
            'homepage.story_heading' => ['required', 'string', 'max:140'],
            'homepage.story_paragraphs' => ['required', 'array', 'min:1', 'max:5'],
            'homepage.story_paragraphs.*' => ['required', 'string', 'max:600'],
            'homepage.story_image' => ['required', 'string', $imageRule],
            'homepage.story_image_alt' => ['required', 'string', 'max:160'],
            'terms_sections' => ['required', 'array', 'size:'.count(self::LOCKED_TERM_SECTIONS)],
        ];

        foreach (self::LOCKED_TERM_SECTIONS as $index => $locked) {
            $rules["terms_sections.{$index}.id"] = [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($locked): void {
                    if ($value !== $locked['id']) {
                        $fail("Policy section ID must remain '{$locked['id']}'.");
                    }
                },
            ];
            $rules["terms_sections.{$index}.title"] = ['required', 'string', 'max:160'];
            $rules["terms_sections.{$index}.paragraphs"] = ['required', 'array', 'min:1', 'max:8'];
            $rules["terms_sections.{$index}.paragraphs.*"] = ['required', 'string', 'max:1000'];
            $rules["terms_sections.{$index}.bullets"] = ['present', 'array', 'max:10'];
            $rules["terms_sections.{$index}.bullets.*"] = ['required', 'string', 'max:1000'];
        }

        $validated = Validator::make($input, $rules)->validate();

        $sections = [];
        foreach (self::LOCKED_TERM_SECTIONS as $index => $locked) {
            $item = $validated['terms_sections'][$index];
            $sections[] = [
                'id' => $locked['id'],
                'number' => $locked['number'],
                'title' => trim((string) $item['title']),
                'paragraphs' => array_values(array_map(fn (string $p): string => trim($p), $item['paragraphs'])),
                'bullets' => array_values(array_map(fn (string $b): string => trim($b), $item['bullets'] ?? [])),
            ];
        }

        return [
            'business_name' => trim((string) $validated['business_name']),
            'contact_email' => strtolower(trim((string) $validated['contact_email'])),
            'contact_phone' => trim((string) ($validated['contact_phone'] ?? '')),
            'business_address' => trim((string) $validated['business_address']),
            'island_min_kg' => $this->cleanNumber($validated['island_min_kg']),
            'cebu_min_kg' => $this->cleanNumber($validated['cebu_min_kg']),
            'default_markup_percent' => number_format((float) $validated['default_markup_percent'], 2, '.', ''),
            'default_handling_centavos' => (int) $validated['default_handling_centavos'],
            'default_cebu_delivery_centavos' => (int) $validated['default_cebu_delivery_centavos'],
            'default_validity_hours' => (int) $validated['default_validity_hours'],
            'default_quotation_terms' => trim((string) $validated['default_quotation_terms']),
            'homepage' => [
                'advisory_enabled' => (bool) $validated['homepage']['advisory_enabled'],
                'advisory_text' => trim((string) ($validated['homepage']['advisory_text'] ?? '')),
                'hero_eyebrow' => trim((string) $validated['homepage']['hero_eyebrow']),
                'hero_headline' => trim((string) $validated['homepage']['hero_headline']),
                'hero_subtext' => trim((string) $validated['homepage']['hero_subtext']),
                'hero_image' => trim((string) $validated['homepage']['hero_image']),
                'hero_image_alt' => trim((string) $validated['homepage']['hero_image_alt']),
                'hero_caption' => trim((string) ($validated['homepage']['hero_caption'] ?? '')),
                'story_heading' => trim((string) $validated['homepage']['story_heading']),
                'story_paragraphs' => array_values(array_map(
                    fn (string $p): string => trim($p),
                    $validated['homepage']['story_paragraphs'],
                )),
                'story_image' => trim((string) $validated['homepage']['story_image']),
                'story_image_alt' => trim((string) $validated['homepage']['story_image_alt']),
            ],
            'terms_sections' => $sections,
        ];
    }

    private function cleanNumber(float|int|string $value): int|float
    {
        $rounded = round((float) $value, 2);

        return (float) ((int) $rounded) === $rounded ? (int) $rounded : $rounded;
    }
}
