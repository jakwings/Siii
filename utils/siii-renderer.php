<?php
class Renderer
{
    private $mDB = NULL;
    private $mMetadata = NULL;
    private $mDirArticles = NULL;
    private $mDirTemplates = NULL;
    private $mDbTableName = 'events';

    private $mArticlePerPage = 10;

    public function __construct($config)
    {
        $this->mDB = $config['database'];
        $this->mMetadata = $config['metadata'];
        $this->mDirArticles = $config['dir_articles'];
        $this->mDirTemplates = $config['dir_templates'];
        $this->mDbTableName = $config['db_tablename'];
    }

    public function Render()
    {
        $slug = $this->GetSlug();
        $type = ($slug === '') ? 'index' : 'article';
        $category = $this->GetCategoryName();
        $pagenum = $this->GetPageNumber();
        $pagemax = $this->CountPages($category);
        if ($pagenum < 1
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
        return $_GET['category'];
    }

    public function GetTimelineNames($hidden = FALSE)
    {
        return $this->_SelectEvents(array(
            'action' => 'UNI',
            'column' => 'timeline',
            'order' => array('timeline' => SORT_ASC),
            'where' => function ($event) use ($hidden) {
                return $event['hidden'] == $hidden;
            }
        ));
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
        return file_get_contents($path);
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
        require_once 'class-parsedown.php';
        $parser = new Parsedown();
        return $parser->text($source);
    }

    public function GetEventBySlug($slug = '', $column = NULL)
    {
        $events = $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) use ($slug) {
                return $event['slug'] === $slug;
            }
        ));
        return $events[0];
    }

    public function GetAllEvents($column = NULL)
    {
        return $this->_SelectEvents(array(
            'column' => $column,
            'where' => function ($event) {
                return !$event['hidden'];
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
                return !$event['hidden']
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
                return !$event['hidden']
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
                return ($timeline === NULL ? !$event['hidden']
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
                return ($timeline === NULL ? !$event['hidden']
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
        $max = $this->mArticlePerPage;
        $offset = ($pagenum - 1) * $max;
        if ($timeline === NULL) {
            $events = $this->GetAllEvents($column);
        } else {
            $events = $this->GetEventsByTimeline($timeline, $column);
        }
        return array_slice($events, $offset, $max);
    }

    public function CountPages($timeline = NULL)
    {
        $number = $this->_CountEvents(function ($event) use ($timeline) {
            if ($timeline !== NULL) {
                return $event['timeline'] === $timeline;
            }
            return !$event['hidden'];
        });
        return intval(ceil($number / $this->mArticlePerPage));
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
