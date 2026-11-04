<style>
    .catalog { --rose: #8e5a66; --rose-dark: #6f4350; --blush: #f5e9ec; }
    .catalog .hero { background: var(--blush); border: 1px solid #e8c6cf; border-radius: .5rem; padding: 1.75rem; }
    .catalog .hero h1, .catalog .section-title, .catalog .serif { font-family: Georgia, 'Times New Roman', serif; color: var(--rose); letter-spacing: .04em; }
    .catalog .hero h1, .catalog .section-title { text-transform: uppercase; }
    .catalog .hero h1 { font-size: 1.6rem; }
    .catalog .btn-rose { background: var(--rose); color: #fff; border: 0; }
    .catalog .btn-rose:hover { background: var(--rose-dark); color: #fff; }
    .catalog .product { background: #fff; border: 1px solid #f0dde2; border-radius: .5rem; overflow: hidden; height: 100%; display: flex; flex-direction: column; }
    .catalog .product img, .catalog .detail-img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--blush); }
    .catalog .tags { position: absolute; top: .6rem; left: .6rem; display: flex; gap: .3rem; flex-wrap: wrap; }
    .catalog .tag { font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: #fff; padding: .2rem .5rem; border-radius: .2rem; }
    .catalog .tag-sameday { background: var(--rose); }
    .catalog .tag-luxury { background: #a27a84; }
    .catalog .tag-out { background: #6c757d; }
    .catalog .price { color: var(--rose); font-size: 1.15rem; }
    .catalog a.card-link { color: inherit; text-decoration: none; }
    .catalog a.card-link:hover .fw-semibold { color: var(--rose); }
    .catalog summary { cursor: pointer; }
</style>