<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional Kiswahili wording for text banners (shown when a visitor chooses Kiswahili). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_banners', function (Blueprint $t) {
            $t->string('kicker_sw', 80)->nullable()->after('kicker');
            $t->string('title_sw', 160)->nullable()->after('title');
            $t->string('text_sw', 400)->nullable()->after('text');
            $t->string('button_label_sw', 60)->nullable()->after('button_label');
        });

        // the starter banners get their Kiswahili wording straight away
        $starter = [
            'New tyres for every car' => ['Matairi', 'Matairi mapya kwa kila gari', 'Saizi za magari madogo, SUV na all-terrain zipo. Tunayafunga na kuyafanyia balancing gereji.', 'Tafuta saizi yako'],
            'Spray booth painting, panel by panel' => ['Gereji na rangi', 'Kupaka rangi kwenye chumba maalum, sehemu kwa sehemu', 'Bampa, milango, boneti au gari zima. Leta fundi wako wa rangi au tumia wetu.', 'Weka miadi ya gari lako'],
            'The right oil for your engine' => ['Vilainishi', 'Oili sahihi kwa injini yako', 'Oili za synthetic, semi-synthetic na dizeli pamoja na vichujio vinavyoendana.', 'Nunua vilainishi'],
            'Order online, collect today' => ['Agiza mtandaoni', 'Agiza mtandaoni, chukua leo', 'Tunakuwekea vipuri vyako na kukupigia vikiwa tayari. Lipa kwa M-Pesa, benki au taslimu.', 'Anza kununua'],
        ];
        foreach ($starter as $title => [$k, $ti, $tx, $bt]) {
            \Illuminate\Support\Facades\DB::table('site_banners')->where('title', $title)->whereNull('title_sw')
                ->update(['kicker_sw' => $k, 'title_sw' => $ti, 'text_sw' => $tx, 'button_label_sw' => $bt]);
        }
    }

    public function down(): void
    {
        Schema::table('site_banners', fn (Blueprint $t) => $t->dropColumn(['kicker_sw', 'title_sw', 'text_sw', 'button_label_sw']));
    }
};
