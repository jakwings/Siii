<?php
$type = $data['type'];
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$title = $blog->GetMetadata('title', TRUE);
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1">
    <?php $blog->Load('head-title', $data) ?>

<?php echo <<<"EOT"
    <link rel="alternate" href="{$root}/feed.xml" title="{$title}" type="application/rss+xml">
    <link rel="shortcut icon" type="image/x-icon" href="{$root}/favicon.ico">
    <link rel="stylesheet" href="{$root}/data/css/normalize.css">
    <link rel="stylesheet" href="{$root}/data/css/style.css">
EOT;
?>

  </head>

  <body>
    <div class="blog">
      <div class="header">
        <?php $blog->Load('body-header') ?>
      </div>

      <?php $blog->Load('body-navigator', $data) ?>

      <div class="body" role="main">
        <?php ($type === 'index') and $blog->Load('index/page', $data) ?>
        <?php ($type === 'article') and $blog->Load('article/main', $data) ?>
      </div>
      <div class="footer">
        <?php $blog->Load('body-footer') ?>
      </div>
    </div>

<?php echo <<<"EOT"
    <!--
      - <script src="{$root}/data/js/jquery.js"></script>
      - <script src="{$root}/data/js/miscellaneous.js"></script>
      -->
EOT;
?>

  </body>
</html>
