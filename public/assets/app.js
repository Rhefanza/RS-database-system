document.addEventListener('DOMContentLoaded', () => {
    const panels = [...document.querySelectorAll('[data-master-panel]')];
    const tabs = [...document.querySelectorAll('[data-master-tab]')];
    if (!panels.length) return;

    const show = (id) => {
        const selected = panels.some((panel) => panel.id === id) ? id : panels[0].id;
        panels.forEach((panel) => panel.classList.toggle('active', panel.id === selected));
        tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.masterTab === selected));
    };

    show(location.hash.slice(1));
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.masterTab)));
    window.addEventListener('hashchange', () => show(location.hash.slice(1)));
});
