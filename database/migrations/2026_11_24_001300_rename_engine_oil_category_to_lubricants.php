<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The oil department is renamed "Engine Oil" → "Lubricants" (engine oil, ATF, CVT fluid…).
 * Its types (Engine Oil, Transmission) stay as they are. Any older "Lubricants & Fluids" category is merged in.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['Engine Oil', 'Lubricants & Fluids'] as $old) {
            DB::table('parts')->where('category', $old)->update(['category' => 'Lubricants']);
            $row = DB::table('list_options')->where('list', 'part_category')->where('value', $old)->first();
            if ($row) {
                DB::table('list_options')->where('list', 'part_category')->where('value', 'Lubricants')->exists()
                    ? DB::table('list_options')->where('id', $row->id)->delete()
                    : DB::table('list_options')->where('id', $row->id)->update(['value' => 'Lubricants']);
            }
        }

        if (Schema::hasTable('site_banners')) {
            foreach (DB::table('site_banners')->where('link_url', 'like', '%category=Engine%Oil%')->get(['id', 'link_url', 'button_label']) as $b) {
                DB::table('site_banners')->where('id', $b->id)->update([
                    'link_url' => str_replace(['category=Engine+Oil', 'category=Engine%20Oil'], 'category=Lubricants', $b->link_url),
                    'button_label' => $b->button_label === 'Shop engine oil' ? 'Shop lubricants' : $b->button_label,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('parts')->where('category', 'Lubricants')->update(['category' => 'Engine Oil']);
        DB::table('list_options')->where('list', 'part_category')->where('value', 'Lubricants')->update(['value' => 'Engine Oil']);
    }
};
