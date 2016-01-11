<div class="timeline">
<?php
$pagenum = $data['pagenum'];
$category = $blog->GetCategoryName();
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$mode = $blog->GetTimelineModeByName($category);
if ($mode !== 'archive') {
    $events = $blog->GetEventsByPage($pagenum, NULL, $category);
} else {
    $events = $blog->GetEventsByTimeline($category);
}

$category_slug = $blog->GetCategorySlug();
echo <<<"EOT"
    <div class="events" timeline="{$category_slug}">
EOT;

foreach ($events as $event) {
    $date = strftime('%Y-%m-%d', $event['time']);
    $title = $blog->EscapeHtml($event['title']);
    $slug = $blog->EscapeHtml($blog->EncodeUrl($event['slug'], TRUE));
    $link = $slug ? " href=\"{$root}/{$slug}.html\"" : '';
    $extra_class = $slug ? '' : ' whisper';
    $timeline = $blog->EscapeHtml($blog->GetTimelineSlugByName($event['timeline']));
    $description = $blog->ParseMarkup($event['content']);
    echo <<<"EOT"
<div class="event{$extra_class}" timeline="{$timeline}">
  <div class="title">
    <a{$link}><span class="date">{$date}</span> <span class="text">{$title}</span></a>
  </div>
  <div class="description">{$description}</div>
</div>
EOT;
}

echo <<<"EOT"
    </div>
EOT;

if ($mode !== 'archive') {
    $blog->Load('index/pager', $data);
}
?>
</div>

