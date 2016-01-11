<?php
class Renderer
{
    private $mDB = NULL;
    private $mMetadata = NULL;
    private $mTimelines = NULL;
    private $mDirArticles = NULL;
    private $mDirTemplates = NULL;
    private $mDbTableName = 'events';

    private $mArticlePerPage = 1;
    private $mArticlePerCategory = 10;

    public function __construct($config)
    {
        $this->mDB = $config['database'];
        $this->mMetadata = $config['metadata'];
        $this->mTimelines = $config['timelines'];
        $this->mDirArticles = $config['dir_articles'];
        $this->mDirTemplates = $config['dir_templates'];
        $this->mDbTableName = $config['db_tablename'];
    }

    public function Render()
    {
        $slug = $this->GetSlug();
        $type = ($slug === '') ? 'index' : 'article';
        $category_slug = $this->GetCategorySlug();
        $category = $this->GetCategory();
        $pagenum = $this->GetPageNumber();
        $pagemax = $this->CountPages($category ? $category['name'] : FALSE);
        if (($category_slug !== FALSE and !$category)
            or $category['mode'] === 'hidden'
            or $pagenum < 1
            or $pagenum > $pagemax
            or ($type === 'article' and !$this->HasEvent($slug)))
        {
            header('Content-Type: text/plain; charset="utf-8"');
            $this->Load('errors', array(
                'status' => 404
            ));
            return FALSE;  // cache not ready
        } else {
            header('Content-Type: text/html; charset="utf-8"');
            $this->Load('index', array(
                'slug' => $slug,
                'type' => $type,
                'pagenum' => $pagenum,
                'pagemax' => $pagemax
            ));
        }
        return TRUE;  // cache ready
    }

    public function Load($template, $data = NULL)
    {
        $blog = $this;
        include $this->GetSecurePath($blog->mDirTemplates . $template . '.php');
    }

    public function GetSecurePath($path)
    {
        if (preg_match('/\/\?\.\?\./', '/' . $path)) {
            throw new Exception('Invalid path.');
        }
        return $path;
    }

    public function GetMetadata($key, $escape = FALSE)
    {
        $metadata = $this->mMetadata;
        $key = strval($key);
        if ($key !== '') {
            $val = $metadata[$key];
            return $escape ? $this->EscapeHtml(strval($val)) : $val;
        }
        return $metadata;
    }

    public function GetSlug()
    {
        return strval($_GET['view']);
    }

    public function GetPageNumber()
    {
        $pn = intval($_GET['page']);
        return ($pn > 0) ? $pn : 1;
    }

    public function GetCategoryName()
    {
        $slug = $this->GetCategorySlug();
        return $this->GetCategoryNameBySlug($slug);
    }

    public function GetCategorySlug()
    {
        if (array_key_exists('category', $_GET)) {
            return $_GET['category'];
        }
        return FALSE;
    }

    public function GetCategory()
    {
        $slug = $this->GetCategorySlug();
        return $this->GetCategoryBySlug($slug);
    }

