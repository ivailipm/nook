// The service worker. App\Http\Controllers\ServiceWorkerController serves this file at /sw.js after two constants it
// writes: VERSION, which changes with every build and with every change to this file or the offline page, and PRECACHE,
// the offline page and every built file. Vite never bundles it.
//
// Three caches: nook-shell-<version> holds the offline page, the built files and Livewire's script; nook-groceries holds
// the saved copy of the grocery list, the one page kept for use without a connection, with the version it was saved
// under; nook-share holds a photo shared to nook until the Promo board picks it up (resources/js/pwa.js). Every other
// request goes to the network as if there were no worker: Livewire's requests, every POST but the share, user photos.

const SHELL = `nook-shell-${VERSION}`;
const COPY = 'nook-groceries';
const SHARE = 'nook-share';

// How long /groceries waits for the network before it shows the saved copy, so a weak signal in the shop doesn't hang.
const PATIENCE = 4000;

// Livewire's script, /livewire-<hex>/livewire.js or livewire.min.js.
const LIVEWIRE_SCRIPT = /^\/livewire-[0-9a-f]+\/livewire(\.min)?\.js$/;

// A new version takes over as soon as it is installed, with no prompt to reload.
self.addEventListener('install', (event) => {
    event.waitUntil(precache().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(activate());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (url.origin !== location.origin) {
        return;
    }

    if (request.method === 'POST' && url.pathname === '/promos/share') {
        event.respondWith(receiveShare(request));
    } else if (request.method !== 'GET') {
        return;
    } else if (isAsset(url)) {
        event.respondWith(asset(event));
    } else if (request.mode === 'navigate' || request.headers.has('X-Livewire-Navigate')) {
        event.respondWith(url.pathname === '/groceries' ? groceries(event) : page(event));
    }
});

// resources/js/pwa.js asks for a fresh copy once the list has changed through Livewire.
self.addEventListener('message', (event) => {
    if (event.data === 'refresh-groceries') {
        event.waitUntil(fetch('/groceries').then(keepCopy));
    }
});

// Fills this version's shell cache. A built file that an older cache already holds is copied from there, because its
// content-hashed name means the same bytes. Any failure fails the install, and the current worker stays.
async function precache() {
    const shell = await caches.open(SHELL);

    await Promise.all(
        PRECACHE.map(async (path) => {
            const response = (path.startsWith('/build/') && (await caches.match(path))) || (await fetch(path));

            if (!response.ok) {
                throw new Error(`${path} answered ${response.status}`);
            }

            await shell.put(path, response);
        }),
    );
}

// Turns on navigation preload, so a page doesn't wait for the worker to start, and keeps only this version's shell
// cache and the one the saved copy was taken under, whose files the copy links to.
async function activate() {
    await self.registration.navigationPreload?.enable();

    const copy = await caches.match('/groceries', { cacheName: COPY });
    const kept = [SHELL, copy && `nook-shell-${copy.headers.get('X-Nook-Version')}`];

    for (const name of await caches.keys()) {
        if (name.startsWith('nook-shell-') && !kept.includes(name)) {
            await caches.delete(name);
        }
    }

    await self.clients.claim();
}

// The built files and Livewire's script, which never change under the same address.
function isAsset(url) {
    const path = url.pathname;

    return url.origin === location.origin && (path.startsWith('/build/assets/') || LIVEWIRE_SCRIPT.test(path));
}

// From any nook cache first. One that isn't cached yet comes from the network, and a 200 goes into this version's cache.
async function asset(event) {
    const cached = await caches.match(event.request);

    if (cached) {
        return cached;
    }

    const response = await fetch(event.request);

    if (response.status === 200) {
        const copy = response.clone();

        event.waitUntil(caches.open(SHELL).then((shell) => shell.put(event.request, copy)));
    }

    return response;
}

// The page from the network. A navigation takes the navigation preload response, which started with the navigation.
async function fromNetwork(event) {
    return (await event.preloadResponse) ?? fetch(event.request);
}

// Every page but /groceries: the network, or the offline page only when it can't be reached. An error status is passed
// on as it is. The login page forgets the saved copy.
async function page(event) {
    try {
        const response = await fromNetwork(event);

        if (isLogin(response)) {
            event.waitUntil(forgetCopy());
        }

        return response;
    } catch {
        return offlinePage();
    }
}

// /groceries: the network for up to 4 seconds, then the saved copy, or the offline page while there is none. A page
// that arrives after the copy has answered still updates it.
async function groceries(event) {
    const network = fromNetwork(event).then((response) => {
        event.waitUntil(keepCopy(response.clone()));

        return response;
    });

    // Keeps the worker running until the network answers, even after the copy has.
    event.waitUntil(network.catch(() => {}));

    try {
        return await Promise.race([network, new Promise((resolve, reject) => setTimeout(reject, PATIENCE))]);
    } catch {
        return (await savedCopy()) ?? offlinePage();
    }
}

// The login page, asked for or redirected to. Reaching it means nobody is signed in on this browser.
function isLogin(response) {
    return response.ok && new URL(response.url).pathname === '/login';
}

// Saves a 200 page from /groceries that wasn't redirected as the copy, with this version. The login page forgets the
// copy instead. The stylesheets and scripts the page links to go into this version's cache first, so the copy always
// opens styled and working, and then the shell cache the previous copy needed can go.
async function keepCopy(response) {
    if (isLogin(response)) {
        return forgetCopy();
    }

    const isHtml = response.headers.get('Content-Type')?.includes('text/html');

    if (response.status !== 200 || response.redirected || !isHtml) {
        return;
    }

    const html = await response.text();

    await storeLinkedFiles(html);

    const copies = await caches.open(COPY);
    const previous = (await copies.match('/groceries'))?.headers.get('X-Nook-Version');
    const headers = { 'Content-Type': 'text/html; charset=utf-8', 'X-Nook-Version': VERSION };

    await copies.put('/groceries', new Response(html, { headers }));

    if (previous && previous !== VERSION) {
        await caches.delete(`nook-shell-${previous}`);
    }
}

// Puts every same-origin stylesheet and script a page links to (the built files and Livewire's script) into this
// version's cache: from another nook cache when one holds it, which may be about to go, otherwise from the network.
async function storeLinkedFiles(html) {
    const shell = await caches.open(SHELL);
    const urls = [...html.matchAll(/<(?:link|script)\b[^>]*?\s(?:href|src)="([^"]*)"/g)]
        .map(([, address]) => new URL(address, location.origin))
        .filter(isAsset);

    await Promise.all(
        urls.map(async ({ href }) => {
            if (await shell.match(href)) {
                return;
            }

            const response = (await caches.match(href)) ?? (await fetch(href));

            if (!response.ok) {
                throw new Error(`${href} answered ${response.status}`);
            }

            await shell.put(href, response);
        }),
    );
}

function forgetCopy() {
    return caches.delete(COPY);
}

// The saved copy, marked with data-offline-copy on its <html>, which puts resources/js/pwa.js in read-only mode.
async function savedCopy() {
    const copy = await caches.match('/groceries', { cacheName: COPY });

    if (!copy) {
        return undefined;
    }

    const html = (await copy.text()).replace(/<html\b/i, '<html data-offline-copy');

    return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// The offline page, as a new response without an address of its own, so the page keeps the one it asked for.
async function offlinePage() {
    const offline = await caches.match('/offline', { cacheName: SHELL });

    return new Response(offline.body, { headers: offline.headers });
}

// The share target in manifest.webmanifest. The first shared photo waits in nook-share until the Promo board hands it to
// the add-promo sheet, and the share lands there. The POST never reaches the server, so it needs no CSRF token.
async function receiveShare(request) {
    const data = await request.formData();
    const photo = data.getAll('photo').find((entry) => entry instanceof File && entry.size > 0);

    if (photo) {
        const shared = await caches.open(SHARE);
        const headers = { 'Content-Type': photo.type, 'X-File-Name': encodeURIComponent(photo.name) };

        await shared.put('/promos/share', new Response(photo, { headers }));
    }

    return Response.redirect('/promos', 303);
}
