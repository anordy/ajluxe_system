@props(['model', 'current' => null, 'max' => 1200, 'format' => 'image/jpeg', 'reset' => null, 'size' => 'h-16 w-16'])
{{--
    Picture picker that never uses Livewire's upload route:
    the browser shrinks the picture (longest side ≤ $max px), uploads it once to pictures.upload and puts
    only the short "upload:…" reference in the Livewire property $model, so the form stays light.
    If that upload address cannot be reached, the picture itself is put in $model and sent with Save.
    format: image/jpeg for photos, image/png for logos (keeps transparency).
    reset:  a browser event name that clears the preview (e.g. when the form re-opens).
--}}
<div x-data="{
        preview: null,
        busy: '',
        error: '',
        async pick(e) {
            const f = e.target.files[0];
            e.target.value = '';
            this.error = '';
            if (! f) return;
            if (! /^image\/(jpeg|png|webp|gif|bmp)$/i.test(f.type)) { this.error = 'Choose a JPG or PNG picture (iPhone HEIC photos: set the camera to “Most compatible”).'; return; }
            this.busy = 'Preparing picture…';
            try {
                const img = await this.load(f);
                const s = Math.min(1, {{ (int) $max }} / Math.max(img.width, img.height));
                const c = document.createElement('canvas');
                c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
                const ctx = c.getContext('2d');
                @if ($format === 'image/jpeg') ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); @endif
                ctx.drawImage(img, 0, 0, c.width, c.height);
                if (img.close) img.close();
                // photos: WebP where the browser can make it (about a third smaller than JPG)
                let blob = await new Promise(r => c.toBlob(r, '{{ $format === 'image/jpeg' ? 'image/webp' : $format }}', 0.8));
                if (! blob || blob.type !== '{{ $format === 'image/jpeg' ? 'image/webp' : $format }}') blob = await new Promise(r => c.toBlob(r, '{{ $format }}', 0.82));
                if (this.preview && this.preview.startsWith('blob:')) URL.revokeObjectURL(this.preview);
                this.preview = URL.createObjectURL(blob);
                this.busy = 'Uploading…';
                try {
                    const res = await fetch(@js(route('pictures.upload')), { method: 'POST', body: blob, credentials: 'same-origin',
                        headers: { 'Content-Type': blob.type, 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } });
                    const out = await res.json().catch(() => ({}));
                    if (! res.ok || ! out.token) throw new Error(out.message || 'upload');
                    $wire.set('{{ $model }}', out.token, false);   // only a short reference stays in the form
                } catch (err) {
                    if (err.message && err.message !== 'upload' && ! err.message.includes('fetch')) { this.error = err.message; this.preview = null; this.busy = ''; return; }
                    // no connection to the upload address: send the picture with Save instead
                    $wire.set('{{ $model }}', c.toDataURL('{{ $format }}', 0.82), false);
                }
                this.busy = '';
            } catch (err) {
                this.busy = ''; this.error = 'This picture could not be opened. Try a JPG or PNG.';
            }
        },
        load(f) {
            if (window.createImageBitmap) return createImageBitmap(f).catch(() => this.loadImg(f));
            return this.loadImg(f);
        },
        loadImg(f) {
            return new Promise((ok, fail) => { const i = new Image(); i.onload = () => { URL.revokeObjectURL(i.src); ok(i) }; i.onerror = fail; i.src = URL.createObjectURL(f); });
        },
        clear() { this.preview = null; this.error = ''; $wire.set('{{ $model }}', null, false); }
    }"
    @if ($reset) x-on:{{ $reset }}.window="preview = null; error = ''" @endif
    class="flex items-center gap-4">
    <div class="{{ $size }} flex shrink-0 items-center justify-center overflow-hidden rounded-lg border border-ink-100 bg-ink-50">
        <img x-show="preview" x-cloak :src="preview" class="h-full w-full object-contain" alt="">
        @if ($current)
            <img x-show="! preview" src="{{ $current }}" class="h-full w-full object-contain" alt="">
        @else
            <span x-show="! preview"><x-icon name="box" class="h-6 w-6 text-ink-300" /></span>
        @endif
    </div>
    <div class="min-w-0">
        <input type="file" accept="image/*" @change="pick($event)"
               class="block text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
        <p x-show="busy" x-cloak class="mt-1 text-xs text-ink-500" x-text="busy"></p>
        <p x-show="preview && ! busy" x-cloak class="mt-1 text-xs text-ink-500">Ready — it is saved when you press Save. <button type="button" class="font-semibold text-brand-600" @click="clear()">Undo</button></p>
        <p x-show="error" x-cloak class="error" x-text="error"></p>
    </div>
</div>
