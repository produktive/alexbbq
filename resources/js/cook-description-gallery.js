export default function cookDescriptionGallery() {
    let unsubscribeMorph = null;
    let processFrame = null;

    return {
        images: [],
        lightboxOpen: false,
        lightboxIndex: 0,
        processed: false,

        init() {
            this.scheduleProcess();

            if (typeof Livewire !== 'undefined') {
                unsubscribeMorph = Livewire.hook('morph.updated', ({ el }) => {
                    const root = this.$refs.content;

                    if (!root || !root.isConnected) {
                        return;
                    }

                    if (el === root || root.contains(el) || el.contains(root)) {
                        this.scheduleProcess();
                    }
                });
            }
        },

        destroy() {
            unsubscribeMorph?.();

            if (processFrame !== null) {
                cancelAnimationFrame(processFrame);
            }

            document.body.classList.remove('overflow-hidden');
        },

        scheduleProcess() {
            if (processFrame !== null) {
                cancelAnimationFrame(processFrame);
            }

            processFrame = requestAnimationFrame(() => {
                processFrame = null;
                this.$nextTick(() => this.process());
            });
        },

        process() {
            const root = this.$refs.content;

            if (!root) {
                return;
            }

            const imgs = [...root.querySelectorAll('img')].filter(
                (img) => !img.closest('.cook-description-gallery'),
            );

            if (!imgs.length) {
                this.processed = true;

                return;
            }

            root.querySelector('.cook-description-gallery')?.remove();
            this.images = [];

            if (this.lightboxOpen) {
                this.closeLightbox();
            }

            const gallery = document.createElement('div');
            gallery.className = 'cook-description-gallery fi-not-prose';

            imgs.forEach((img, index) => {
                const src = img.getAttribute('src');
                const caption = img.getAttribute('alt') ?? '';

                if (!src) {
                    return;
                }

                this.images.push({ src, caption });

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cook-description-thumb';
                button.setAttribute('aria-label', caption || `View image ${index + 1}`);
                button.addEventListener('click', () => this.openLightbox(index));

                const thumb = document.createElement('img');
                thumb.src = src;
                thumb.alt = caption;
                thumb.loading = 'lazy';
                thumb.draggable = false;

                button.appendChild(thumb);
                gallery.appendChild(button);

                const parent = img.closest('p');

                img.remove();

                if (parent && parent.textContent.trim() === '' && parent.children.length === 0) {
                    parent.remove();
                }
            });

            root.appendChild(gallery);
            this.processed = true;
        },

        openLightbox(index) {
            this.lightboxIndex = index;
            this.lightboxOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        closeLightbox() {
            this.lightboxOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        next() {
            if (!this.images.length) {
                return;
            }

            this.lightboxIndex = (this.lightboxIndex + 1) % this.images.length;
        },

        prev() {
            if (!this.images.length) {
                return;
            }

            this.lightboxIndex = (this.lightboxIndex - 1 + this.images.length) % this.images.length;
        },

        lightboxSrc() {
            return this.images[this.lightboxIndex]?.src ?? '';
        },

        lightboxCaption() {
            return this.images[this.lightboxIndex]?.caption ?? '';
        },

        hasCaption() {
            return this.lightboxCaption().trim() !== '';
        },

        hasMultiple() {
            return this.images.length > 1;
        },
    };
}
