<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website clean-up:
 *  - "Engine Oil & Lubricants" (old starter category) is merged into "Engine Oil", so the shop menu has one oil department
 *  - banners that pointed to the paint price list now point to "Book your car in" (prices are no longer on the website)
 */
return new class extends Migration
{
    private const OLD = 'Engine Oil & Lubricants';
    private const NEW = 'Engine Oil';

    public function up(): void
    {
        DB::table('parts')->where('category', self::OLD)->update(['category' => self::NEW]);

        $old = DB::table('list_options')->where('list', 'part_category')->where('value', self::OLD)->first();
        if ($old) {
            $hasNew = DB::table('list_options')->where('list', 'part_category')->where('value', self::NEW)->exists();
            $hasNew
                ? DB::table('list_options')->where('id', $old->id)->delete()
                : DB::table('list_options')->where('id', $old->id)->update(['value' => self::NEW]);
        }

        if (Schema::hasTable('site_banners')) {
            DB::table('site_banners')->where('link_url', '/garage#paint')
                ->update(['link_url' => '/garage#book', 'button_label' => 'Book your car in']);
        }
    }

    public function down(): void
    {
        // merged categories cannot be told apart again
    }
};
