<?php

namespace App\Livewire\Website;

use App\Livewire\Concerns\ManagesRecords;
use App\Models\Part;
use App\Models\SiteBanner;
use App\Support\Audit;
use App\Support\ImageData;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The scrolling ads on the website home page. */
#[Title('Website Banners')]
class Banners extends Component
{
    use ManagesRecords;

    protected array $manageRoles = ['admin', 'manager'];

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];
    public ?string $photoData = null;
    public bool $removePhoto = false;

    private static function blank(): array
    {
        return ['layout' => 'text', 'kicker' => '', 'title' => '', 'text' => '', 'button_label' => '', 'link_url' => '', 'tone' => 'ink',
            'kicker_sw' => '', 'title_sw' => '', 'text_sw' => '', 'button_label_sw' => '',
            'part_id' => '', 'is_active' => true, 'starts_on' => '', 'ends_on' => ''];
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->editingId = null;
        $this->form = self::blank();
        $this->reset(['photoData', 'removePhoto']);
        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('banner-form-opened');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $b = SiteBanner::findOrFail($id);
        $this->editingId = $b->id;
        $this->form = array_merge(self::blank(), array_map(fn ($v) => $v ?? '', $b->only(['layout', 'kicker', 'title', 'text', 'button_label', 'kicker_sw', 'title_sw', 'text_sw', 'button_label_sw', 'link_url', 'tone', 'part_id'])), [
            'is_active' => $b->is_active, 'starts_on' => $b->starts_on?->toDateString() ?? '', 'ends_on' => $b->ends_on?->toDateString() ?? '',
        ]);
        $this->reset(['photoData', 'removePhoto']);
        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('banner-form-opened');
    }

    /** Picking a part fills empty boxes with its details. */
    public function updatedFormPartId($id): void
    {
        if ($id && ($p = Part::find($id))) {
            $this->form['title'] = $this->form['title'] ?: ($p->hasDiscount() ? "Save {$p->discountShown()}% on {$p->name}" : $p->name);
            $this->form['kicker'] = $this->form['kicker'] ?: ($p->hasDiscount() ? 'Deal of the week' : ($p->category ?: ''));
            $this->form['button_label'] = $this->form['button_label'] ?: 'Shop the deal';
            $this->form['tone'] = $p->hasDiscount() ? 'red' : $this->form['tone'];
        }
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'form.layout'       => 'required|in:'.implode(',', array_keys(SiteBanner::LAYOUTS)),
            'form.kicker'       => 'nullable|string|max:60',
            'form.title'        => 'required_if:form.layout,text|nullable|string|max:120',
            'form.text'         => 'nullable|string|max:300',
            'form.button_label' => 'nullable|string|max:40',
            'form.kicker_sw'       => 'nullable|string|max:80',
            'form.title_sw'        => 'nullable|string|max:160',
            'form.text_sw'         => 'nullable|string|max:400',
            'form.button_label_sw' => 'nullable|string|max:60',
            'form.link_url'     => ['nullable', 'string', 'max:300', 'regex:#^(https?://|/)#'],
            'form.tone'         => 'required|in:'.implode(',', array_keys(SiteBanner::TONES)),
            'form.part_id'      => 'nullable|exists:parts,id',
            'form.is_active'    => 'boolean',
            'form.starts_on'    => 'nullable|date',
            'form.ends_on'      => 'nullable|date|after_or_equal:form.starts_on',
        ], ['form.title.required_if' => 'Write the title shown on the banner.', 'form.link_url.regex' => 'Start the link with https:// or / (a page on this website).', 'form.ends_on.after_or_equal' => 'The end date must be after the start date.'])['form'];
        foreach (['kicker', 'text', 'button_label', 'kicker_sw', 'title_sw', 'text_sw', 'button_label_sw', 'link_url', 'part_id', 'starts_on', 'ends_on'] as $k) {
            $data[$k] = $data[$k] === '' ? null : $data[$k];
        }

        // a picture-only banner is nothing without its picture; its name is optional
        if ($data['layout'] === 'image') {
            $data['title'] = trim((string) ($data['title'] ?? '')) ?: 'Banner '.now()->format('d M Y');
            $hasPicture = $this->photoData || ($this->editingId && ! $this->removePhoto && SiteBanner::whereKey($this->editingId)->value('image_path'));
            if (! $hasPicture) {
                $this->addError('photoData', 'Upload your banner design.');

                return;
            }
        }

        $photo = null;
        if ($this->photoData) {
            try {
                $photo = ImageData::store($this->photoData, 'banners');
            } catch (\InvalidArgumentException $e) {
                $this->addError('photoData', $e->getMessage());

                return;
            }
        }

        if ($this->editingId) {
            $b = SiteBanner::findOrFail($this->editingId);
            $b->update($data);
        } else {
            $b = SiteBanner::create($data + ['sort_order' => (int) SiteBanner::max('sort_order') + 1]);
        }
        if ($photo || $this->removePhoto) {
            if ($b->image_path) {
                Storage::disk('public')->delete($b->image_path);
            }
            $b->update(['image_path' => $photo]);
        }
        Audit::log('Website', $this->editingId ? 'BANNER_UPDATE' : 'BANNER_CREATE', $b->title);
        $this->toast($this->editingId ? 'Banner updated.' : 'Banner added to the website.');
        $this->showForm = false;
        $this->reset(['photoData', 'removePhoto']);
    }

    public function move(int $id, int $dir): void
    {
        $this->authorizeManage();
        $list = SiteBanner::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $list->search(fn ($b) => $b->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $list->count()) {
            return;
        }
        $order = $list->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $bid) {
            SiteBanner::whereKey($bid)->update(['sort_order' => $pos + 1]);
        }
    }

    public function toggle(int $id): void
    {
        $this->authorizeManage();
        $b = SiteBanner::findOrFail($id);
        $b->update(['is_active' => ! $b->is_active]);
        $this->toast($b->is_active ? 'Banner switched on.' : 'Banner switched off.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $b = SiteBanner::findOrFail($id);
        if ($b->image_path) {
            Storage::disk('public')->delete($b->image_path);
        }
        $b->delete();
        Audit::log('Website', 'BANNER_DELETE', $b->title);
        $this->toast('Banner deleted.');
    }

    public function render()
    {
        return view('livewire.website.banners', [
            'banners' => SiteBanner::with('part')->orderBy('sort_order')->orderBy('id')->get(),
            'parts'   => Part::online()->orderBy('name')->get(['id', 'name', 'sku', 'discount_percent', 'selling_price', 'min_selling_price']),
            'links'   => collect(Part::shopDepartments())->mapWithKeys(fn ($d) => [route('shop.catalog', ['category' => $d], false) => $d])
                ->merge([route('shop.catalog', ['deals' => 1], false) => 'Deals', route('shop.garage', [], false) => 'Garage & paint']),
        ]);
    }
}
