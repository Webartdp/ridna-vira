<style>
    .publication-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
    .materials-section .publication-heading h2 { margin-bottom: 0; }
    .publication-heading > a { color: #83591e; font-family: var(--rv-font-ui); font-size: 14px; white-space: nowrap; text-decoration: underline; text-underline-offset: 4px; }
    .publication-placeholder { display: grid; place-items: center; width: 100%; height: 100%; min-height: 100px; padding: 24px; background: radial-gradient(ellipse at 20% 10%, #8a7544, transparent 65%), #393d2e; color: #f8eacc; }
    .publication-placeholder > span { font-family: var(--rv-font-display); font-size: 28px; text-align: center; line-height: 1.3; }
    .material-row > .publication-placeholder { display: grid; place-items: center; width: 154px; height: 100px; border: 3px solid #fff; border-radius: 20px; }
    .material-row > .publication-placeholder > span { font-size: 16px; }
    .materials-section .feature-material__overlay { background: linear-gradient(180deg, #17190e05 15%, #17190ee8 100%); }
    .materials-section .feature-material__content strong { font-size: clamp(18px, 2vw, 24px); line-height: 1.3; overflow-wrap: anywhere; }
    .materials-section .feature-material__content small { padding-right: 45px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .materials-section .material-row strong { line-height: 1.4; overflow-wrap: anywhere; }
    .material-meta { color: #80653d; font-family: var(--rv-font-ui); font-size: 12px; }
    .publication-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 28px 24px; }
    .publication-card { min-width: 0; border: 1px solid #d8ccba; background: #fffdf8; border-radius: 12px; overflow: hidden; }
    .publication-card__visual { display: block; aspect-ratio: 8 / 5; overflow: hidden; background: #e9e2d2; }
    .publication-card__visual img { width: 100%; height: 100%; object-fit: cover; }
    .publication-card__body { padding: 22px; }
    .publication-card h2 { margin: 10px 0 14px; font-family: var(--rv-font-ui); font-size: 21px; line-height: 1.4; overflow-wrap: anywhere; }
    .publication-card p { margin: 0 0 14px; color: #665548; font-family: var(--rv-font-ui); font-size: 14px; line-height: 1.7; }
    .publication-card__link { display: inline-block; padding: 8px 0; font-family: var(--rv-font-ui); font-size: 14px; color: #83591e; }
    .publication-categories { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 32px; }
    .publication-categories a { padding: 10px 14px; border: 1px solid #d8c8b0; border-radius: 8px; color: #6a401c; background: #fffcf6; font-family: var(--rv-font-ui); font-size: 13px; }
    .publication-categories .is-current { background: #6a401c; color: #fff; }
    .publication-meta { margin: 16px 0 0; color: #80653d; font-family: var(--rv-font-ui); font-size: 14px; }
    .publication-prose img { display: block; max-width: 100%; max-height: 900px; width: auto; height: auto; }
    .publication-prose p:has(> img + img) { display: flex; flex-wrap: wrap; align-items: center; gap: 24px; }
    .publication-prose p:has(> img + img) > img { max-width: min(100%, 340px); margin: 0; }
    .publication-prose table img { max-width: 100%; }
    .publication-videos { margin-top: 32px; display: grid; gap: 12px; font-family: var(--rv-font-ui); }
    .publication-videos a { display: block; padding: 16px; border: 1px solid #d8ccba; border-radius: 8px; color: #6a401c; }
    @media (max-width: 1000px) { .publication-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) {
        .publication-cards { grid-template-columns: 1fr; }
        .publication-heading { gap: 12px; }
        .publication-heading > a { font-size: 12px; }
        .material-row > .publication-placeholder { width: 100px; height: 90px; padding: 8px; }
        .publication-card__body { padding: 20px; }
        .publication-prose .article-table table { min-width: 0; }
        .publication-prose .article-table td { min-width: 0; padding: 5px; }
    }
</style>
