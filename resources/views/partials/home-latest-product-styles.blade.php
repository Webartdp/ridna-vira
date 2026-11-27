<style>
    .home-latest-products {
        padding: clamp(36px, 6vw, 72px) 0;
    }

    .home-latest-products__heading {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px 24px;
        margin-bottom: 20px;
    }

    .home-latest-products__heading h2 {
        margin: 0;
        font-family: var(--rv-font-display);
        font-size: clamp(1.625rem, 3vw, 2.375rem);
    }

    .home-latest-products__heading a {
        color: var(--rv-brown);
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 4px;
    }

    .home-latest-products__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .home-latest-products__card {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
        border: 1px solid #d7cab3;
        border-radius: 14px;
        background: #fffdf6;
        color: var(--rv-dark);
        box-shadow: 0 2px 8px rgba(28, 8, 3, .08);
    }

    .home-latest-products__card:hover {
        border-color: #9b7845;
        color: var(--rv-brown);
    }

    .home-latest-products__card img {
        width: 100%;
        height: auto;
        aspect-ratio: 1;
        object-fit: cover;
        background: #ede4d2;
    }

    .home-latest-products__details {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 8px;
        padding: 14px;
    }

    .home-latest-products__details h3,
    .home-latest-products__details p {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .home-latest-products__details p {
        margin-top: auto;
        color: var(--rv-brown);
    }

    @media (max-width: 899.98px) {
        .home-latest-products__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 479.98px) {
        .home-latest-products__grid { grid-template-columns: 1fr; }
    }
</style>
