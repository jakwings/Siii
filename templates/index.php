<?php
$type = $data['type'];
$category = $blog->GetCategoryName();
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$title = $blog->GetMetadata('title', TRUE);
?>
<!DOCTYPE html>
<html lang="zh-tw" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1">

    <?php $blog->Load('head-title', $data) ?>

<?php
echo <<<"EOT"
    <link rel="alternate" href="{$root}/feed.xml" title="{$title}" type="application/rss+xml">
    <link rel="icon" size="48x48" href="{$root}/favicon.ico">
    <link rel="icon" size="240x240" type="image/png" href="{$root}/favicon240.png">
    <link rel="icon" size="any" type="image/svg+xml" href="{$root}/favicon.svg">
    <link rel="stylesheet" href="{$root}/data/css/normalize.css">
    <link rel="stylesheet" href="{$root}/data/css/style.css">
    <script>
      // For my lovely zip.svg.
      (function (win) {
        if (!/chrome/i.test(win.navigator.userAgent)) {
          return;
        }
        win.addEventListener('load', function () {
          var doc = win.document;
          var style = doc.createElement('style');
          doc.body.appendChild(style);
          style.textContent = '.blog>.main>.wrapper:before{background:none;content:url({$root}/data/messy/site/images/zip.svg);}';
        }, false);
      })(this);
    </script>
EOT;
?>

  </head>

  <body>
    <div class="blog">
      <?php $blog->Load('body-header') ?>
      <div class="main" role="main">
        <div class="wrapper">
          <?php ($type === 'index') and $blog->Load('index/page', $data) ?>
          <?php ($type === 'article') and $blog->Load('article/main', $data) ?>
        </div>
      </div>
      <?php $blog->Load('body-footer') ?>
    </div>
  </body>
</html>
