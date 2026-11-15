{{-- Tombol ganti mode terang/gelap. Perilakunya di resources/js/app.js (data-ganti-tema). --}}
<button type="button" {{ $attributes->merge(['class' => 'button button--ghost button--neutral button--icon-only']) }} data-ganti-tema aria-label="Ganti mode terang atau gelap" title="Ganti mode terang/gelap">
    <x-ikon nama="bulan" class="size-5 dark:hidden" />
    <x-ikon nama="matahari" class="hidden size-5 dark:block" />
</button>
