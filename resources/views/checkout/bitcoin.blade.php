<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitcoin Checkout | Kosher Market</title>
    <style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7fb;margin:0;color:#111827}.wrap{max-width:820px;margin:40px auto;padding:24px}.card{background:#fff;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08)}.btc{font-size:14px;font-weight:700;letter-spacing:.04em}.notice{background:#fff7ed;padding:14px;border-radius:10px;margin:18px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:flex;flex-direction:column;gap:6px}.field.full{grid-column:1/-1}.field input,.field select{padding:12px;border:1px solid #d1d5db;border-radius:8px;font:inherit}.qty{width:100px;padding:12px;border:1px solid #d1d5db;border-radius:8px}.row{display:flex;gap:12px;align-items:center}.button{border:0;border-radius:9px;padding:13px 18px;background:#111827;color:white;font-weight:700;cursor:pointer}.price{font-size:28px;font-weight:800;margin:8px 0 24px}.muted{color:#6b7280}.btcmark{font-size:42px}.summary{background:#f8fafc;border-radius:10px;padding:16px;margin:18px 0}@media(max-width:650px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}}</style>
</head>
<body><div class="wrap"><div class="card">
    <div class="btc">BITCOIN ONLY • GLOBAL MARKETPLACE</div><div class="btcmark">₿</div>
    <h1>{{ $product->getTranslation('name') ?: $product->name }}</h1>
    <p class="muted">Your Bitcoin payment is held in escrow until the transaction is completed.</p>
    <div class="price"><span id="total">{{ number_format((float)$product->checkout_price, 8) }}</span> BTC</div>
    <form method="POST" action="{{ route('checkout.process') }}" id="checkout-form">
        @csrf<input type="hidden" name="product_id" value="{{ $product->id }}">
        <div class="grid">
            <div class="field"><label>Quantity</label><input class="qty" id="quantity" type="number" name="quantity" min="1" max="100" value="1" required></div>
            <div class="field"><label>Destination country</label><select name="shipping_country" id="country" required><option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->code }}">{{ $country->name }}</option>@endforeach</select></div>
            <div class="field full"><label>Shipping method</label><select name="shipping_rate_id" id="shipping_rate" required><option value="">Select country first</option></select></div>
            <div class="field full"><label>Full name</label><input name="shipping_full_name" maxlength="160" required></div>
            <div class="field full"><label>Address line 1</label><input name="shipping_address_line1" maxlength="255" required></div>
            <div class="field full"><label>Address line 2 <span class="muted">(optional)</span></label><input name="shipping_address_line2" maxlength="255"></div>
            <div class="field"><label>City</label><input name="shipping_city" maxlength="120" required></div>
            <div class="field"><label>State / Province</label><input name="shipping_state" maxlength="120"></div>
            <div class="field"><label>Postal code</label><input name="shipping_postal_code" maxlength="40" required></div>
        </div>
        <div class="summary" id="shipping-summary">Select your destination to see available shipping methods and delivery estimates.</div>
        <button class="button" type="submit">Continue to Bitcoin Payment</button>
    </form>
    @if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
    <div class="notice"><strong>Escrow protection:</strong> Bitcoin is not released to the seller immediately. You can confirm receipt or open a dispute.</div>
</div></div>
<script>
const country=document.getElementById('country');const qty=document.getElementById('quantity');const rate=document.getElementById('shipping_rate');const summary=document.getElementById('shipping-summary');const total=document.getElementById('total');const base={{ (float)$product->checkout_price }};
async function loadShipping(){if(!country.value)return;rate.innerHTML='<option>Loading…</option>';try{const url='{{ route('checkout.shipping-options') }}?product_id={{ $product->id }}&country='+encodeURIComponent(country.value)+'&quantity='+encodeURIComponent(qty.value);const r=await fetch(url,{headers:{'Accept':'application/json'}});const data=await r.json();if(!r.ok)throw new Error(data.message||'No shipping options');rate.innerHTML='';if(!data.options?.length){rate.innerHTML='<option value="">No shipping available</option>';summary.textContent='This vendor does not currently ship this product to the selected country.';return;}data.options.forEach(o=>{const el=document.createElement('option');el.value=o.id;el.dataset.price=o.price_btc;el.dataset.min=o.min_delivery_days;el.dataset.max=o.max_delivery_days;el.textContent=`${o.service_name} — ${Number(o.price_btc).toFixed(8)} BTC (${o.min_delivery_days}-${o.max_delivery_days} days)`;rate.appendChild(el)});updateTotal();}catch(e){rate.innerHTML='<option value="">Unable to load shipping</option>';summary.textContent=e.message;}}
function updateTotal(){const o=rate.options[rate.selectedIndex];const shipping=Number(o?.dataset.price||0);total.textContent=(base*Number(qty.value)+shipping).toFixed(8);if(o?.value)summary.textContent=`Shipping: ${shipping.toFixed(8)} BTC • Estimated delivery: ${o.dataset.min}-${o.dataset.max} days.`;}
country.addEventListener('change',loadShipping);qty.addEventListener('change',loadShipping);rate.addEventListener('change',updateTotal);
</script></body></html>
