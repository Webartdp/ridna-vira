<style>
    .community-directory {padding:56px 0 80px;color:#24170e}
    .community-directory h1,.community-profile h1 {font-family:var(--rv-font-display);font-size:clamp(28px,4vw,48px);line-height:1.2;overflow-wrap:anywhere}
    .community-directory__intro {max-width:760px;margin:0 0 36px;line-height:1.65}
    .community-directory__grid {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
    .community-summary {display:flex;flex-direction:column;border:1px solid #c9b994;border-radius:16px;overflow:hidden;background:#f6f1e6;box-shadow:0 8px 24px #24170e0a}
    .community-summary__image {display:block;aspect-ratio:16/9;overflow:hidden}
    .community-summary img {width:100%;height:100%;object-fit:cover}
    .community-summary__body {padding:24px;display:flex;flex:1;flex-direction:column;gap:12px;line-height:1.55}
    .community-summary h2 {font-family:var(--rv-font-display);font-size:22px;line-height:1.3;margin:0;overflow-wrap:anywhere}
    .community-summary p {margin:0}
    .community-summary__body>a {margin-top:auto;text-underline-offset:4px}
    .community-profile-location {font-size:14px;color:#66523b}
    .community-profile {padding:48px 0 80px;color:#24170e}
    .community-profile__grid {display:grid;grid-template-columns:minmax(0,2fr) minmax(240px,1fr);gap:36px;align-items:start;margin-top:32px}
    .community-profile__photo {margin:0 0 28px}
    .community-profile__photo img {width:100%;height:auto;max-height:600px;object-fit:cover;border-radius:16px}
    .community-profile__body {font-size:18px;line-height:1.75;overflow-wrap:anywhere}
    .community-profile__body img {max-width:100%;height:auto}
    .community-profile__body h2,.community-profile__body h3 {font-family:var(--rv-font-display);margin:1.5em 0 .6em}
    .community-profile__contacts {padding:28px;background:#f6f1e6;border:1px solid #c9b994;border-radius:16px;overflow-wrap:anywhere;line-height:1.65}
    .community-profile__contacts h2 {font-family:var(--rv-font-display);font-size:22px}
    .community-profile__contacts a {display:block;margin-top:10px;text-underline-offset:4px}
    .community-profile__nav {display:flex;flex-wrap:wrap;gap:20px;margin:0 0 32px}
    @media(max-width:950px){.community-directory__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:640px){.community-directory__grid,.community-profile__grid{grid-template-columns:1fr}.community-profile,.community-directory{padding:32px 0 48px}.community-profile__grid{gap:24px}.community-profile__body{font-size:16px}}
</style>