    public function GetCategoryByName($name)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['name'] === $name) {
                return $timeline;
            }
        }
        return FALSE;
    }

    public function GetCategoryBySlug($slug)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['slug'] === $slug) {
                return $timeline;
            }
        }
        return FALSE;
    }

    public function GetCategoryNameBySlug($slug)
    {
        return $this->GetTimelineNameBySlug($slug);
    }

    public function GetCategorySlugByName($name)
    {
        return $this->GetTimelineSlugByName($name);
    }

    public function GetTimelineNames($mode = '')
    {
        $names = array();
        foreach ($this->mTimelines as $timeline) {
            if ($mode === TRUE or $timeline['mode'] === $mode) {
                $names[] = $timeline['name'];
            }
        }
        return $names;
    }
    public function GetTimelineSlugs($mode = '')
    {
        $slugs = array();
        foreach ($this->mTimelines as $timeline) {
            if ($mode === TRUE or $timeline['mode'] === $mode) {
                $slugs[] = $timeline['slug'];
            }
        }
        return $slugs;
    }
    public function GetTimelineSlugByName($name)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['name'] === $name) {
                return $timeline['slug'];
            }
        }
        return FALSE;
    }
    public function GetTimelineNameBySlug($slug)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['slug'] === $slug) {
                return $timeline['name'];
            }
        }
        return FALSE;
    }
    public function GetTimelineModeByName($name)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['name'] === $name) {
                return $timeline['mode'];
            }
        }
        return FALSE;
    }
    public function GetTimelineModeBySlug($slug)
    {
        foreach ($this->mTimelines as $timeline) {
            if ($timeline['slug'] === $slug) {
                return $timeline['mode'];
            }
        }
        return FALSE;
    }

    public function HasEvent($slug)
    {
        return $this->_CountEvents(function ($event) use ($slug) {
            return $event['slug'] === $slug;
        }) > 0;
    }

    public function GetEventContent($slug)
    {
        $path = $this->GetSecurePath($this->mDirArticles . $slug . '.md');
        $content = file_get_contents($path);
        $event = $this->GetEventBySlug($slug, 'has_data', function ($event) use ($slug) {
            return $event['slug'] === $slug;
        });
        if ($event['has_data']) {
            $content = preg_replace('/\A.*?^%$/ms', '', $content);
        }
        return $content;
    }

    public function EscapeHtml($text, $flag = ENT_QUOTES)
    {
        return htmlentities($text, $flag, 'UTF-8');
    }

    public function EncodeUrl($url, $keepSlashes = FALSE)
    {
        if (!$keepSlashes) {
            return rawurlencode($url);
        }
        return implode('/', array_map('rawurlencode', explode('/', $url)));
    }

    public function ParseMarkup($source)
    {
        require_once 'siii-markdown.php';
        $parser = new Markdown($this->GetMetadata('path'));
        $parser->setBreaksEnabled(TRUE);
        return $parser->parse($source);
    }

    public function GetEventBySlug($slug = '', $column = NULL, $where = NULL)
    {
        $events = $this->_SelectEvents(array(
            'column' => $column,
            'where' => $where ?: function ($event) use ($slug) {
                return $event['mode'] !== 'archive' and $event['slug'] === $slug;
            }
        ));
        return $events[0];
    }

    public function GetAllEvents($column = NULL)
    {
        return $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) {
                return $event['mode'] === '';
            }
        ));
    }

    public function GetEventsByTimeline($timeline, $column = NULL)
    {
        return $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($timeline) {
                return $event['timeline'] === $timeline;
            }
        ));
    }

    public function GetEventsByTime($timestamp, $column = NULL)
    {
        $date = getdate($timestamp);
        return $this->GetEventsByYearMonth($date['year'], $date['mon'], $column);
    }

    public function GetEventsBySlug($slug, $column = NULL)
    {
        $event = $this->GetEventBySlug($slug);
        return empty($event) ?
                 array() : $this->GetEventsByTime($event['time'], $column);
    }

    public function GetEventsByYear($year, $column = NULL)
    {
        $start = strtotime(strval($year) . '-1-1');
        $end = strtotime(strval($year + 1) . '-1-1');
        return $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($start, $end) {
                return $event['mode'] === ''
                    and $event['time'] >= $start
                    and $event['time'] < $end;
            }
        ));
    }

    public function GetEventsByYearMonth($year, $month, $column = NULL)
    {
        $start = strtotime(strval($year) . '-' . strval($month) . '-1');
        $end = strtotime(strval($year) . '-' . strval($month + 1) . '-1');
        return $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($start, $end) {
                return $event['mode'] === ''
                    and $event['time'] >= $start
                    and $event['time'] < $end;
            }
        ));
    }

    public function GetPrevEventBySlug($slug, $column = NULL, $timeline = NULL)
    {
        $event = $this->GetEventBySlug($slug);
        $time = $event ? $event['time'] : NULL;
        if ($time === NULL) {
            return NULL;
        }
        $events = $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($slug, $time, $timeline) {
                return (empty($timeline) ? $event['mode'] === ''
                                         : $event['timeline'] === $timeline)
                    and $event['time'] <= $time
                    and $event['slug'] !== ''
                    and $event['slug'] !== $slug;
            }
        ));
        return $events[0];  // gives the biggest one
    }

    public function GetNextEventBySlug($slug, $column = NULL, $timeline = NULL)
    {
        $event = $this->GetEventBySlug($slug);
        $time = $event ? $event['time'] : NULL;
        if ($time === NULL) {
            return NULL;
        }
        $events = $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($slug, $time, $timeline) {
                return (empty($timeline) ? $event['mode'] === ''
                                         : $event['timeline'] === $timeline)
                    and $event['time'] >= $time
                    and $event['slug'] !== ''
                    and $event['slug'] !== $slug;
            }
        ));
        return array_pop($events);  // gives the smallest one
    }

    public function GetEventsByPage($pagenum, $column = NULL, $timeline = NULL)
    {
        $max = empty($timeline) ? $this->mArticlePerPage : $this->mArticlePerCategory;
        $offset = ($pagenum - 1) * $max;
        if (empty($timeline)) {
            $events = $this->GetAllEvents($column);
        } else {
            $events = $this->GetEventsByTimeline($timeline, $column);
        }
        return array_slice($events, $offset, $max);
    }

    public function CountPages($timeline = NULL)
    {
        $number = $this->_CountEvents(function ($event) use ($timeline) {
            if (!empty($timeline)) {
                return $event['timeline'] === $timeline;
            }
            return $event['mode'] === '';
        });
        $number = ($number > 0) ? $number : 1;
        if (empty($timeline)) {
            return intval(ceil($number / $this->mArticlePerPage));
        }
        return intval(ceil($number / $this->mArticlePerCategory));
    }

    private function _SelectEvents($select = NULL)
    {
        return $this->mDB->Select($this->mDbTableName, $select, TRUE);
    }
    private function _CountEvents($where = NULL)
    {
        return $this->mDB->Count($this->mDbTableName, $where, TRUE);
    }
    private function _GetUniqueEvents($header = NULL)
    {
        return $this->mDB->Unique($this->mDbTableName, $header, TRUE);
    }
    private function _GetNewestEvent($header = NULL)
    {
        return $this->mDB->Max($this->mDbTableName, $header, TRUE);
    }
    private function _GetOldestEvent($header = NULL)
    {
        return $this->mDB->Min($this->mDbTableName, $header, TRUE);
    }
}
?>
