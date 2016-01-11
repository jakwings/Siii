<?php
class Siii
{
    private $mDirRoot = NULL;
    private $mDirCache = 'cache';
    private $mDirConfig = 'config';
    private $mDirArticles = 'files';
    private $mDirDatabase = 'database';
    private $mDirTemplates = 'templates';
    private $mMetadata = NULL;
    private $mTimelines = NULL;
    private $mIsTimelinesOk = FALSE;
    private $mSlug = '';
    private $mDB = NULL;
    private $mDbTableName = 'events';
    private $mCacheFile = NULL;
    private $mIsCacheReady = FALSE;
    private $mCacheEnabled = FALSE;
    private $mCacheLifetimeForFeed = 5400;  // 60 * 60 * 1.5 seconds
    private $mCacheLifetimeForPage = 43200;  // 60 * 60 * 12 seconds

    public function __construct()
    {
        $this->mDirRoot = rtrim(realpath(dirname(__FILE__) . '/../'), '/') . '/';
        $this->mDirCache = $this->_GetRealDir($this->mDirCache);
        $this->mDirConfig = $this->_GetRealDir($this->mDirConfig);
        $this->mDirArticles = $this->_GetRealDir($this->mDirArticles);
        $this->mDirDatabase = $this->_GetRealDir($this->mDirDatabase);
        $this->mDirTemplates = $this->_GetRealDir($this->mDirTemplates);
        $this->mSlug = strval($_GET['view']);

        mb_language('uni');
        mb_internal_encoding('UTF-8');
        mb_regex_encoding('UTF-8');
        mb_regex_set_options('pz');

        $uri = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
        $cache_id = md5($uri) . '.cache';
        $this->mCacheFile = $this->GetSecurePath($this->mDirCache . $cache_id);

        $this->_ReadConfigFile();
        $this->_CheckHtaccess();
        $this->_SetupTimelines(!$this->mCacheEnabled);
        $this->_UpdateFeed(!$this->mCacheEnabled);
        $this->_UpdateSitemap(!$this->mCacheEnabled);
    }

    public function __destruct()
    {
        $this->_DisconnectDatabase();
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

    public function Render()
    {
        require_once 'siii-renderer.php';
        $this->_ConnectDatabase();
        $blog = new Renderer(array(
            'database' => $this->mDB,
            'metadata' => $this->mMetadata,
            'timelines' => $this->mTimelines,
            'dir_articles' => $this->mDirArticles,
            'dir_templates' => $this->mDirTemplates,
            'db_tablename' => $this->mDbTableName
        ));
        if ($this->mCacheEnabled) {
            $this->FindCache($blog);
        } else {
            $this->mIsCacheReady = $blog->Render();
        }
    }

    public function GetEventContent($event)
    {
        $path = $this->GetSecurePath($this->mDirArticles . $event['slug'] . '.md');
        $content = file_get_contents($path);
        if ($event['has_data']) {
            $content = preg_replace('/\A.*?^%$/ms', '', $content);
        }
        return $content;
    }

    public function GetSecurePath($path)
    {
        if (preg_match('/\/\?\.\?\./', '/' . $path)) {
            throw new Exception('Invalid path.');
        }
        return $path;
    }

    public function ParseMarkup($source)
    {
        require_once 'siii-markdown.php';
        $parser = new Markdown($this->GetMetadata('path'));
        $parser->setBreaksEnabled(TRUE);
        return $parser->parse($source);
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

    public function FindCache($blog)
    {
        $cmd_file = $this->mDirRoot . 'cmd_clear_cache';
        if (file_exists($cmd_file)) {
            $filenames = glob($this->mDirCache . '*.cache', GLOB_NOSORT);
            ignore_user_abort(TRUE);
            foreach ($filenames as $filename) {
                unlink($filename);
            }
            unlink($cmd_file);
            $this->_ReadConfigFile();
            $this->_SetupTimelines(TRUE);
            $this->_UpdateFeed(TRUE);
            $this->_UpdateSitemap(TRUE);
            ignore_user_abort(FALSE);
        } else {
            if (file_exists($this->mCacheFile)) {
                if (time() - filemtime($this->mCacheFile) > $this->mCacheLifetimeForPage) {
                    ignore_user_abort(TRUE);
                    unlink($this->mCacheFile);
                    $this->_ReadConfigFile();
                    $this->_SetupTimelines(TRUE);
                    $this->_UpdateFeed(TRUE);
                    $this->_UpdateSitemap(TRUE);
                    ignore_user_abort(FALSE);
                } else {
                    ob_clean();
                    header('Content-Type: text/html; charset="utf-8"');
                    readfile($this->mCacheFile);
                    exit();
                }
            }
        }
        ob_clean();
        ob_start();
        $this->mIsCacheReady = $blog->Render();
        if ($this->mCacheEnabled and $this->mIsCacheReady) {
            $buffer = ob_get_flush();
            ignore_user_abort(TRUE);
            file_put_contents($this->mCacheFile, $buffer, LOCK_EX);
            ignore_user_abort(FALSE);
        }
    }

    private function _GetRealDir($dir)
    {
        $root = $this->mDirRoot;
        return rtrim(realpath($root . $dir . '/') ?: '/dev/null', '/') . '/';
    }

    private function _DisconnectDatabase()
    {
        if (isset($this->mDB) and $this->mDB->IsConnected()) {
            $this->mDB->Disconnect();
        }
    }

    private function _ConnectDatabase()
    {
        if (!isset($this->mDB)) {
            require_once 'class-todb.php';
            $this->mDB = new Todb();
            $this->mDB->Debug(TRUE);
        }
        if (!$this->mDB->IsConnected()) {
            $this->mDB->Connect($this->mDirDatabase);
        }
    }

    private function _GetArticleFrontmatter($slug)
    {
        $lines = array();
        $has_header = FALSE;
        $file = fopen($this->mDirArticles . $slug . '.md', 'r');
        if ($file) {
            while (false !== ($line = fgets($file))) {
                $line = rtrim($line, "\r\n");
                if ($line === '%') {
                    $has_header = TRUE;
                    break;
                }
                $lines[] = $line;
            }
            fclose($file);
        }
        if ($has_header) {
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $headers = mb_split('\s*//\s*', $line);
                    $lines[$index] = $headers[0] . '//' . $headers[1] . '//' . $slug . '//' . 'has_data';
                    break;
                }
            }
        }
        return $lines;
    }

