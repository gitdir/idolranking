const grids = document.querySelectorAll('.fan-post-grid');

grids.forEach((grid) => {
    const cards = [...grid.querySelectorAll(':scope > .fan-post-card')];
    if (!cards.length) return;

    let frame;
    const layout = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            cards.forEach((card) => {
                const span = Math.ceil(card.getBoundingClientRect().height + 11);
                const value = `span ${span}`;
                if (card.style.gridRowEnd !== value) card.style.gridRowEnd = value;
            });
        });
    };

    const observer = new ResizeObserver(layout);
    observer.observe(grid);
    cards.forEach((card) => observer.observe(card));
    layout();
});
