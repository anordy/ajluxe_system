<?php

namespace Tests\Feature;

use App\Livewire\Shop\CarRequestForm;
use App\Livewire\Shop\Home;
use App\Livewire\Website\Banners;
use App\Livewire\Website\CarRequests;
use App\Livewire\Website\Cars;
use App\Livewire\Website\Texts;
use App\Models\CarModel;
use App\Models\CarRequest;
use App\Models\ListOption;
use App\Models\Part;
use App\Models\SiteBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Website content managed from the admin portal, and "Can't find your car?" requests. */
class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    private function staff(string $role = 'admin'): User
    {
        return User::factory()->role($role)->create();
    }

    private function part(array $a = []): Part
    {
        static $n = 0;
        $n++;

        return Part::create($a + ['sku' => 'W-'.$n, 'name' => 'Website Part '.$n, 'unit' => 'Pcs', 'purchase_price' => 1000, 'selling_price' => 2000,
            'is_active' => true, 'show_online' => true, 'category' => 'Filters']);
    }

    public function test_car_request_form_saves_and_staff_add_the_car_to_the_list(): void
    {
        RateLimiter::clear('car-request:127.0.0.1');
        // the home page offers the form instead of WhatsApp / name search
        $this->get('/')->assertOk()->assertSee("Can't find your car?")->assertSee('Send us your car details')
            ->assertDontSee('Search by name or part number')->assertDontSee('Ask us on WhatsApp');

        Livewire::test(CarRequestForm::class)->call('show', 'Toyota', 'Crown')->assertSet('open', true)->assertSet('make', 'Toyota')
            ->set('model', '')->call('send')->assertHasErrors(['model', 'phone'])
            ->set('model', 'Crown Athlete')->set('year', '2013')->set('vin', 'grs204 0012345')->set('parts', 'Front brake pads, oil filter')
            ->set('phone', '0754 123 456')->set('wants_offers', true)
            ->call('send')->assertHasNoErrors()->assertSet('sent', true)->assertSee('Request received');

        $r = CarRequest::firstOrFail();
        $this->assertSame('GRS2040012345', $r->vin);
        $this->assertSame(2013, $r->year);
        $this->assertSame('new', $r->status);
        $this->assertTrue($r->wants_offers);

        // staff see it with a badge, then add the car to the website list
        $this->actingAs($this->staff(), 'web');
        $this->get(route('website.requests'))->assertOk()->assertSee('Toyota Crown Athlete (2013)')->assertSee('Front brake pads');
        Livewire::test(CarRequests::class)->call('open', $r->id)->set('note', 'Quoted pads 95,000')->call('addToList');
        $this->assertSame('added', $r->fresh()->status);
        $this->assertSame('Quoted pads 95,000', $r->fresh()->staff_note);
        $this->assertTrue(CarModel::where('make', 'Toyota')->where('model', 'Crown Athlete')->where('year_from', 2013)->exists());

        // the car finder offers it once a part on the website is linked to it
        $crown = CarModel::where('make', 'Toyota')->where('model', 'Crown Athlete')->first();
        Livewire::test(Home::class)->set('make', 'Toyota')->assertDontSee('Crown Athlete');
        $pads = $this->part(['name' => 'Crown Brake Pads']);
        \App\Models\PartVehicle::create(['part_id' => $pads->id, 'car_model_id' => $crown->id]);
        Livewire::test(Home::class)->set('make', 'Toyota')->assertSee('Crown Athlete');
    }

    public function test_car_request_needs_phone_or_email_and_is_rate_limited(): void
    {
        RateLimiter::clear('car-request:127.0.0.1');
        Livewire::test(CarRequestForm::class)->set('make', 'Nissan')->set('model', 'Juke')->set('email', 'not-an-email')->call('send')->assertHasErrors('email');
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(CarRequestForm::class)->set('make', 'Nissan')->set('model', 'Juke')->set('email', 'a@b.co')->call('send')->assertHasNoErrors();
        }
        Livewire::test(CarRequestForm::class)->set('make', 'Nissan')->set('model', 'Juke')->set('email', 'a@b.co')->call('send')->assertHasErrors('make');
        $this->assertSame(5, CarRequest::count());
    }

    public function test_banners_are_managed_in_admin_and_shown_in_order_within_dates(): void
    {
        $this->actingAs($this->staff('manager'), 'web');
        SiteBanner::query()->delete();
        $deal = $this->part(['name' => 'Cabin Filter Promo', 'discount_percent' => 25]);

        Livewire::test(Banners::class)->call('create')->set('form.part_id', (string) $deal->id)
            ->assertSet('form.title', 'Save 25% on Cabin Filter Promo')->assertSet('form.tone', 'red')
            ->set('form.link_url', 'javascript:alert(1)')->call('save')->assertHasErrors('form.link_url')
            ->set('form.link_url', '')->call('save')->assertHasNoErrors();
        Livewire::test(Banners::class)->call('create')->set('form.title', 'Rainy season wipers')->set('form.link_url', '/shop?category=Filters')->call('save');
        Livewire::test(Banners::class)->call('create')->set('form.title', 'Next month offer')->set('form.starts_on', now()->addMonth()->toDateString())->call('save');

        $home = $this->get('/')->assertOk()->assertSee('Save 25% on Cabin Filter Promo')->assertSee('Now TZS 1,500 instead of TZS 2,000', false)
            ->assertSee('Rainy season wipers')->assertDontSee('Next month offer');
        $this->assertLessThan(strpos($home->getContent(), 'Rainy season wipers'), strpos($home->getContent(), 'Save 25% on Cabin Filter Promo'));

        $wipers = SiteBanner::where('title', 'Rainy season wipers')->first();
        Livewire::test(Banners::class)->call('move', $wipers->id, -1);
        $home = $this->get('/')->getContent();
        $this->assertLessThan(strpos($home, 'Save 25% on Cabin Filter Promo'), strpos($home, 'Rainy season wipers'));

        // a banner for a part taken off the website hides itself
        $deal->update(['show_online' => false]);
        $this->get('/')->assertDontSee('Save 25% on Cabin Filter Promo');

        // picture only: a finished design, no text or button; needs its picture
        \Illuminate\Support\Facades\Storage::fake('public');
        $img = imagecreatetruecolor(160, 90);
        ob_start(); imagejpeg($img); $jpg = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        Livewire::test(Banners::class)->call('create')->set('form.layout', 'image')->set('form.title', 'December tyre sale')
            ->call('save')->assertHasErrors('photoData')
            ->set('photoData', $jpg)->set('form.link_url', '/shop?category=Tyres')->call('save')->assertHasNoErrors();
        $b = SiteBanner::where('title', 'December tyre sale')->first();
        $this->assertTrue($b->isImageOnly());
        $html = $this->get('/')->assertOk()->assertSee('alt="December tyre sale"', false)->assertSee('/shop?category=Tyres', false)->getContent();
        $slide = substr($html, strpos($html, 'alt="December tyre sale"') - 600, 700);
        $this->assertStringNotContainsString('Shop now', $slide);

        // the editor says plainly whether the banner is on the website
        Livewire::test(Banners::class)->call('create')->set('form.title', 'Hidden promo')->set('form.is_active', false)->call('save')
            ->assertDispatched('toast', message: 'Banner saved, but it is switched off, so it is not on the website. Use the ✓ button to switch it on.', type: 'warning');
        Livewire::test(Banners::class)->call('create')->set('form.title', 'Coming soon')->set('form.starts_on', today()->addDays(3)->toDateString())->call('save')
            ->assertDispatched('toast', type: 'success');
        Livewire::test(Banners::class)->call('create')->set('form.title', 'Live promo')->call('save')
            ->assertDispatched('toast', message: 'Banner saved and live on the website.', type: 'success');
        $this->get('/')->assertSee('Live promo')->assertDontSee('Hidden promo')->assertDontSee('Coming soon');

        // a design needs no name, title, text or button at all
        Livewire::test(Banners::class)->call('create')->set('form.layout', 'image')->set('form.title', '')->set('photoData', $jpg)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(1, SiteBanner::where('layout', 'image')->where('title', 'like', 'Banner %')->count());
        // a text banner still needs its title
        Livewire::test(Banners::class)->call('create')->set('form.layout', 'text')->set('form.title', '')->call('save')->assertHasErrors('form.title');

        // footer: payment names only, no account or Lipa Namba numbers
        $footer = $this->get('/')->getContent();
        $footer = substr($footer, strpos($footer, 'Ways to pay'), 900);
        $this->assertStringContainsString('M-Pesa', $footer);
        $this->assertStringContainsString('CRDB Bank', $footer);
        $this->assertStringNotContainsString('10259295578', $footer);
        $this->assertStringNotContainsString('351540391', $footer);

        // website customers and mechanics cannot open the banner screen
        $this->actingAs($this->staff('mechanic'), 'web');
        $this->get(route('website.banners'))->assertForbidden();
    }

    public function test_texts_cars_and_departments_come_from_the_database(): void
    {
        $this->actingAs($this->staff(), 'web');
        Livewire::test(Texts::class)->set('form.web_hero_title', 'Spare parts for every car in Dar')
            ->set('form.web_trust', "Original parts | Straight from the importer\nFree fitting | On orders over 200,000")
            ->set('form.web_topbar', 'Open on Sundays in December')->call('save')->assertHasNoErrors();
        Livewire::test(Texts::class)->set('form.web_hero_title', '')->call('save')->assertHasErrors('form.web_hero_title');

        Livewire::test(Cars::class)->call('create')->set('form.make', 'Toyota')->set('form.model', 'Mark II')->set('form.year_from', '2000')->set('form.year_to', '1999')
            ->call('save')->assertHasErrors('form.year_to')->set('form.year_to', '2004')->call('save')->assertHasNoErrors();
        Livewire::test(Cars::class)->call('create')->set('form.make', 'Toyota')->set('form.model', 'Mark II')->call('save')->assertHasErrors('form.model');

        // a new category with a part online appears in the menu, in the Lists order
        ListOption::create(['list' => 'part_category', 'value' => 'Body Kits', 'sort_order' => 0]);
        $this->part(['category' => 'Body Kits', 'name' => 'Front Lip Spoiler']);

        $this->get('/')->assertOk()->assertSee('Spare parts for every car in Dar')->assertSee('Straight from the importer')->assertSee('Open on Sundays in December')
            ->assertSee('Body Kits');
        \App\Models\PartVehicle::create(['part_id' => $this->part(['name' => 'Mark II Filter'])->id, 'car_model_id' => CarModel::where('model', 'Mark II')->value('id')]);
        Livewire::test(Home::class)->set('make', 'Toyota')->assertSee('Mark II');
        $this->assertContains('Body Kits', Part::shopDepartments());
        $this->assertNotContains('Consumables', Part::shopDepartments()); // listed, but nothing online
    }
}
