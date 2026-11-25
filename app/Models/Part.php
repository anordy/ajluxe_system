<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One parts inventory for the whole business (garage + parts shop).
 * stock_qty is only changed through StockMovement::record() so every change is traceable.
 */
class Part extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'oem_no', 'brand', 'category', 'unit', 'vehicle_make', 'vehicle_model',
        'rack_location', 'purchase_price', 'selling_price', 'min_selling_price', 'tax_rate', 'reorder_level', 'image_path', 'is_active',
        'show_online', 'sourced', 'source_supplier_id', 'discount_percent', 'ribbon', 'web_description', 'subcategory', 'specs', 'variant_of',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'purchase_price' => 'decimal:2',
            'selling_price'  => 'decimal:2',
            'min_selling_price' => 'decimal:2',
            'discount_percent'  => 'decimal:2',
            'show_online'       => 'boolean',
            'sourced'           => 'boolean',
            'specs'             => 'array',
            'tax_rate'       => 'decimal:2',
            'stock_qty'      => 'decimal:2',
            'reorder_level'  => 'decimal:2',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLow(): bool
    {
        // bought-when-sold parts are never "low": we don't keep them on purpose
        return ! $this->sourced && (float) $this->reorder_level > 0 && (float) $this->stock_qty <= (float) $this->reorder_level;
    }

    public function stockValue(): float
    {
        return (float) $this->stock_qty * (float) $this->purchase_price;
    }

    public function margin(): ?float
    {
        return (float) $this->selling_price > 0
            ? round(((float) $this->selling_price - (float) $this->purchase_price) / (float) $this->selling_price * 100, 1)
            : null;
    }

    /** Lowest price staff may sell at (admins may go below). */
    public function minPrice(): float
    {
        return (float) $this->min_selling_price;
    }

    /** Throws if $rate is below the minimum selling price and the user is not an admin. */
    public function assertPriceAllowed(float $rate): void
    {
        if ($this->minPrice() > 0 && $rate + 0.001 < $this->minPrice() && ! auth()->user()?->hasRole('admin')) {
            throw new \App\Exceptions\BusinessRuleException("The lowest price for {$this->name} is ".money($this->minPrice()).'. Ask an admin to sell below it.');
        }
    }

    // ---------------------------------------------------------------- website

    /** Parts customers can see on the website. */
    /** Ribbons that can sit on a product picture on the website. */
    public const RIBBONS = ['sale' => 'Sale', 'new' => 'New', 'best_offer' => 'Best offer', 'sold_out' => 'Sold out'];

    /**
     * The ribbon to show on the website: the one picked on the part, or automatically
     * Sold out (nothing to sell) → New (first 12 hours) → Sale (has a discount). Null = no ribbon.
     * Returns ['key' => ..., 'label' => ...]; the discount is added to the label when there is one.
     */
    public function ribbonBadge(): ?array
    {
        $key = $this->attributes['ribbon'] ?? null;
        if ($key === 'none') {
            return null;
        }
        if (! isset(self::RIBBONS[$key])) {
            $key = match (true) {
                $this->shopStock() <= 0 => 'sold_out',
                $this->isNew()          => 'new',
                $this->hasDiscount()    => 'sale',
                default                 => null,
            };
        }
        if (! $key) {
            return null;
        }
        $label = __(self::RIBBONS[$key]);
        if ($key !== 'sold_out' && $this->hasDiscount()) {
            $label .= ' −'.$this->discountShown().'%';
        }

        return ['key' => $key, 'label' => $label];
    }

    /** Hours a newly added part wears the "New" badge on the website. */
    public const NEW_HOURS = 12;

    public function isNew(): bool
    {
        return $this->created_at !== null && $this->created_at->gt(now()->subHours(self::NEW_HOURS));
    }

    /** How many a bought-from-supplier part shows as available on the website when we hold none. */
    public const SOURCED_QTY = 10;

    /** Ready to sell online: on our shelf, or bought from its supplier when sold. */
    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('stock_qty', '>', 0)->orWhere('sourced', true));
    }

    /** What the website may sell: our stock, or (bought-from-supplier parts) at least SOURCED_QTY. Customers never see the difference. */
    public function shopStock(): int
    {
        $own = (int) floor(max(0, (float) $this->stock_qty));

        return $this->sourced ? max($own, self::SOURCED_QTY) : $own;
    }

    public function sourceSupplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'source_supplier_id');
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('show_online', true)->where('selling_price', '>', 0);
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_percent > 0 && $this->salePrice() < (float) $this->selling_price;
    }

    /** Website price after the discount — never below the minimum selling price. */
    public function salePrice(): float
    {
        $price = round((float) $this->selling_price * (1 - (float) $this->discount_percent / 100));

        return max($price, $this->minPrice());
    }

    /** Real discount shown to customers (may be less than set if the minimum price stops it). */
    public function discountShown(): int
    {
        $sp = (float) $this->selling_price;

        return $sp > 0 ? (int) round((1 - $this->salePrice() / $sp) * 100) : 0;
    }

    /** Departments shown in the website menu (in this order); other categories still work. */
    public const DEPARTMENTS = ['Car Parts', 'Tyres', 'Lubricants', 'Filters', 'Car Accessories'];

    /** The oil department (engine oil, ATF, CVT fluid…). Was called "Engine Oil". */
    public const OIL = 'Lubricants';

    /** The spec used for quick-filter chips in a department (e.g. oil viscosity, tyre size). */
    public const CHIP_SPECS = ['Lubricants' => 'Viscosity', 'Tyres' => 'Size'];

    /** Specification filters in the website sidebar, per department. */
    public const FACETS = [
        'Lubricants' => ['Capacity', 'Oil type', 'API', 'ACEA', 'Manufacturer approvals', 'Engine'],
        'Tyres'      => ['Season', 'Load index', 'Speed rating', 'Rim diameter'],
        'Filters'    => ['Filter type'],
        'Car Parts'  => ['Brake system', 'Shock absorber type', 'Battery capacity', 'Bulb type'],
    ];

    /** Specs that can hold several values separated by commas (each one filters on its own). */
    public const MULTI_SPECS = ['Manufacturer approvals', 'ACEA'];

    /** Tyre tabs: label and the tyre types (subcategory) in each. */
    public const TYRE_GROUPS = [
        'car' => ['Passenger car', ['Passenger']],
        'suv' => ['4x4 / SUV', ['SUV', 'All-terrain', 'Mud-terrain']],
        'van' => ['Light truck / van', ['Light truck', 'Commercial']],
    ];

    // ---------------------------------------------------------------- cars it fits

    /** Car models (and optionally engines) this part fits. */
    public function fitments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartVehicle::class)->orderBy('id');
    }

    /** Parts that fit a make (and model): the mapped cars, or the "Fits" text written on the part. */
    /**
     * Parts for a car. With an engine: parts linked to that engine or to all engines of the model,
     * plus parts that only name the make/model as text (they say nothing about engines).
     */
    public function scopeFits(Builder $q, ?string $make, ?string $model = null, ?int $engine = null): Builder
    {
        if (! $make) {
            return $q;
        }

        return $q->where(function ($q) use ($make, $model, $engine) {
            $q->where(fn ($q) => $q->where('vehicle_make', $make)->when($model, fn ($q) => $q->where('vehicle_model', 'like', '%'.$model.'%')))
              ->orWhereHas('fitments', fn ($f) => $f
                  ->whereHas('carModel', fn ($c) => $c->where('make', $make)->when($model, fn ($c) => $c->where('model', 'like', '%'.$model.'%')))
                  ->when($engine, fn ($f) => $f->where(fn ($e) => $e->whereNull('car_engine_id')->orWhere('car_engine_id', $engine))));
        });
    }

    /** "Toyota Hilux, Toyota Fortuner" for the website (make and model only). */
    public function fitsText(int $max = 3): ?string
    {
        $mapped = $this->relationLoaded('fitments') ? $this->fitments : $this->fitments()->with('carModel')->get();
        $names = $mapped->map(fn ($f) => $f->carModel?->name())->filter()->unique()->values();
        if ($names->isEmpty()) {
            return trim($this->vehicle_make.' '.$this->vehicle_model) ?: null;
        }
        $more = $names->count() - $max;

        return $names->take($max)->implode(', ').($more > 0 ? ' '.__('and :n more', ['n' => $more]) : '');
    }

    // ---------------------------------------------------------------- sizes of the same product

    /** The main part when this is another size of it (1L of a 4L oil). */
    public function main(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Part::class, 'variant_of');
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Part::class, 'variant_of');
    }

    /** Parts that stand for their product in lists: main parts, and sizes whose main part is not online. */
    public function scopeListed(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('variant_of')->orWhereNotIn('variant_of', static::online()->select('id')));
    }

    /** Every size of this product sold online (this one included), smallest first. */
    public function family(): \Illuminate\Support\Collection
    {
        $main = $this->variant_of ?: $this->id;

        return static::online()->where(fn ($q) => $q->whereKey($main)->orWhere('variant_of', $main))->get()
            ->sortBy(fn (Part $p) => [$p->litres() ?? 999, (float) $p->salePrice()])->values();
    }

    /** Families for a list of parts at once: [main id => collection of sizes]. Only families with more than one size. */
    public static function families(iterable $parts): array
    {
        $mains = collect($parts)->map(fn ($p) => $p->variant_of ?: $p->id)->unique()->values();
        if ($mains->isEmpty()) {
            return [];
        }

        return static::online()->where(fn ($q) => $q->whereIn('id', $mains)->orWhereIn('variant_of', $mains))->get()
            ->groupBy(fn ($p) => $p->variant_of ?: $p->id)
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($g) => $g->sortBy(fn (Part $p) => [$p->litres() ?? 999, (float) $p->salePrice()])->values())
            ->all();
    }

    /** Litres in the pack, from the "Capacity" spec ("4 L", "1L", "500 ml"). */
    public function litres(): ?float
    {
        $c = strtolower((string) ($this->specList()['Capacity'] ?? ''));
        if (preg_match('/([\d.,]+)\s*ml\b/', $c, $m)) {
            return (float) str_replace(',', '.', $m[1]) / 1000;
        }
        if (preg_match('/([\d.,]+)\s*(l|litre|liter|ltr)\b/', $c, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Short pack label for size buttons: "4L", "500ml", or the unit. */
    public function packLabel(): string
    {
        $l = $this->litres();
        if ($l !== null) {
            return $l < 1 ? round($l * 1000).'ml' : rtrim(rtrim(number_format($l, 2, '.', ''), '0'), '.').'L';
        }

        return (string) ($this->specList()['Capacity'] ?? $this->unit ?? 'Pcs');
    }

    public function pricePerLitre(): ?float
    {
        $l = $this->litres();

        return $l ? round((float) $this->salePrice() / $l) : null;
    }

    /** "per piece", "per set"… for the price line. */
    public function unitLabel(): string
    {
        $u = strtolower(trim((string) ($this->unit ?: 'pcs')));

        return match ($u) {
            'pcs', 'pc', 'piece', 'pieces', 'each', 'nos', 'no' => 'piece',
            'l', 'ltr', 'litre', 'liter'                       => 'litre',
            'pr', 'pair'                                       => 'pair',
            'set', 'sets', 'kit'                               => $u === 'kit' ? 'kit' : 'set',
            'm', 'mtr', 'metre', 'meter'                       => 'metre',
            default                                            => $u,
        };
    }

    /** The picture, or a drawing of the part when there is none. */
    public function pictureUrl(): string
    {
        return $this->imageUrl() ?: $this->drawingUrl();
    }

    /** Small copy of the photo for cards and lists (falls back to the drawing). */
    public function thumbUrl(): string
    {
        if ($this->image_path && ! str_ends_with(strtolower($this->image_path), '.svg')) {
            return \App\Support\ImageData::thumbUrl($this->image_path);
        }

        return $this->pictureUrl();
    }

    public function drawingUrl(): string
    {
        return \App\Support\ProductArt::dataUri(...\App\Support\ProductArt::forCategory($this->category, $this->subcategory, $this->name));
    }

    // ---------------------------------------------------------------- tyres

    /** "205/55 R16 91V" → ['w' => '205', 'h' => '55', 'r' => '16', 'c' => ''] */
    public static function tyreSize(?string $size): ?array
    {
        if (! preg_match('#(\d{3})\s*/\s*(\d{2})\s*Z?R\s*(\d{2})\s*(C)?#i', (string) $size, $m)) {
            return null;
        }

        return ['w' => $m[1], 'h' => $m[2], 'r' => $m[3], 'c' => strtoupper($m[4] ?? '')];
    }

    /** "Summer tyres 205/55 R16 91V" */
    public function tyreLine(): ?string
    {
        $s = $this->specList();
        if (empty($s['Size'])) {
            return null;
        }
        $li = preg_match('#^[\d/]+#', (string) ($s['Load index'] ?? ''), $m) ? $m[0] : '';
        $sr = preg_match('#^[A-Z]{1,2}\b#', (string) ($s['Speed rating'] ?? ''), $m) ? $m[0] : '';
        $season = match (strtolower((string) ($s['Season'] ?? ''))) {
            'summer' => 'Summer tyres', 'winter' => 'Winter tyres', 'all season', 'all-season' => 'All-season tyres', default => 'Tyres',
        };

        return trim($season.' '.$s['Size'].' '.$li.$sr);
    }

    /** A spec value written for customers: "91 (615 kg)" → "91 = up to 615 kg". */
    public static function specDisplay(string $key, string $value): string
    {
        if ($key === 'Load index' && preg_match('#^([\d/]+)\s*\((\d+)\s*kg\)$#', $value, $m)) {
            return "{$m[1]} = up to {$m[2]} kg";
        }
        if ($key === 'Speed rating' && preg_match('#^([A-Z]{1,2})\s*\((\d+)\s*km/h\)$#', $value, $m)) {
            return "{$m[1]} = up to {$m[2]} km/h";
        }

        return $value;
    }

    /** Specs as an ordered [label => value] list, empty values removed. */
    /**
     * Departments shown on the website: the "Part Categories" list (Admin › Lists, in its order)
     * that have parts online, then any other category with parts online.
     */
    public static function shopDepartments(): array
    {
        return once(function () {
            $live = static::online()->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->all();
            $listed = array_values(array_filter(list_options('part_category'), fn ($c) => in_array($c, $live, true)));
            $rest = array_values(array_diff($live, $listed));
            sort($rest);

            return array_merge($listed, $rest);
        });
    }

    public function specList(): array
    {
        return array_filter((array) ($this->specs ?? []), fn ($v) => $v !== null && $v !== '');
    }

    /** "Size: 265/65 R17\nLoad: 112T" ⇄ ['Size' => '265/65 R17', ...] for the staff form. */
    public static function parseSpecs(?string $text): ?array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            if ($k !== '' && $v !== '') {
                $out[mb_substr($k, 0, 40)] = mb_substr($v, 0, 120);
            }
        }

        return $out ?: null;
    }

    public function specsText(): string
    {
        return collect($this->specList())->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
    }

    public function slug(): string
    {
        return \Illuminate\Support\Str::slug($this->name.' '.$this->brand) ?: 'part';
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('sourced', false)->where('reorder_level', '>', 0)->whereColumn('stock_qty', '<=', 'reorder_level');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->orWhere('oem_no', 'like', "%{$term}%")
            ->orWhere('brand', 'like', "%{$term}%")
            ->orWhere('vehicle_make', 'like', "%{$term}%")
            ->orWhere('vehicle_model', 'like', "%{$term}%")
            ->orWhere('subcategory', 'like', "%{$term}%")
            ->orWhere('category', 'like', "%{$term}%"));
    }

    public function deleteBlocker(): ?string
    {
        if ($this->movements()->where('type', '!=', 'opening')->exists()) {
            return 'This part has stock history (sales, purchases or job cards). Deactivate it instead.';
        }
        foreach (['job_card_items', 'invoice_items', 'purchase_order_items'] as $table) {
            if (DB::table($table)->where('part_id', $this->id)->exists()) {
                return 'This part is used on documents. Deactivate it instead.';
            }
        }

        return null;
    }
}
