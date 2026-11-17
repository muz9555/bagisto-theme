(() => {
    const activate = (button, focus = false) => {
        const group = button.closest('[data-mx-tabs]');
        if (!group) return;
        group.querySelectorAll('[role="tab"]').forEach(tab => {
            const selected = tab === button;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        group.querySelectorAll('[role="tabpanel"]').forEach(panel => {
            panel.hidden = panel.id !== button.dataset.mxTarget;
        });
        if (focus) button.focus();
    };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-mx-target]');
        if (button) activate(button);
        const link = event.target.closest('.mx-mobile-menu nav a');
        if (link) link.closest('details').open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            const open = document.querySelector('.mx-mobile-menu[open]');
            if (open) { open.open = false; open.querySelector('summary').focus(); }
        }
        const button = event.target.closest('[data-mx-target]');
        if (!button || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const tabs = [...button.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
        const current = tabs.indexOf(button);
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault();
        activate(tabs[index], true);
    });
})();
