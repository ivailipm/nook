// The installable app. Registers the service worker (resources/js/sw.js, served at /sw.js), keeps its saved copy of the
// grocery list current, puts that copy in read-only mode when the worker answers with it, and hands a photo shared to
// nook to the add-promo sheet. Service workers and the Cache API only exist on secure origins, so on plain http none of
// this runs.

if ('serviceWorker' in navigator && 'caches' in window) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' });
    });

    // A change made through Livewire after the page loaded reaches the copy: once a burst of changes has settled, the
    // worker fetches the list again.
    let refresh = null;

    for (const name of ['grocery-item-added', 'promo-changed', 'nav-counts-changed']) {
        window.addEventListener(name, () => {
            clearTimeout(refresh);
            refresh = setTimeout(refreshCopy, 2000);
        });
    }

    // livewire:navigated fires after each navigation and once after a full page load. This listens on window, so it runs
    // after app.js's listener on document has written the page title into the announcer.
    window.addEventListener('livewire:navigated', () => {
        if (isCopy()) {
            readOnly();
        } else if (location.pathname === '/promos') {
            pickUpSharedPhoto();
        }
    });
}

// The worker marks the copy's <html> when it answers with it, and a navigation carries that attribute over.
function isCopy() {
    return document.documentElement.hasAttribute('data-offline-copy');
}

function refreshCopy() {
    const worker = navigator.serviceWorker.controller;

    if (worker && !isCopy()) {
        worker.postMessage('refresh-groceries');
    }
}

// Read-only mode, for the copy of the grocery list: every button and input but the banner's "Try again" is disabled,
// screen readers hear why, and any navigation becomes a full page load, so leaving the copy always gets a fresh page and
// token. Ticking offline will lift this for the ticks.
function readOnly() {
    const banner = document.getElementById('offline-copy');

    document.querySelectorAll('button, input').forEach((control) => {
        if (!banner.contains(control)) {
            control.disabled = true;
        }
    });

    document.getElementById('page-announcer').textContent = banner.querySelector('p').textContent.trim();

    document.addEventListener('livewire:navigate', loadInFull);
}

function loadInFull(event) {
    event.preventDefault();
    location.href = event.detail.url.href;
}

// A photo shared to nook waits in nook-share (sw.js) until the Promo board shows. It goes into the add-promo sheet's
// own file input, so it takes the path of a picked one: the upload, the preview and the same validation. Opening the
// sheet resets its form, so the photo waits for that round trip, or the reset would discard it.
async function pickUpSharedPhoto() {
    const shared = await caches.match('/promos/share', { cacheName: 'nook-share' });
    const input = document.querySelector('#sheet-add-promo input[type="file"]');

    if (!shared || !input) {
        return;
    }

    const blob = await shared.blob();
    const transfer = new DataTransfer();

    transfer.items.add(new File([blob], decodeURIComponent(shared.headers.get('X-File-Name')), { type: blob.type }));

    window.dispatchEvent(new CustomEvent('open-sheet', { detail: { name: 'add-promo' } }));
    await Livewire.find(input.closest('[wire\\:id]').getAttribute('wire:id')).$commit();

    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));

    const cache = await caches.open('nook-share');

    await cache.delete('/promos/share');
}
