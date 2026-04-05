<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment <?= htmlspecialchars(ucfirst($type)) ?> — ArCa Gateway</title>
<style>
  :root {
    --bg:#0f1117; --surface:#1a1d27; --border:#2e3248;
    --green:#22c55e; --red:#ef4444; --yellow:#eab308; --blue:#4f6ef7;
    --text:#e2e8f0; --text2:#94a3b8;
  }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
         min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .card { background:var(--surface); border:1px solid var(--border); border-radius:16px;
          padding:48px 40px; max-width:440px; width:100%; text-align:center; }
  .icon { font-size:56px; margin-bottom:20px; }
  h1 { font-size:22px; font-weight:700; margin-bottom:10px; }
  p  { color:var(--text2); font-size:15px; margin-bottom:24px; line-height:1.6; }
  .btn { display:inline-block; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:600;
         text-decoration:none; transition:opacity .15s; }
  .btn:hover { opacity:.85; }
  .btn-green  { background:var(--green); color:#fff; }
  .btn-red    { background:var(--red);   color:#fff; }
  .btn-blue   { background:var(--blue);  color:#fff; }
  .countdown  { font-size:12px; color:var(--text2); margin-top:14px; }
</style>
</head>
<body>
<div class="card">
  <?php if ($type === 'success'): ?>
    <div class="icon">✅</div>
    <h1>Payment Successful</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($statusUrl): ?>
      <a href="<?= htmlspecialchars($statusUrl) ?>" class="btn btn-green">View Order</a>
      <div class="countdown" id="cd">Redirecting in <span id="sec">8</span>s...</div>
    <?php endif; ?>

  <?php elseif ($type === 'declined'): ?>
    <div class="icon">❌</div>
    <h1>Payment Declined</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($statusUrl): ?>
      <a href="<?= htmlspecialchars($statusUrl) ?>" class="btn btn-red">Back to Store</a>
    <?php endif; ?>

  <?php elseif ($type === 'pending'): ?>
    <div class="icon">⏳</div>
    <h1>Payment Pending</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($statusUrl): ?>
      <a href="<?= htmlspecialchars($statusUrl) ?>" class="btn btn-blue">Check Order</a>
    <?php endif; ?>

  <?php else: ?>
    <div class="icon">⚠️</div>
    <h1>Something Went Wrong</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($statusUrl): ?>
      <a href="<?= htmlspecialchars($statusUrl) ?>" class="btn btn-blue">Back to Store</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($type === 'success' && $statusUrl): ?>
<script>
  let s = 8;
  const el = document.getElementById('sec');
  const iv = setInterval(() => {
    el.textContent = --s;
    if (s <= 0) { clearInterval(iv); location.href = <?= json_encode($statusUrl) ?>; }
  }, 1000);
</script>
<?php endif; ?>
</body>
</html>
