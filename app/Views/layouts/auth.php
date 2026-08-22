<?php /* Minimal centered layout for login / error pages. */ ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f2557">
    <title><?= e($title ?? 'Shoe Bank') ?> · <?= e(config('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: {
        brand: { 50:'#eef2fb',400:'#3b5ba9',500:'#24417f',600:'#0f2557',700:'#0b1c44' }
      } } } };
    </script>
</head>
<body class="h-full bg-gradient-to-b from-brand-600 to-brand-700 text-slate-800">
  <div class="min-h-full flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
      <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
      <?= $content ?>
    </div>
  </div>
</body>
</html>
