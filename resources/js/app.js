import './bootstrap';

// The detail ({ history, cached }) of the latest livewire:navigate, still null after a full page load.
let navigation = null;

// livewire:navigated also fires once after a full page load, so remember how each navigation started.
document.addEventListener('livewire:navigate', (event) => {
    navigation = event.detail;
});

// The swap moves the persisted sheets, and a modal <dialog> that is moved loses its modal state, so close open sheets first; their close handlers reset the forms.
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
});

// Back and forward restore the page as it was when you left it, so the components outside @persist (the page and SidebarLinks) fetch current data.
document.addEventListener('livewire:navigated', () => {
    if (navigation?.history && navigation.cached) {
        Livewire.all()
            .filter((component) => !component.el.closest('[x-persist]'))
            .forEach((component) => component.$wire.$refresh());
    }
});

// A screen reader announces a full page load but not a navigation, so the persisted live region reads out the new page's title.
document.addEventListener('livewire:navigated', () => {
    const announcer = document.getElementById('page-announcer');

    if (navigation && announcer) {
        announcer.textContent = document.title;
    }
});
