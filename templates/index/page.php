<div class="timeline">
  <div class="events">
<?php
$pagenum = $data['pagenum'];
$category = $blog->GetCategoryName();
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$events = $blog->GetEventsByPage($pagenum, NULL, $category);

foreach ($events as $event) {
    $date = strftime('%Y-%m-%d', $event['time']);
    $title = $blog->EscapeHtml($event['title']);
    $slug = $blog->EscapeHtml($blog->EncodeUrl($event['slug'], TRUE));
    $slug = $slug ? "{$root}/{$slug}.html" : '#';
    $timeline = $blog->EscapeHtml($event['timeline']);
    $description = $blog->ParseMarkup($event['content']);
    echo <<<"EOT"
<div class="event" timeline="{$timeline}">
  <div class="title">
    <a href="{$slug}"><span class="date">{$date}</span> <span class="text">{$title}</span></a>
  </div>
  <div class="description">{$description}</div>
</div>
EOT;
}
?>
  </div>
  <?php $blog->Load('index/pager', $data) ?>
</div>

