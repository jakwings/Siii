<?php
$debug = $blog->GetMetadata('debug');
?>
<div class="article" role="document">
  <?php $blog->Load('article/content', $data) ?>
  <?php $blog->Load('article/pager', $data) ?>
  <?php !$debug and $blog->Load('article/comments', $data) ?>
</div>
