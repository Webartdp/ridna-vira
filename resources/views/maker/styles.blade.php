<style>
.maker-product{display:grid;grid-template-columns:64px minmax(0,1fr);gap:10px 12px;padding:14px 0;border-bottom:1px solid #d7cab3}
.maker-product-info{min-width:0;overflow-wrap:anywhere}
.maker-product h3{font:700 1rem/1.35 var(--rv-font-ui)!important;margin:0 0 5px}
.maker-product small{line-height:1.5}
.maker-thumb,.maker-photos img{border-radius:8px;object-fit:cover;background:#eee5d3}
.maker-actions{grid-column:2;display:flex;gap:6px;flex-wrap:wrap}
.maker-actions form{margin:0}
.maker-action{display:inline-grid;place-items:center;width:44px;height:44px;border:1px solid #d7cab3;border-radius:8px;background:#fffdf6;color:#493a23;cursor:pointer}
.maker-action:hover,.maker-action:focus-visible{background:#eee5d3;outline-color:#493a23}
.maker-action-danger{color:#9f2222}
.maker-icon{width:20px;height:20px}
.maker-photos{margin-top:18px}
.maker-photos label{display:flex;gap:10px;align-items:center}
.maker-photos img{flex:none}
</style>
