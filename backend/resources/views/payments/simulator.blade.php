<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>محاكاة الدفع — وديان</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --ink:#1a1714; --canvas:#faf8f5; --line:#e8e5e0; --gilt:#b08d57; --ok:#4a6b52; --no:#9b4a42; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100dvh; display:grid; place-items:center; background:var(--canvas);
           font-family:'IBM Plex Sans Arabic',system-ui,sans-serif; color:var(--ink); padding:1.5rem; }
    .card { width:100%; max-width:32rem; background:#fff; border:1px solid var(--line);
            box-shadow:0 12px 32px -16px rgb(26 23 20 / .18); padding:2.5rem; }
    .tag { display:inline-block; font-size:.7rem; letter-spacing:.2em; color:var(--gilt); margin-bottom:1rem; }
    h1 { margin:0 0 .5rem; font-size:1.35rem; }
    p.lead { margin:0 0 2rem; color:#6b625a; font-size:.9rem; line-height:1.7; }
    dl { display:grid; grid-template-columns:auto 1fr; gap:.75rem 1rem; margin:0 0 2rem;
         padding:1.25rem; background:var(--canvas); font-size:.9rem; }
    dt { color:#8a8177; } dd { margin:0; font-weight:500; font-variant-numeric:tabular-nums; }
    .actions { display:grid; gap:.75rem; }
    button { font:inherit; font-weight:600; padding:.9rem 1rem; border:0; cursor:pointer; color:#fff; }
    .ok { background:var(--ok); } .no { background:var(--no); }
    button:hover { opacity:.9; }
    .note { margin-top:1.5rem; font-size:.75rem; color:#8a8177; line-height:1.7; text-align:center; }
  </style>
</head>
<body>
  <div class="card">
    <span class="tag">م ح ا ك ا ة   ا ل د ف ع</span>
    <h1>صفحة دفع تجريبية</h1>
    <p class="lead">
      هذه ليست بوابة دفع حقيقية. تظهر لأن <code>PAYMENT_GATEWAY=fake</code>،
      وتتيح تجربة مسار الشراء والحجز كاملاً دون حساب لدى أي مزوّد.
    </p>

    <dl>
      <dt>رقم العملية</dt><dd>{{ $payment->payment_number }}</dd>
      <dt>المبلغ</dt><dd>{{ \App\Support\Money::of($payment->amount, $payment->currency)->formatted() }}</dd>
      <dt>النوع</dt><dd>{{ $payment->kind->label() }}</dd>
    </dl>

    <div class="actions">
      <form method="POST" action="{{ route('payments.simulate', $payment->payment_number) }}">
        @csrf
        <input type="hidden" name="success" value="1">
        <input type="hidden" name="return_url" value="{{ $returnUrl }}">
        <button class="ok" type="submit" style="width:100%">إتمام الدفع بنجاح</button>
      </form>

      <form method="POST" action="{{ route('payments.simulate', $payment->payment_number) }}">
        @csrf
        <input type="hidden" name="success" value="0">
        <input type="hidden" name="return_url" value="{{ $returnUrl }}">
        <button class="no" type="submit" style="width:100%">محاكاة فشل الدفع</button>
      </form>
    </div>

    <p class="note">
      كلا الزرّين يمرّان بنفس مسار الخطّاف الذي يسلكه مزوّد حقيقي،<br>
      فما تختبره هنا هو المنطق نفسه لا اختصاراً له.
    </p>
  </div>
</body>
</html>
