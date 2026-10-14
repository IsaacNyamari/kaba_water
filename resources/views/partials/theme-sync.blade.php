{{--
    Keeps light / dark mode in step after the first paint. @fluxAppearance only
    applies the theme once on load; this also follows the operating system when
    it switches theme (if the user hasn't picked one) and re-applies the saved
    choice after wire:navigate page swaps.
--}}
<script>
    (() => {
        if (window.__kabaThemeSync) {
            return;
        }
        window.__kabaThemeSync = true;

        const media = window.matchMedia('(prefers-color-scheme: dark)');

        const apply = () => {
            const choice = window.localStorage.getItem('flux.appearance') || 'system';
            const dark = choice === 'dark' || (choice === 'system' && media.matches);

            document.documentElement.classList.toggle('dark', dark);
        };

        media.addEventListener('change', apply);
        document.addEventListener('livewire:navigated', apply);
    })();
</script>
