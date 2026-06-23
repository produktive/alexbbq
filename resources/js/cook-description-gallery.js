export default function cookDescriptionGallery() {
    return {
        images: [],
        lightboxOpen: false,
        lightboxIndex: 0,
        processed: false,

        init() {
            const root = this.$refs.content;

            if (!root) {
                return;
            }

            const imgs = [...root.querySelectorAll('img')];

            if (!imgs.length) {
                this.processed = true;

                return;
            }

            const gallery = document.createElement('div');
            gallery.className = 'cook-description-gallery';

            imgs.forEach((img, index) => {
                const src = img.getAttribute('src');
                const alt = img.getAttribute('alt') ?? '';

                if (!src) {
                    return;
                }

                this.images.push({ src, alt });

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cook-description-thumb';
                button.setAttribute('aria-label', alt || `View image ${index + 1}`);
                button.addEventListener('click', () => this.openLightbox(index));

                const thumb = document.createElement('img');
                thumb.src = src;
                thumb.alt = alt;
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

        lightboxAlt() {
            return this.images[this.lightboxIndex]?.alt ?? '';
        },

        hasMultiple() {
            return this.images.length > 1;
        },
    };
}
