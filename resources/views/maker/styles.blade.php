<style>
.maker-inventory-heading{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.maker-inventory-heading h2{margin:0}
.maker-table-scroll{overflow-x:auto;border:1px solid #e0d4bf;border-radius:10px}
.maker-table{min-width:680px;background:#fffdf6}
.maker-table thead{background:#eee5d3;color:#493a23}
.maker-table tr > :is(th,td){padding:12px;vertical-align:middle}
.maker-table tbody tr:last-child > *{border-bottom:0}
.maker-table tbody tr:nth-child(even){background:#faf5ea}
.maker-product{display:flex;align-items:center;gap:12px;min-width:180px}
.maker-product-name{font:700 1rem/1.35 var(--rv-font-ui);overflow-wrap:anywhere}
.maker-prices small{white-space:nowrap;line-height:1.55}
.maker-status{display:inline-block;padding:4px 9px;border-radius:16px;white-space:nowrap;background:#eee5d3;color:#493a23}
.maker-status-live{background:#e1eddd;color:#264f32}
.maker-inventory-empty{margin:0;padding:26px 0;color:#756348}
.maker-dialog{position:fixed;inset:0;margin:auto;width:min(620px,calc(100% - 24px));max-height:calc(100dvh - 24px);overflow:auto;padding:0;border:1px solid #d7cab3;border-radius:14px;background:#fffdf6;color:#302a22;box-shadow:0 20px 60px #302a224d}
.maker-dialog::backdrop{background:#20160dcc}
.maker-dialog .shop-panel{border:0;padding-top:48px}
.maker-dialog-close{position:absolute;top:8px;right:8px}
.maker-dialog > .shop-alert{margin:55px 16px 0}
.maker-dialog > .shop-alert + .shop-panel{padding-top:16px}
.maker-thumb{flex:none}
.maker-thumb,.maker-photos img{border-radius:8px;object-fit:cover;background:#eee5d3}
.maker-actions{display:flex;gap:6px}
.maker-actions form{margin:0}
.maker-action{display:inline-grid;place-items:center;width:44px;height:44px;border:1px solid #d7cab3;border-radius:8px;background:#fffdf6;color:#493a23;cursor:pointer}
.maker-action:hover,.maker-action:focus-visible{background:#eee5d3;outline-color:#493a23}
.maker-action-danger{color:#9f2222}
.maker-icon{width:20px;height:20px}
.maker-photos{margin-top:18px}
.maker-photos label{display:flex;gap:10px;align-items:center}
.maker-photos img{flex:none}
</style>
