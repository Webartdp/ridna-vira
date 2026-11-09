@vite('resources/scss/articles.scss')
<style>
    body.rituals-view { background: #f7f3ea; }
    body.rituals-view::before { content: none; }
    .rituals-intro { max-width: 760px; margin-bottom: 32px; font-family: var(--rv-font-ui); line-height: 1.8; }
    .rituals-nav { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 0 32px; margin: 0 0 24px; border-bottom: 1px solid #d8ccba; }
    .rituals-nav a, .ritual-downloads a { display: inline-flex; align-items: center; min-height: 44px; padding: 10px 16px; border: 1px solid #d8c8b0; border-radius: 8px; color: #6a401c; font-family: var(--rv-font-ui); font-size: 14px; background: #fffcf6; }
    .rituals-nav a:hover, .ritual-downloads a:hover { background: #f0e4d0; }
    .rituals-section { margin-top: 52px; scroll-margin-top: 110px; }
    .rituals-section > h2 { margin-bottom: 24px; font-family: var(--rv-font-display); font-size: clamp(26px, 4vw, 36px); line-height: 1.3; }
    .rituals-section .article-preview__author { font-size: 13px; color: #8a5d16; }
    .ritual-guides { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
    .ritual-guide { min-width: 0; padding: 24px; border: 1px solid #d8ccba; border-radius: 8px; background: #fffcf6; font-family: var(--rv-font-ui); overflow-wrap: anywhere; }
    .ritual-guide h3 { margin: 6px 0 18px; font-family: var(--rv-font-ui); font-size: 20px; line-height: 1.4; }
    .ritual-guide__city { margin: 0; color: #8a5d16; font-size: 13px; }
    .ritual-guide a { display: block; width: fit-content; max-width: 100%; padding: 7px 0; font-size: 14px; line-height: 1.6; color: #6a401c; text-decoration: underline; text-underline-offset: 3px; }
    .ritual-videos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; list-style: none; padding: 0; }
    .ritual-videos a { display: flex; gap: 16px; align-items: center; padding: 20px; height: 100%; border: 1px solid #d8ccba; border-radius: 8px; font-family: var(--rv-font-ui); color: #6a401c; line-height: 1.5; }
    .ritual-videos a:hover { background: #f0e4d0; }
    .ritual-video-icon { display: grid; place-items: center; flex: 0 0 36px; height: 36px; border-radius: 50%; background: #6a401c; color: #fff; font-size: 12px; }
    .ritual-downloads { display: flex; flex-wrap: wrap; gap: 12px; margin: 24px 0 32px; }
    .ritual-downloads a { gap: 12px; }
    .ritual-downloads small { opacity: .7; }
    .ritual-source { margin-top: 36px; padding-top: 24px; border-top: 1px solid #dfd6c7; color: #665548; font-family: var(--rv-font-ui); font-size: 13px; }
    .ritual-source a { color: #775020; text-decoration: underline; }
    .ritual-page-title { overflow-wrap: anywhere; }
    .ritual-toc { padding: 16px 20px; margin: 0 0 32px; border: 1px solid #d8ccba; border-radius: 8px; font-family: var(--rv-font-ui); line-height: 1.7; }
    .ritual-toc summary { cursor: pointer; color: #6a401c; }
    .ritual-toc ol { padding-left: 22px; margin: 16px 0 0; }
    .ritual-toc a { display: inline-block; padding: 6px 0; color: #6a401c; overflow-wrap: anywhere; }
    .rituals-view .article-prose h2, .rituals-view .article-prose h3, .rituals-view .article-prose h4 { scroll-margin-top: 110px; }
    .ritual-reading-key { display: flex; flex-wrap: wrap; gap: 10px 20px; margin: 0 0 32px; padding: 16px 0; border-block: 1px solid #dfd6c7; font-family: var(--rv-font-ui); font-size: 13px; line-height: 1.6; color: #665548; }
    .ritual-reading-key span { display: inline-flex; align-items: center; gap: 8px; }
    .ritual-reading-key i { width: 10px; height: 10px; border-radius: 2px; background: #b78d4d; }
    .ritual-reading-key .ritual-key-direction { background: #9b9a8f; }
    .ritual-reading-key .ritual-key-song { background: #65765d; }
    .article-prose .ritual-passage { margin: 28px 0; padding: 24px 28px; border: 1px solid #ddc9a6; border-left: 4px solid #a47937; border-radius: 0 10px 10px 0; background: #f7efdf; color: #382b1c; }
    .article-prose .ritual-passage > p { margin: 0 0 18px; line-height: 1.9; }
    .article-prose .ritual-passage > p:last-child { margin-bottom: 0; }
    .article-prose .ritual-passage > .ritual-passage__label { margin: 0 0 16px; color: #82551c; font-family: var(--rv-font-ui); font-size: 12px; font-weight: 400; line-height: 1.5; letter-spacing: .06em; text-transform: uppercase; }
    .article-prose .ritual-passage--praise { background: #eee4ce; border-color: #cfb47f; border-left-color: #8b601c; }
    .article-prose .ritual-passage--direction { padding: 14px 20px; background: #f4f3ee; border-color: #deddd4; border-left: 2px solid #9b9a8f; color: #625f53; font-size: 15px; }
    .article-prose .ritual-passage--note { padding: 14px 20px; background: #f4f3ee; border-color: #deddd4; border-left: 2px solid #9b9a8f; color: #625f53; font-size: 15px; }
    .article-prose .ritual-passage--note > .ritual-passage__label { color: #747063; }
    .article-prose .ritual-passage--direction > .ritual-passage__label { color: #747063; margin-bottom: 10px; font-size: 11px; }
    .article-prose .ritual-passage--song { background: #f0f2ea; border-color: #d1d8c7; border-left-color: #65765d; }
    .article-prose .ritual-passage--song > .ritual-passage__label { color: #536249; }
    .article-prose .ritual-passage--oath { background: #f4ece3; border-color: #d5bca0; border-left-color: #8b5936; }
    .article-prose .ritual-passage img { display: block; }
    .prayer-audio { margin-top: 40px; font-family: var(--rv-font-ui); }
    .prayer-audio h2 { font-family: var(--rv-font-display); font-size: 28px; }
    .prayer-audio__track { margin-top: 24px; }
    .prayer-audio__track h3 { font-family: var(--rv-font-ui); font-size: 18px; margin-bottom: 12px; }
    .prayer-audio audio { display: block; width: 100%; max-width: 540px; }
    @media (max-width: 600px) {
        .article-prose .ritual-passage { margin-block: 24px; padding: 20px 16px; }
        .article-prose .ritual-passage--direction { padding: 14px 16px; }
    }
    @media (max-width: 1000px) { .ritual-guides { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .ritual-guides, .ritual-videos { grid-template-columns: 1fr; } .rituals-section { margin-top: 36px; } .ritual-guide { padding: 20px; } }
</style>
