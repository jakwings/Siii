Siii博客系統
============

一個面向程序員的簡單實用易改造的個人（微）博客系統。以上傳文件的方式更新博客。（Commandline 黨請善用 `curl` 和 `wput` 。）

服務器需求：Apache 2.2.x及以上版本，PHP 5.3.4及以上版本，而且對並發連接數的需求不要太高啦……

想移植到 Nginx 服務器應該也不難（URL重寫），下次再介紹相關配置。

### 安裝方法

1. `git clone https://github.com/jakwings/Siii.git /網站根目錄/Siii`
2. `cd /網站根目錄/Siii`
3. `rm -f ./.htaccess` （每次更改路徑都要刪除）
4. 編輯配置文件 `Siii/config/config.toml` 或者啥都不做。
5. 最後訪問 http://example.com/Siii/ ，`.htaccess` 文件會自行生成。

其實不安裝在子目錄也可以，Siii會自動搞定URL重寫。

### 配置方法

請直接看 `Siii/config/config.toml` 裡的內容，再看 `Siii/files/` 裡有什麼。

Disqus或多說評論的代碼可直接复製到 `Siii/templates/article/comments.php` 那文件裡。（這是非PHP黨唯一可輕鬆搞掂的網頁模板……）

有這些提示，看不懂你請我吃飯我再告訴你！（最近綱換輸入法打字慢懶得打字……）

### 强制清空緩存的方法

假如你覺得一切穩定了，在 `Siii/config/config.toml` 開啓緩存功能以後，隨時上傳 `Siii/cmd_clear_cache` 文件（任意內容）並訪問博客首頁即可。

### 各文件（夾）的用途

```
Siii/
    |- .htaccess            用於URL重寫的Apache配置文件（自動生成）
    |- */.htaccess          用於禁止非法訪問
    |- cache/*.cache        網頁緩存文件（自動生成）
    |- config/
             |- config.toml 以TOML 0.2爲語法的配置文件
    |- cmd_clear_cache      用於强制清空緩存的任意文件
    |- data/*               網頁所用的各種資源
    |- database/*           數據庫文件（文本格式）
    |- favicon.ico          網站圖標
    |- feed.xml             博客的RSS/Atom訂閱文件（自動生成）
    |- files/*.md           各文章的Markdown源文件（Parsedown語法）
    |- index.php            一切頁面的入口
    |- sitemap.xml          可提交給Google Web Master站長工具（自動生成）
    |- templates/*          網頁模板
    |- utils/*              核心文件，不解釋
```

### 網頁主題模板太少？

歡迎fork，歡迎通過提交issue分享你的傑作！不要求接口統一，但求個性與樂趣！

### 爲何該系統如此簡陋？

讓PHP菜鳥易上手DIY，不讓過早優化並模塊化扼殺簡單實用及個性。這東東多多少少面向沒錢租VPS的程序員，有儿女的話，不妨做個GUI管理工具，計劃培養下一代。:p