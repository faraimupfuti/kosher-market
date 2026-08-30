<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitcoin Checkout | Velstore</title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7fb;margin:0;color:#111827}.wrap{max-width:760px;margin:50px auto;padding:24px}.card{background:#fff;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08)}.btc{font-size:14px;font-weight:700;letter-spacing:.04em}.notice{background:#fff7ed;padding:14px;border-radius:10px;margin:18px 0}.row{display:flex;gap:12px;align-items:center}.qty{width:100px;padding:12px;border:1px solid #d1d5db;border-radius:8px}button{border:0;border-radius:9px;padding:13px 18px;background:#111827;color:white;font-weight:700;cursor:pointer}.price{font-size:28px;font-weight:800;margin:8px 0 24px}.muted{color:#6b7280}.btcmark{font-size:42px}
    </style>
</head>
<body>
<div class="wrap"><div class="card">
    <div class="btc">BITCOIN ONLY MARKETPLACE</div>
    <div class="btcmark">₿</div>
    <h1>{{ $product->name }}</h1>
    <p class="muted">Your payment will be held in Velstore escrow until the transaction is completed.</p>
    <div class="price">{{ number_format((float)$product->sale_price, 8) }} BTC</div>
    <form method="POST" action="{{ route('checkout.process') }}">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <div class="row">
            <input class="qty" type="number" name="quantity" min="1" max="100" value="1" required>
            <button type="submit">Continue to Bitcoin Payment</button>
        </div>
    </form>
    @if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
    <div class="notice"><strong>Escrow protection:</strong> Bitcoin is not released to the seller immediately. You can confirm receipt or open a dispute.</div>
</div></div>
</body>
</html>
