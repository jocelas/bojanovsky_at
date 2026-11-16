(() => {
  const root = document.documentElement;
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
  const sun = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>';
  const moon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 14A8.5 8.5 0 0 1 10 3.5 8.5 8.5 0 1 0 20.5 14Z"/></svg>';

  function updateButtons() {
    const dark = root.dataset.theme === 'dark' || (!root.dataset.theme && systemTheme.matches);
    document.querySelectorAll('.theme-toggle').forEach(button => {
      button.innerHTML = dark ? sun : moon;
      const label = `Switch to ${dark ? 'light' : 'dark'} theme`;
      button.setAttribute('aria-label', label);
      button.title = label;
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.theme-toggle').forEach(button => {
      button.hidden = false;
      button.addEventListener('click', () => {
        const dark = getComputedStyle(root).colorScheme === 'dark';
        root.dataset.theme = dark ? 'light' : 'dark';
        updateButtons();
      });
    });
    updateButtons();
  });
  systemTheme.addEventListener('change', () => {
    delete root.dataset.theme;
    updateButtons();
  });
})();
