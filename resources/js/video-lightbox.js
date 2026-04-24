const SELECTOR = '[data-video-id]';

function buildEmbedUrl(id) {
    return `https://www.youtube.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0&modestbranding=1`;
}

function open(lightbox, frame, id) {
    frame.src = buildEmbedUrl(id);
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
    lightbox.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
        lightbox.style.opacity = '1';
    });
    document.body.style.overflow = 'hidden';
}

function close(lightbox, frame) {
    lightbox.style.opacity = '0';
    lightbox.setAttribute('aria-hidden', 'true');
    setTimeout(() => {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
        frame.src = '';
    }, 200);
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('go-video-lightbox');
    if (!lightbox) return;

    const frame = document.getElementById('go-video-lightbox-frame');
    const closeBtn = document.getElementById('go-video-lightbox-close');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(SELECTOR);
        if (!trigger) return;
        const id = trigger.getAttribute('data-video-id');
        if (!id) return;
        e.preventDefault();
        open(lightbox, frame, id);
    });

    closeBtn.addEventListener('click', () => close(lightbox, frame));

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) close(lightbox, frame);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !lightbox.classList.contains('hidden')) {
            close(lightbox, frame);
        }
    });
});
