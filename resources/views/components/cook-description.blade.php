@props(['html'])

<div x-data="cookDescriptionGallery()" {{ $attributes }}>
    <article
        id="cook-description"
        x-ref="content"
        class="cook-description"
        :class="{ 'is-processed': processed }"
    >
        {!! $html !!}
    </article>

    <template x-teleport="body">
        <div
            x-show="lightboxOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
            @keydown.escape.window="closeLightbox()"
            @keydown.arrow-right.window="lightboxOpen && next()"
            @keydown.arrow-left.window="lightboxOpen && prev()"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-default"
                aria-label="Close lightbox"
                @click="closeLightbox()"
            ></button>

            <button
                type="button"
                class="absolute inset-e-4 top-4 z-10 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                aria-label="Close"
                @click="closeLightbox()"
            >
                <flux:icon.x-mark variant="mini" class="size-5"/>
            </button>

            <template x-if="hasMultiple()">
                <button
                    type="button"
                    class="absolute inset-s-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:inset-s-4"
                    aria-label="Previous image"
                    @click.stop="prev()"
                >
                    <flux:icon.chevron-left variant="mini" class="size-6"/>
                </button>
            </template>

            <figure class="relative z-1 max-h-[90vh] max-w-[90vw]">
                <img
                    :src="lightboxSrc()"
                    :alt="lightboxCaption()"
                    class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                    @click.stop
                >

                <figcaption
                    x-show="hasCaption()"
                    x-cloak
                    x-text="lightboxCaption()"
                    class="cook-description-lightbox-caption"
                ></figcaption>
            </figure>

            <template x-if="hasMultiple()">
                <button
                    type="button"
                    class="absolute inset-e-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:inset-e-4"
                    aria-label="Next image"
                    @click.stop="next()"
                >
                    <flux:icon.chevron-right variant="mini" class="size-6"/>
                </button>
            </template>
        </div>
    </template>
</div>
