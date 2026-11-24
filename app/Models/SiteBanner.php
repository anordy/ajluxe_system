<?php

namespace App\Models;

use App\Support\ProductArt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One scrolling ad on the website home page. */
class SiteBanner extends Model
{
    public const TONES = ['ink' => 'Dark', 'red' => 'Red', 'grey' => 'Light grey'];

    /** text: title, text, button and picture · image: a finished design shown as it is (no text, no button) */
    public const LAYOUTS = ['text' => 'Text and picture', 'image' => 'Picture only (my own design)'];

    protected $fillable = ['layout', 'kicker', 'title', 'text', 'button_label', 'kicker_sw', 'title_sw', 'text_sw', 'button_label_sw', 'link_url', 'image_path', 'tone', 'part_id', 'sort_order', 'is_active', 'starts_on', 'ends_on'];

    protected $casts = ['is_active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'sort_order' => 'integer'];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** Switched on and inside its dates. */
    public function scopeLive(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }
        if ($p = $this->part) {
            return $p->imageUrl() ?: ProductArt::dataUri(...ProductArt::forCategory($p->category, $p->subcategory, $p->name));
        }

        return null;
    }

    /** An uploaded photo fills its half of the banner; drawings (SVG) and part pictures are shown whole. */
    public function isPhoto(): bool
    {
        return $this->image_path && ! str_ends_with(strtolower($this->image_path), '.svg');
    }

    public function isImageOnly(): bool
    {
        return $this->layout === 'image';
    }

    /** Where a picture-only banner goes when clicked; null when it has no link. */
    public function clickUrl(): ?string
    {
        return $this->link_url || $this->part ? $this->url() : null;
    }

    public function url(): string
    {
        if ($this->link_url) {
            return $this->link_url;
        }

        return $this->part ? route('shop.product', [$this->part, $this->part->slug()]) : route('shop.catalog');
    }

    /** kicker / title / text / button_label in the visitor's language (Kiswahili copy when filled in). */
    public function tr(string $field): ?string
    {
        if (app()->getLocale() === 'sw' && filled($this->{$field.'_sw'})) {
            return $this->{$field.'_sw'};
        }

        return $this->{$field};
    }

    /** Line shown under the title; for a linked part on offer, the price is added automatically. */
    public function body(): ?string
    {
        $p = $this->part;
        $text = $this->tr('text');
        if ($p && $p->hasDiscount()) {
            $price = __('Now :price instead of :was.', ['price' => money($p->salePrice()), 'was' => money($p->selling_price)]);

            return trim($price.' '.$text);
        }

        return $text ?: ($p ? __('Now :price.', ['price' => money($p->salePrice())]) : null);
    }

    /** Hidden on the website: switched off, out of its dates, or its part is no longer for sale online. */
    public function isLive(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && (! $this->starts_on || $this->starts_on->lte($today))
            && (! $this->ends_on || $this->ends_on->gte($today))
            && (! $this->part_id || ($this->part && $this->part->show_online && $this->part->is_active))
            && (! $this->isImageOnly() || $this->image_path);
    }
}
