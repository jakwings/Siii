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
    private $mIsTimelinesOK = FALSE;
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

        // These instructions must be in this execution order.
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

    public function Render($data = NULL)
    {
        require_once 'siii-renderer.php';
        $this->_ConnectDatabase();
        $blog = new Renderer(array(
            'database' => $this->mDB,
            'metadata' => $this->mMetadata,
            'dir_articles' => $this->mDirArticles,
            'dir_templates' => $this->mDirTemplates,
            'db_tablename' => $this->mDbTableName
        ));
        if ($this->mCacheEnabled) {
            $this->FindCache($blog);
        } else {
            $this->mIsCacheReady = $blog->Render($data);
        }
    }

    public function GetArticleContent($slug)
    {
        $path = $this->GetSecurePath($this->mDirArticles . $slug . '.md');
        return file_get_contents($path);
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
        require_once 'class-parsedown.php';
        $parser = new Parsedown();
        return $parser->text($source);
    }

    public function EscapeHtml($text, $flag = ENT_QUOTES)
    {
        return htmlentities($text, $flag, 'UTF-8');
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
        //$this->_StartCache();
        ob_clean();
        ob_start();
        $this->mIsCacheReady = $blog->Render($data);
        if ($this->mIsCacheReady) {
            $buffer = ob_get_flush();
            ignore_user_abort(TRUE);
            file_put_contents($this->mCacheFile, $buffer, LOCK_EX);
            ignore_user_abort(FALSE);
        }
    }

    private function _StartCache()
    {
        ob_clean();
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            ob_start(array($this, '_EndCache'), 0, PHP_OUTPUT_HANDLER_FLUSHABLE);
        } else {
            // This has failed with PHP5.3.28
            ob_start(array($this, '_EndCache'), 0, false);
        }
    }
    private function _EndCache($buffer, $phase)
    {
        if ($this->mIsCacheReady and $phase & PHP_OUTPUT_HANDLER_END) {
            ignore_user_abort(TRUE);
            file_put_contents($this->mCacheFile, $buffer, LOCK_EX);
            ignore_user_abort(FALSE);
        }
        return $buffer;
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
        $this->mIsTimelinesOK = FALSE;
        $this->mMetadata = $config['metadata'];
        date_default_timezone_set($this->mMetadata['timezone']);
    }

    public function _SetupTimelines($force = FALSE)
    {
        if ($this->mIsTimelinesOK) {
            return;
        }
        $this->_ConnectDatabase();
        $table_name = $this->mDbTableName;
        // Check if table exists.
        if ($this->mDB->ListTables($table_name)) {
            if (!$force) {
                $this->mIsTimelinesOK = TRUE;
                return;
            }
        } else {
            $this->mDB->CreateTable($table_name, array(
                'time', 'timeline', 'hidden', 'title', 'slug', 'content'
            ));
        }
        // Collect events from timelines.
        $events = array();
        $timelines = $this->mTimelines;
        $dir_config = $this->mDirConfig;
        $timelines = array_filter($timelines, function ($timeline) use ($dir_config) {
            if (empty($timeline['name'])) {
                return FALSE;
            }
            $files = is_array($timeline['file']) ? $timeline['file']
                                                 : array($timeline['file']);
            foreach ($files as $file) {
                if (!file_exists($dir_config . $file)) {
                    return FALSE;
                }
            }
            return TRUE;
        });
        foreach ($timelines as $timeline) {
            if (!is_array($timeline['file'])) {
                $lines = file($this->mDirConfig . $timeline['file'],
                              FILE_IGNORE_NEW_LINES);
                $lines[] = '%';
            } else {
                $lines = array();
                foreach ($timeline['file'] as $file) {
                    $lines = array_merge($lines,
                                         file($this->mDirConfig . $file,
                                              FILE_IGNORE_NEW_LINES));
                    $lines[] = '%';
                }
            }
            $event = array('content' => array());
            $is_header = TRUE;
            foreach ($lines as $line) {
                if ($line === '%') {
                    $is_header = TRUE;
                    $event['content'] = implode("\n", $event['content']);
                    $event['timeline'] = $timeline['name'];
                    $event['hidden'] = !!$timeline['hidden'];
                    $events[] = $event;
                    $event = array('content' => array());
                    continue;
                }
                if ($is_header) {
                    $event['headers'] = mb_split('\s*//\s*', trim($line));
                    $event['time'] = strtotime($event['headers'][0] ?: time());
                    $event['title'] = strval($event['headers'][1]);
                    $event['slug'] = strval($event['headers'][2]);
                    $is_header = FALSE;
                } else {
                    $event['content'][] = $line;
                }
            }
        }
        $events = array_filter($events, function ($event) {
            return !empty($event['headers']) and $event['title'] !== '';
        });
        usort($events, function ($a, $b) {
            return $a['time'] <= $b['time'];  // use `<` if ...
        });
        // Save events to database.
        $this->mDB->SetRecords($table_name, $events);
        $this->mDB->Update($table_name);
        $this->mIsTimelinesOK = TRUE;
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
                return $record['slug'] !== '';
            }
        ), TRUE);
        foreach ($events as $event) {
          $date = $event['time'];
          $link = $homepage . rawurlencode($event['slug']) . '.html';
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

        $max_item_number = 10;
        $events = $this->mDB->Select($this->mDbTableName, array(
            'where' => function ($record) {
                return !$record['hidden'] and $record['slug'] !== '';
            }
        ), TRUE);
        $events = array_slice($events, 0, $max_item_number);
        foreach ($events as $event) {
            $slug = $event['slug'];
            //$description = $this->ParseMarkup($this->GetArticleContent($slug));
            $description = $this->ParseMarkup($event['content']);
            $feed->AddItem(array(
                'title' => $event['title'],
                'link' => $homepage . rawurlencode($slug) . '.html',
                'guid' => $homepage . rawurlencode($slug) . '.html',
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
