<?php
$type = $data['type'];
$title = $blog->GetMetadata('title', TRUE);

if ($type === 'index') {
    $category = $blog->GetCategoryName();
    $name = $blog->EscapeHTML($category ?: '首頁');
    $pagenum = $data['pagenum'];
    if ($pagenum < 2) {
        echo "<title>{$name} - {$title}</title>";
    } else {
        echo "<title>{$name}（第{$pagenum}頁） - {$title}</title>";
    }
} else {
    $slug = $data['slug'];
    $event = $blog->GetEventBySlug($slug, 'title');
    echo "<title>{$event['title']} - {$title}</title>";
}
?>

