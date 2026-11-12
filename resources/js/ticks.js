// Instant ticking. Every tick checkbox calls $tick from its x-on:click with a save function, for example
// $tick((done, $wire) => $wire.toggle(12, done)). The checkbox and its row show the tapped state at once, through the
// attributes their classes follow, and the save sends that state. Counts, moves and toasts come with the reply's re-render.
//
// The save gets its component's $wire rather than reading Alpine's: a reply can rebuild the checkbox (a To-do row moving
// to Done), and Alpine's $wire, looked up from a checkbox that has left the page, does nothing.

const failure = 'That tick was not saved. Try again';

// The rows with a save waiting, by checkbox id: the state the server last confirmed, the state the row wants now, its
// component's $wire, and the promise that settles once the row's saves are done.
const waiting = new Map();

// True while a save function runs, so the action it fires is known as a tick save.
let sending = false;

// Shows a state on a checkbox and its row (the wire:key element): aria-pressed, the label for that state and data-done.
function show(box, done) {
    box.setAttribute('aria-pressed', done ? 'true' : 'false');
    box.setAttribute('aria-label', box.getAttribute(done ? 'data-checked-label' : 'data-label'));
    box.closest('[wire\\:key]').toggleAttribute('data-done', done);
}

function send(save, done, wire) {
    sending = true;

    try {
        return save(done, wire);
    } finally {
        sending = false;
    }
}

// Saves the wanted state, and once more if the row was tapped again while it waited. A failed save puts the row back to
// the state the server last confirmed, with a toast, unless it was tapped back to that state already. It never retries.
async function settle(id, row, save) {
    for (;;) {
        const done = row.wanted;

        try {
            await send(save, done, row.wire);
        } catch {
            if (row.wanted !== row.confirmed) {
                const box = document.getElementById(id);

                if (box) {
                    show(box, row.confirmed);
                }

                window.dispatchEvent(new CustomEvent('toast', { detail: { message: failure } }));
            }

            break;
        }

        row.confirmed = done;

        if (row.wanted === done) {
            break;
        }
    }

    waiting.delete(id);
}

// Flips the row at once and starts its save, or only changes what a row with a save waiting wants. Tick-only (Home's
// Groceries card) ignores a tap on a pressed box. Returns a promise that settles once the row's saves are done.
function tick(box, save, { tickOnly = false } = {}) {
    const pressed = box.getAttribute('aria-pressed') === 'true';
    let row = waiting.get(box.id);

    if (tickOnly && pressed) {
        return row?.settled ?? Promise.resolve();
    }

    show(box, !pressed);

    if (row) {
        row.wanted = !pressed;

        return row.settled;
    }

    row = { confirmed: pressed, wanted: !pressed, wire: Livewire.find(box.closest('[wire\\:id]').getAttribute('wire:id')) };
    waiting.set(box.id, row);
    row.settled = settle(box.id, row, save);

    return row.settled;
}

document.addEventListener('livewire:init', () => {
    Alpine.magic('tick', (el) => (save, options) => tick(el, save, options));

    // A failed tick save shows the toast rather than Livewire's error modal. A 419 still asks to refresh the page.
    Livewire.interceptAction(({ onError }) => {
        if (!sending) {
            return;
        }

        onError(({ response, preventDefault }) => {
            if (response.status !== 419) {
                preventDefault();
            }
        });
    });

    // Every reply re-renders its whole component, so a row with a save waiting writes the state it wants into the
    // incoming HTML first, and another reply can't show its old state even for a moment.
    Livewire.hook('morph', ({ toEl }) => {
        waiting.forEach((row, id) => {
            const box = toEl.querySelector(`#${CSS.escape(id)}`);

            if (box) {
                show(box, row.wanted);
            }
        });
    });
});