    private function _ExtractHeadersFromLine($line)
    {
        $headers = array();
        $data = mb_split('\s*//\s*', trim($line));
        $headers['time'] = strtotime(strval($data[0])) ?: time();
        $headers['title'] = strval($data[1]);
        $headers['slug'] = strval($data[2]);
        $headers['has_data'] = !trim(strval($data[3]));  // tricky
        return $headers;
    }

    public function _ReadConfigFile()
    {
        require_once 'class-toml.php';
        $config = \Toml\Toml::parseFile($this->mDirConfig . 'config.toml');
        if (isset($config['cache']['enable'])) {
            $this->mCacheEnabled = !!$config['cache']['enable'];
        }
        if (isset($config['cache']['lifetime']['page'])) {
            $this->mCacheLifetimeForPage = $config['cache']['lifetime']['page'];
        }
        if (isset($config['cache']['lifetime']['feed'])) {
            $this->mCacheLifetimeForFeed = $config['cache']['lifetime']['feed'];
        }
        $this->mTimelines = $config['timelines'] ?: array();
        foreach ($this->mTimelines as $index => $timeline) {
            $this->mTimelines[$index]['mode'] = $timeline['mode'] ?: '';
            $this->mTimelines[$index]['slug'] = $timeline['slug'] ?: $timeline['name'];
            if (isset($timeline['file']) and !is_array($timeline['file'])) {
                $this->mTimelines[$index]['file'] = array($timeline['file']);
            }
            if (isset($timeline['dir']) and !is_array($timeline['dir'])) {
                $this->mTimelines[$index]['dir'] = array($timeline['dir']);
            }
        }
        $this->mIsTimelinesOk = FALSE;
        $this->mMetadata = $config['metadata'];
        date_default_timezone_set($this->mMetadata['timezone']);
    }

