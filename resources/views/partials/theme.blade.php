<script>
    (() => {
        const flux = window.Flux ??= {};
        flux.applyAppearance = (appearance) => {
            const theme = appearance === 'light' ? 'light' : 'dark';
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme;
            try { localStorage.setItem('flux.appearance', theme); } catch {}
        };
        let theme = 'dark';
        try { theme = localStorage.getItem('flux.appearance') === 'light' ? 'light' : 'dark'; } catch {}
        flux.applyAppearance(theme);
    })();
</script>
