<?php

namespace App\Support;

/**
 * Simple product illustrations (SVG): demo stock pictures, website category tiles and ad banners.
 * Every drawing uses a 400×300 canvas with a transparent background.
 */
class ProductArt
{
    public static function make(string $type, array $o = []): string
    {
        $c = $o['color'] ?? '#E31B23';
        $label = htmlspecialchars($o['label'] ?? '', ENT_XML1);
        $body = match ($type) {
            'tyre'        => self::tyre($label, $o['tread'] ?? 'at'),
            'oil'         => self::oil($c, $label, $o['size'] ?? '4L'),
            'oil_filter'  => self::oilFilter($c),
            'air_filter'  => self::panelFilter('#F4F4F2', '#E2E0D8', $o['frame'] ?? '#2B2D31'),
            'cabin_filter'=> self::panelFilter('#FFFFFF', '#E6EAEE', '#4B5563'),
            'fuel_filter' => self::fuelFilter($c),
            'brake_pads'  => self::brakePads(),
            'brake_disc'  => self::brakeDisc(),
            'shock'       => self::shock($c),
            'spark_plug'  => self::sparkPlug(),
            'battery'     => self::battery($c, $label),
            'wiper'       => self::wiper(),
            'bulb'        => self::bulb($label),
            'belt'        => self::belt(),
            'coolant'     => self::bottle($c, $label),
            'mats'        => self::mats(),
            'seat_cover'  => self::seat($c),
            'phone_holder'=> self::phoneHolder(),
            'jack'        => self::jack($c),
            'dashcam'     => self::dashcam(),
            'jump_starter'=> self::jumpStarter($c, $label),
            'paint'       => self::paint($c),
            default       => self::oilFilter($c),
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="800" height="600">'.self::defs().$body.'</svg>';
    }

    /** For <img src="…">: the drawing as a data URI (no file needed). */
    public static function dataUri(string $type, array $o = []): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::make($type, $o));
    }

    /** Picture used for a department / type tile when no product photo is available. */
    public static function forCategory(?string $category, ?string $sub = null, ?string $name = null): array
    {
        $map = [
            'Brake Pads' => ['brake_pads'], 'Brake Discs' => ['brake_disc'], 'Shock Absorbers' => ['shock'], 'Spark Plugs' => ['spark_plug'],
            'Batteries' => ['battery', ['label' => '12V', 'color' => '#E31B23']], 'Wiper Blades' => ['wiper'], 'Bulbs' => ['bulb'], 'Belts' => ['belt'],
            'Coolant' => ['coolant', ['color' => '#EC4899', 'label' => 'COOLANT']], 'Oil Filter' => ['oil_filter'], 'Air Filter' => ['air_filter'],
            'Cabin Filter' => ['cabin_filter'], 'Fuel Filter' => ['fuel_filter', ['color' => '#1F2937']], 'Floor Mats' => ['mats'],
            'Seat Covers' => ['seat_cover'], 'Phone Holders' => ['phone_holder'], 'Tools' => ['jack'], 'Electronics' => ['dashcam'],
            'Car Parts' => ['brake_disc'], 'Tyres' => ['tyre'], 'Lubricants' => ['oil', ['label' => '5W-30']], 'Filters' => ['air_filter'],
            'Car Accessories' => ['seat_cover'],
        ];
        if ($sub && isset($map[$sub])) {
            return $map[$sub] + [1 => []];
        }
        if (str_contains((string) $sub, 'terrain') || in_array($sub, ['SUV', 'Passenger', 'Light truck'], true)) {
            return ['tyre', ['tread' => $sub === 'All-terrain' ? 'at' : 'road']];
        }
        if ($category === 'Lubricants') {
            // the grade and pack size written in the name ("Engine Oil 5W-40 5L") go on the drawing
            $grade = preg_match('/\b(0|5|10|15|20)\s*W\s*-?\s*(\d{2})\b/i', (string) $name, $g) ? $g[1].'W-'.$g[2] : null;
            $size = preg_match('/\b(\d+(?:[.,]\d+)?)\s*(L|LTR|LITRES?|LITERS?)\b/i', (string) $name, $l) ? str_replace(',', '.', $l[1]).'L' : '4L';
            $label = $grade ?? ($sub === 'Diesel' ? '15W-40' : ($sub === 'Mineral' ? '20W-50' : (preg_match('/\b(ATF|CVT|T-?IV|DEXRON)\b/i', (string) $name, $t) ? strtoupper($t[1]) : '5W-30')));

            return ['oil', ['label' => $label, 'size' => $size, 'color' => $sub === 'Diesel' ? '#0F766E' : '#E31B23']];
        }

        if (isset($map[$category])) {
            return $map[$category] + [1 => []];
        }
        // unknown category: guess from the words used
        $text = strtolower($category.' '.$sub.' '.$name);
        foreach ([
            'shock' => ['shock'], 'suspension' => ['shock'], 'brake' => ['brake_pads'], 'tyre' => ['tyre'], 'tire' => ['tyre'],
            'wheel' => ['tyre'], 'oil filter' => ['oil_filter'], 'air filter' => ['air_filter'], 'filter' => ['oil_filter'],
            'oil' => ['oil', ['label' => 'OIL']], 'lubric' => ['oil', ['label' => 'OIL']], 'battery' => ['battery', ['label' => '12V']],
            'plug' => ['spark_plug'], 'wiper' => ['wiper'], 'bulb' => ['bulb'], 'light' => ['bulb'], 'belt' => ['belt'],
            'coolant' => ['coolant', ['label' => 'COOLANT']], 'mat' => ['mats'], 'seat' => ['seat_cover'], 'jack' => ['jack'],
            'electr' => ['dashcam'], 'engine' => ['spark_plug'], 'accessor' => ['phone_holder'],
        ] as $word => $art) {
            if (str_contains($text, $word)) {
                return $art + [1 => []];
            }
        }

        return ['oil_filter'];
    }

    private static function defs(): string
    {
        return '<defs>'
            .'<linearGradient id="metal" x1="0" x2="1"><stop offset="0" stop-color="#8E9399"/><stop offset=".45" stop-color="#E9EBEE"/><stop offset="1" stop-color="#7B8087"/></linearGradient>'
            .'<linearGradient id="shine" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".35"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>'
            .'<radialGradient id="rubber" cx=".45" cy=".4" r=".7"><stop offset="0" stop-color="#3A3D42"/><stop offset="1" stop-color="#121315"/></radialGradient>'
            .'<filter id="sh" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#000" flood-opacity=".18"/></filter>'
            .'</defs>';
    }

    private static function shadow(int $cx = 200, int $cy = 268, int $rx = 120): string
    {
        return "<ellipse cx=\"{$cx}\" cy=\"{$cy}\" rx=\"{$rx}\" ry=\"10\" fill=\"#000\" opacity=\".10\"/>";
    }

    // ---------------------------------------------------------------- tyres & wheels

    private static function tyre(string $label, string $tread): string
    {
        $blocks = '';
        for ($i = 0; $i < 36; $i++) {
            $a = $i * 10;
            $blocks .= "<rect x=\"196\" y=\"22\" width=\"8\" height=\"".($tread === 'at' ? 22 : 14)."\" rx=\"2\" fill=\"#0B0C0D\" transform=\"rotate({$a} 200 150)\"/>";
        }
        $spokes = '';
        for ($i = 0; $i < 5; $i++) {
            $spokes .= '<path d="M200 150 L188 92 Q200 86 212 92 Z" fill="#C9CDD2" stroke="#9AA0A6" stroke-width="1" transform="rotate('.($i * 72).' 200 150)"/>';
        }

        return self::shadow(200, 282, 110)
            ."<g filter=\"url(#sh)\"><circle cx=\"200\" cy=\"150\" r=\"128\" fill=\"url(#rubber)\"/>{$blocks}"
            .'<circle cx="200" cy="150" r="98" fill="#1C1E21"/><circle cx="200" cy="150" r="72" fill="url(#metal)"/>'
            ."<circle cx=\"200\" cy=\"150\" r=\"62\" fill=\"#5B6067\"/>{$spokes}<circle cx=\"200\" cy=\"150\" r=\"14\" fill=\"#E9EBEE\" stroke=\"#8E9399\"/></g>"
            .($label ? "<text x=\"200\" y=\"74\" text-anchor=\"middle\" font-family=\"Arial\" font-weight=\"700\" font-size=\"11\" fill=\"#9CA3AF\" letter-spacing=\"1\">{$label}</text>" : '');
    }

    // ---------------------------------------------------------------- oil & fluids

    private static function oil(string $c, string $label, string $size): string
    {
        return self::shadow(200, 272, 80)
            .'<g filter="url(#sh)">'
            ."<path d=\"M128 70 Q128 52 146 52 L214 52 L214 34 L246 34 L246 52 L262 52 Q274 52 274 66 L274 250 Q274 264 260 264 L142 264 Q128 264 128 250 Z\" fill=\"{$c}\"/>"
            .'<path d="M214 34 h32 v-10 h-32 z" fill="#1F2124"/>'
            .'<path d="M150 52 Q150 40 162 40 L196 40 Q206 40 206 52 L206 84 L192 84 L192 56 L164 56 L164 84 L150 84 Z" fill="#1F2124" opacity=".85"/>'
            .'<rect x="140" y="118" width="122" height="104" rx="6" fill="#fff"/>'
            ."<rect x=\"140\" y=\"118\" width=\"122\" height=\"22\" rx=\"6\" fill=\"#16181B\"/>"
            ."<text x=\"201\" y=\"134\" text-anchor=\"middle\" font-family=\"Arial\" font-weight=\"700\" font-size=\"12\" fill=\"#fff\">MOTOR OIL</text>"
            ."<text x=\"201\" y=\"182\" text-anchor=\"middle\" font-family=\"Arial Narrow, Arial\" font-weight=\"800\" font-size=\"34\" fill=\"#16181B\">{$label}</text>"
            ."<text x=\"201\" y=\"208\" text-anchor=\"middle\" font-family=\"Arial\" font-weight=\"700\" font-size=\"14\" fill=\"{$c}\">{$size}</text>"
            .'<rect x="136" y="60" width="10" height="196" rx="5" fill="url(#shine)"/></g>';
    }

    private static function bottle(string $c, string $label): string
    {
        return self::shadow(200, 272, 70)
            .'<g filter="url(#sh)"><rect x="182" y="30" width="36" height="26" rx="4" fill="#1F2124"/>'
            ."<path d=\"M160 66 Q160 56 172 56 L228 56 Q240 56 240 66 L262 100 L262 252 Q262 264 250 264 L150 264 Q138 264 138 252 L138 100 Z\" fill=\"{$c}\" opacity=\".92\"/>"
            .'<rect x="150" y="128" width="100" height="90" rx="6" fill="#fff"/>'
            ."<text x=\"200\" y=\"168\" text-anchor=\"middle\" font-family=\"Arial Narrow, Arial\" font-weight=\"800\" font-size=\"17\" fill=\"#16181B\">{$label}</text>"
            .'<text x="200" y="194" text-anchor="middle" font-family="Arial" font-weight="700" font-size="12" fill="#6B7280">READY MIXED</text>'
            .'<rect x="146" y="80" width="10" height="170" rx="5" fill="url(#shine)"/></g>';
    }

    // ---------------------------------------------------------------- filters

    private static function oilFilter(string $c): string
    {
        $ribs = '';
        for ($x = 150; $x <= 250; $x += 10) {
            $ribs .= "<rect x=\"{$x}\" y=\"78\" width=\"3\" height=\"30\" fill=\"#000\" opacity=\".12\"/>";
        }

        return self::shadow(200, 270, 90)
            .'<g filter="url(#sh)">'
            ."<path d=\"M130 72 Q130 56 146 56 L254 56 Q270 56 270 72 L270 236 Q270 252 254 252 L146 252 Q130 252 130 236 Z\" fill=\"{$c}\"/>"
            ."{$ribs}<rect x=\"130\" y=\"236\" width=\"140\" height=\"22\" rx=\"6\" fill=\"url(#metal)\"/>"
            .'<rect x="152" y="132" width="96" height="62" rx="4" fill="#fff"/>'
            .'<text x="200" y="160" text-anchor="middle" font-family="Arial" font-weight="800" font-size="15" fill="#16181B">OIL</text>'
            .'<text x="200" y="180" text-anchor="middle" font-family="Arial" font-weight="800" font-size="15" fill="#16181B">FILTER</text>'
            .'<rect x="140" y="60" width="12" height="176" rx="6" fill="url(#shine)"/></g>';
    }

    private static function panelFilter(string $media, string $fold, string $frame): string
    {
        $pleats = '';
        for ($x = 96; $x < 300; $x += 12) {
            $pleats .= "<path d=\"M{$x} 104 l6 -10 l6 10 v104 l-6 10 l-6 -10 z\" fill=\"{$fold}\"/>";
        }

        return self::shadow(200, 262, 140)
            ."<g filter=\"url(#sh)\"><rect x=\"80\" y=\"84\" width=\"240\" height="."\"144\" rx=\"10\" fill=\"{$frame}\"/>"
            ."<rect x=\"92\" y=\"96\" width=\"216\" height=\"120\" rx=\"4\" fill=\"{$media}\"/>{$pleats}</g>";
    }

    private static function fuelFilter(string $c): string
    {
        return self::shadow(200, 268, 70)
            .'<g filter="url(#sh)"><rect x="96" y="140" width="40" height="16" rx="6" fill="#4B5563"/><rect x="264" y="140" width="40" height="16" rx="6" fill="#4B5563"/>'
            ."<rect x=\"132\" y=\"92\" width=\"136\" height=\"112\" rx=\"22\" fill=\"{$c}\"/>"
            .'<rect x="150" y="132" width="100" height="34" rx="4" fill="#fff"/><text x="200" y="155" text-anchor="middle" font-family="Arial" font-weight="800" font-size="14" fill="#16181B">FUEL</text>'
            .'<rect x="140" y="98" width="12" height="100" rx="6" fill="url(#shine)"/></g>';
    }

    // ---------------------------------------------------------------- brakes & suspension

    private static function brakePads(): string
    {
        $pad = fn ($dx, $rot) => "<g transform=\"translate({$dx} 0) rotate({$rot} 200 150)\">"
            .'<path d="M110 120 Q200 70 290 120 L280 176 Q200 140 120 176 Z" fill="url(#metal)" stroke="#6B7280"/>'
            .'<path d="M124 132 Q200 92 276 132 L270 168 Q200 136 130 168 Z" fill="#3A3D42"/>'
            .'<rect x="196" y="104" width="8" height="58" fill="#1C1E21" opacity=".5"/></g>';

        return self::shadow(200, 262, 150).'<g filter="url(#sh)">'.$pad(-40, -8).$pad(40, 8).'</g>';
    }

    private static function brakeDisc(): string
    {
        $holes = '';
        for ($i = 0; $i < 24; $i++) {
            $holes .= '<circle cx="200" cy="58" r="4" fill="#5B6067" transform="rotate('.($i * 15).' 200 150)"/>';
        }
        $bolts = '';
        for ($i = 0; $i < 5; $i++) {
            $bolts .= '<circle cx="200" cy="122" r="6" fill="#2B2D31" transform="rotate('.($i * 72).' 200 150)"/>';
        }

        return self::shadow(200, 280, 110)
            ."<g filter=\"url(#sh)\"><circle cx=\"200\" cy=\"150\" r=\"118\" fill=\"url(#metal)\"/>{$holes}"
            ."<circle cx=\"200\" cy=\"150\" r=\"52\" fill=\"#6B7280\"/><circle cx=\"200\" cy=\"150\" r=\"40\" fill=\"#9AA0A6\"/>{$bolts}"
            .'<circle cx="200" cy="150" r="14" fill="#2B2D31"/></g>';
    }

    private static function shock(string $c): string
    {
        $coil = '';
        for ($y = 70; $y < 196; $y += 14) {
            $coil .= "<ellipse cx=\"200\" cy=\"{$y}\" rx=\"46\" ry=\"8\" fill=\"none\" stroke=\"{$c}\" stroke-width=\"7\"/>";
        }

        return self::shadow(200, 284, 60)
            .'<g filter="url(#sh)"><rect x="192" y="18" width="16" height="70" fill="url(#metal)"/>'
            .'<rect x="168" y="40" width="64" height="12" rx="4" fill="#2B2D31"/>'
            .'<rect x="178" y="90" width="44" height="170" rx="8" fill="#2B2D31"/>'
            .'<circle cx="200" cy="266" r="14" fill="none" stroke="#2B2D31" stroke-width="8"/>'
            ."{$coil}</g>";
    }

    // ---------------------------------------------------------------- engine & electrical

    private static function sparkPlug(): string
    {
        $plug = fn ($x, $rot) => "<g transform=\"rotate({$rot} {$x} 150)\">"
            ."<rect x=\"".($x - 9)."\" y=\"34\" width=\"18\" height=\"30\" rx=\"4\" fill=\"url(#metal)\"/>"
            ."<path d=\"M".($x - 18)." 64 h36 l-4 70 h-28 z\" fill=\"#F5F5F2\" stroke=\"#D1D5DB\"/>"
            ."<rect x=\"".($x - 22)."\" y=\"134\" width=\"44\" height=\"26\" rx=\"3\" fill=\"#5B6067\"/>"
            ."<rect x=\"".($x - 14)."\" y=\"160\" width=\"28\" height=\"64\" fill=\"url(#metal)\"/>"
            ."<rect x=\"".($x - 2)."\" y=\"224\" width=\"4\" height=\"14\" fill=\"#8E9399\"/></g>";

        return self::shadow(200, 270, 110).'<g filter="url(#sh)">'.$plug(150, -10).$plug(200, 0).$plug(250, 10).'</g>';
    }

    private static function battery(string $c, string $label): string
    {
        return self::shadow(200, 262, 140)
            .'<g filter="url(#sh)"><rect x="74" y="84" width="252" height="164" rx="10" fill="#1F2124"/>'
            ."<rect x=\"74\" y=\"84\" width=\"252\" height=\"34\" rx=\"10\" fill=\"#2B2D31\"/>"
            .'<rect x="110" y="66" width="30" height="22" rx="3" fill="#E31B23"/><rect x="260" y="66" width="30" height="22" rx="3" fill="#4B5563"/>'
            .'<text x="125" y="82" text-anchor="middle" font-family="Arial" font-weight="800" font-size="16" fill="#fff">+</text>'
            ."<rect x=\"98\" y=\"140\" width=\"204\" height=\"80\" rx=\"6\" fill=\"{$c}\"/>"
            ."<text x=\"200\" y=\"176\" text-anchor=\"middle\" font-family=\"Arial Narrow, Arial\" font-weight=\"800\" font-size=\"30\" fill=\"#fff\">{$label}</text>"
            .'<text x="200" y="204" text-anchor="middle" font-family="Arial" font-weight="700" font-size="12" fill="#fff" opacity=".85">12V CAR BATTERY</text></g>';
    }

    private static function wiper(): string
    {
        return self::shadow(200, 230, 160)
            .'<g filter="url(#sh)" transform="rotate(-12 200 150)">'
            .'<path d="M40 160 Q200 130 360 160 L360 172 Q200 144 40 172 Z" fill="#1F2124"/>'
            .'<path d="M60 170 Q200 146 340 170" stroke="#0B0C0D" stroke-width="5" fill="none"/>'
            .'<rect x="178" y="128" width="44" height="22" rx="4" fill="#4B5563"/><rect x="190" y="110" width="20" height="22" rx="3" fill="#2B2D31"/></g>';
    }

    private static function bulb(string $label): string
    {
        return self::shadow(200, 272, 70)
            .'<g filter="url(#sh)"><ellipse cx="200" cy="100" rx="42" ry="58" fill="#EEF2F6" stroke="#CBD5E1" stroke-width="2"/>'
            .'<path d="M186 96 q14 -18 28 0" stroke="#F59E0B" stroke-width="4" fill="none"/>'
            .'<ellipse cx="186" cy="80" rx="8" ry="16" fill="#fff" opacity=".9"/>'
            .'<rect x="176" y="150" width="48" height="26" fill="url(#metal)"/><rect x="160" y="176" width="80" height="12" rx="3" fill="#5B6067"/>'
            .'<rect x="182" y="188" width="36" height="46" fill="#2B2D31"/><rect x="186" y="234" width="6" height="22" fill="#8E9399"/><rect x="208" y="234" width="6" height="22" fill="#8E9399"/></g>'
            .($label ? "<text x=\"300\" y=\"120\" text-anchor=\"middle\" font-family=\"Arial Narrow, Arial\" font-weight=\"800\" font-size=\"34\" fill=\"#CBD5E1\">{$label}</text>" : '');
    }

    private static function belt(): string
    {
        return self::shadow(200, 262, 130)
            .'<g filter="url(#sh)"><path d="M90 150 Q90 70 170 70 L260 92 Q320 108 320 150 Q320 220 240 226 L150 230 Q90 232 90 150 Z" fill="none" stroke="#1F2124" stroke-width="16"/>'
            .'<path d="M90 150 Q90 70 170 70 L260 92 Q320 108 320 150 Q320 220 240 226 L150 230 Q90 232 90 150 Z" fill="none" stroke="#3A3D42" stroke-width="3" stroke-dasharray="6 6"/></g>';
    }

    // ---------------------------------------------------------------- accessories

    private static function mats(): string
    {
        $mat = fn ($x, $y, $w, $h) => "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"14\" fill=\"#2B2D31\"/>"
            ."<rect x=\"".($x + 10)."\" y=\"".($y + 10)."\" width=\"".($w - 20)."\" height=\"".($h - 20)."\" rx=\"8\" fill=\"none\" stroke=\"#45484E\" stroke-width=\"3\" stroke-dasharray=\"10 6\"/>";

        return self::shadow(200, 278, 160).'<g filter="url(#sh)">'
            .$mat(70, 40, 120, 130).$mat(210, 40, 120, 130).$mat(80, 186, 104, 80).$mat(216, 186, 104, 80).'</g>';
    }

    private static function seat(string $c): string
    {
        return self::shadow(200, 282, 100)
            .'<g filter="url(#sh)"><rect x="168" y="20" width="64" height="44" rx="16" fill="#1F2124"/><rect x="184" y="62" width="8" height="14" fill="#5B6067"/><rect x="208" y="62" width="8" height="14" fill="#5B6067"/>'
            .'<path d="M140 80 Q140 72 150 72 L250 72 Q260 72 260 80 L270 196 L130 196 Z" fill="#1F2124"/>'
            ."<path d=\"M160 86 h80 l6 102 h-92 z\" fill=\"{$c}\" opacity=\".9\"/>"
            .'<path d="M120 196 Q120 186 132 186 L268 186 Q280 186 280 196 L286 250 Q286 262 274 262 L126 262 Q114 262 114 250 Z" fill="#2B2D31"/>'
            ."<path d=\"M150 198 h100 l4 50 h-108 z\" fill=\"{$c}\" opacity=\".9\"/></g>";
    }

    private static function phoneHolder(): string
    {
        return self::shadow(200, 272, 90)
            .'<g filter="url(#sh)"><ellipse cx="200" cy="246" rx="70" ry="18" fill="#2B2D31"/><rect x="192" y="160" width="16" height="86" rx="6" fill="#3A3D42"/>'
            .'<rect x="146" y="40" width="108" height="136" rx="16" fill="#1F2124"/><rect x="156" y="52" width="88" height="112" rx="8" fill="#334155"/>'
            .'<rect x="130" y="96" width="18" height="40" rx="6" fill="#4B5563"/><rect x="252" y="96" width="18" height="40" rx="6" fill="#4B5563"/>'
            .'<rect x="162" y="58" width="76" height="20" rx="4" fill="#E31B23" opacity=".85"/></g>';
    }

    private static function jack(string $c): string
    {
        return self::shadow(200, 270, 160)
            .'<g filter="url(#sh)"><rect x="70" y="216" width="230" height="30" rx="8" fill="#1F2124"/>'
            .'<circle cx="96" cy="250" r="12" fill="#3A3D42"/><circle cx="276" cy="250" r="12" fill="#3A3D42"/>'
            ."<path d=\"M110 216 L230 116 L250 132 L150 222 Z\" fill=\"{$c}\"/>"
            .'<rect x="222" y="96" width="44" height="22" rx="4" fill="#2B2D31"/><rect x="250" y="150" width="40" height="66" rx="8" fill="#4B5563"/>'
            .'<rect x="280" y="140" width="96" height="10" rx="5" fill="#8E9399" transform="rotate(-20 280 145)"/></g>';
    }

    private static function dashcam(): string
    {
        return self::shadow(200, 262, 110)
            .'<g filter="url(#sh)"><rect x="180" y="44" width="40" height="40" rx="6" fill="#3A3D42"/>'
            .'<rect x="96" y="84" width="208" height="128" rx="22" fill="#1F2124"/>'
            .'<circle cx="250" cy="148" r="40" fill="#0B0C0D"/><circle cx="250" cy="148" r="28" fill="#1E3A5F"/><circle cx="240" cy="138" r="8" fill="#fff" opacity=".5"/>'
            .'<rect x="116" y="110" width="88" height="60" rx="6" fill="#334155"/><circle cx="130" cy="190" r="5" fill="#E31B23"/></g>';
    }

    private static function jumpStarter(string $c, string $label): string
    {
        return self::shadow(200, 268, 120)
            .'<g filter="url(#sh)"><rect x="96" y="70" width="208" height="180" rx="24" fill="#1F2124"/>'
            ."<rect x=\"96\" y=\"70\" width=\"208\" height=\"52\" rx=\"24\" fill=\"{$c}\"/>"
            ."<text x=\"200\" y=\"104\" text-anchor=\"middle\" font-family=\"Arial Narrow, Arial\" font-weight=\"800\" font-size=\"22\" fill=\"#fff\">{$label}</text>"
            .'<rect x="124" y="144" width="152" height="54" rx="8" fill="#0B0C0D"/><text x="200" y="180" text-anchor="middle" font-family="Arial" font-weight="700" font-size="22" fill="#22C55E">88%</text>'
            .'<circle cx="150" cy="224" r="9" fill="#E31B23"/><circle cx="250" cy="224" r="9" fill="#4B5563"/></g>'
            .'<path d="M300 120 q60 10 60 70" stroke="#E31B23" stroke-width="6" fill="none"/><path d="M300 140 q40 20 40 80" stroke="#1F2124" stroke-width="6" fill="none"/>';
    }

    private static function paint(string $c): string
    {
        return self::shadow(200, 270, 120)
            .'<g filter="url(#sh)"><path d="M120 118 L230 118 L248 250 L102 250 Z" fill="#3A3D42"/>'
            ."<path d=\"M126 128 L224 128 L238 236 L112 236 Z\" fill=\"{$c}\"/>"
            .'<rect x="200" y="58" width="22" height="64" rx="4" fill="#2B2D31"/><rect x="214" y="64" width="80" height="22" rx="8" fill="#4B5563"/>'
            .'<rect x="288" y="66" width="24" height="18" rx="4" fill="#8E9399"/></g>'
            ."<g fill=\"{$c}\" opacity=\".55\"><circle cx=\"330\" cy=\"70\" r=\"5\"/><circle cx="."\"346\" cy=\"62\" r=\"4\"/><circle cx=\"350\" cy=\"80\" r=\"6\"/><circle cx=\"366\" cy=\"70\" r=\"3\"/><circle cx=\"338\" cy=\"90\" r=\"3\"/></g>";
    }
}