    public function _SetupTimelines($force = FALSE)
    {
        if ($this->mIsTimelinesOk) {
            return;
        }
        $this->_ConnectDatabase();
        $table_name = $this->mDbTableName;
        // Check if table exists.
        if ($this->mDB->ListTables($table_name)) {
            if (!$force) {
                $this->mIsTimelinesOk = TRUE;
                return;
            }
        } else {
            $this->mDB->CreateTable($table_name, array(
                'time', 'timeline', 'mode', 'has_data', 'title', 'slug', 'content'
            ));
        }
        // Collect events from timelines.
        $events = array();
        $timelines = $this->mTimelines;
        $timelines = array_filter($timelines, function ($timeline) {
            return !empty($timeline['name']);
        });
        $this->mTimelines = $timelines;
        foreach ($timelines as $timeline) {
            $lines = array();
            foreach ($timeline['file'] as $file) {
                $path = $this->mDirConfig . $file;
                if (is_file($path)) {
                    $lines = array_merge($lines, file($path, FILE_IGNORE_NEW_LINES));
                    $lines[] = '%';
                }
            }
            foreach ($timeline['dir'] as $dir) {
                $path = rtrim($this->mDirArticles . $dir, '/');
                if (is_dir($path)) {
                    $articles = glob($path . '/*.md', GLOB_NOSORT);
                    foreach ($articles as $article) {
                        $slug = substr($article, strlen($this->mDirArticles), -3);
                        $lines = array_merge($lines, $this->_GetArticleFrontmatter($slug));
                    }
                    $lines[] = '%';
                }
            }
            $event = array('content' => array());
            $is_header = TRUE;
            foreach ($lines as $line) {
                if ($line === '%') {
                    if (!empty($event['title'])) {
                        $event['content'] = implode("\n", $event['content']);
                        $event['timeline'] = $timeline['name'];
                        $event['mode'] = strval($timeline['mode']);
                        $events[] = $event;
                    }
                    $event = array('content' => array());
                    $is_header = TRUE;
                    continue;
                }
                if ($is_header) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $headers = $this->_ExtractHeadersFromLine($line);
                        $event['has_data'] = $headers['has_data'];
                        $event['time'] = $headers['time'];
                        $event['title'] = $headers['title'];
                        $event['slug'] = $headers['slug'];
                        $is_header = FALSE;
                    }
                } else {
                    if ($timeline['mode'] !== 'archive') {
                        $event['content'][] = $line;
                    }
                }
            }
        }
        $events = array_filter($events, function ($event) {
            return $event['title'] !== '';
        });
        usort($events, function ($a, $b) {
            return $a['time'] <= $b['time'];  // use `<` if ...
        });
        // Save events to database.
        $this->mDB->SetRecords($table_name, $events);
        $this->mDB->Update($table_name);
        $this->mIsTimelinesOk = TRUE;
    }

    private function _CheckHtaccess()
    {
        if (!file_exists($this->mDirRoot . '.htaccess')) {
            require_once 'siii-htaccess.php';
        }
    }

    private function _UpdateSitemap($force = FALSE)
    {
        $mapfile = $this->mDirRoot . 'sitemap.xml';
        if (!$force and file_exists($mapfile)) {
            if (time() - filemtime($mapfile) > $this->mCacheLifetimeForFeed) {
                unlink($mapfile);
            } else {
                return;
            }
        }
        require_once 'class-sitemap.php';
        $build_date = $this->mDB->Max($this->mDbTableName, 'time', TRUE);
        $homepage = rtrim($this->GetMetadata('homepage'), '/') . '/';
        $sitemap = new Sitemap($homepage, 'daily', 0.5, $build_date);
        $events = $this->mDB->Select($this->mDbTableName, array(
            'column' => array('slug', 'time'),
            'where' => function ($record) {
                return $record['mode'] !== 'archive' and $record['slug'] !== '';
            }
        ), TRUE);
        foreach ($events as $event) {
            $date = $event['time'];
            $link = $homepage . $this->EncodeUrl($event['slug'], TRUE) . '.html';
            $sitemap->AddUrl($link, 'weekly', 0.9, $date);
        }
        ignore_user_abort(TRUE);
        file_put_contents($mapfile, $sitemap->Fetch(), LOCK_EX);
        ignore_user_abort(FALSE);
    }

    private function _UpdateFeed($force = FALSE)
    {
        $feedfile = $this->mDirRoot . 'feed.xml';
        if (!$force and file_exists($feedfile)) {
            if (time() - filemtime($feedfile) > $this->mCacheLifetimeForFeed) {
                unlink($feedfile);
            } else {
                return;
            }
        }
        require_once 'class-feed.php';
        $homepage = rtrim($this->GetMetadata('homepage'), '/') . '/';
        $feed = new RssFeed($this->GetMetadata('title'),
                            $homepage,
                            $this->GetMetadata('subtitle'));
        $feed->Config('ttl', '90');
        $feed->Config('timeline', 'blog');
        $license = $this->GetMetadata('license');
        $feed->Config('copyright', $license['text']);

        $events = $this->mDB->Select($this->mDbTableName, array(
            'where' => function ($record) {
                return $record['mode'] === '' and $record['slug'] !== '';
            }
        ), TRUE);
        //$max_item_number = 10;
        //$events = array_slice($events, 0, $max_item_number);
        foreach ($events as $event) {
            $slug = $event['slug'];
            //$description = $this->ParseMarkup($this->GetEventContent($event));
            $description = $this->ParseMarkup($event['content']);
            $feed->AddItem(array(
                'title' => $event['title'],
                'link' => $homepage . $this->EncodeUrl($slug, TRUE) . '.html',
                'guid' => $homepage . $this->EncodeUrl($slug, TRUE) . '.html',
                'pubDate' => $event['time'],
                'description' => $description,
                'category' => $event['timeline']
            ));
        }
        ignore_user_abort(TRUE);
        file_put_contents($feedfile, $feed->Fetch(), LOCK_EX);
        ignore_user_abort(FALSE);
    }
}
?>
