<?php
namespace leoding86\SimpleCollector;

class Collector extends Finder
{
    const WILDCARD_MARK = '[*]';
    const CONTENT_URL_SELECTOR_ID = '__CONTENT_URL_SELECTOR__';
    const MAIN_CONTENT_SELECTOR_ID = '__MAIN_CONTENT_SELECTOR__';
    const EVENTS = ['collect_content_success', 'collect_content_fail', 'collect_paged_main_content_success'];
    const PAGES_INLINE = '__PAGES_INLINE__';
    const PAGES_CONTEXT = '__PAGES_CONTEXT__';

    private $isInited;
    private $urls;
    private $max;
    private $downloadPicture;
    private $listeners;
    private $contentSelector = null;
    private $contentSelectorIDAlias = null;
    private $contentPageMode = null;
    private $contentPagesSelector = null;
    private $contentPagesParser = null;
    private $CollectorParser = null;

    private $pictureMaker = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->isInited = false;
        $this->urls = array();
        $this->max = 3;
        $this->downloadPicture = false;
        foreach (self::EVENTS as $event) {
            $this->listeners[$event] = array();
        }
    }

    /**
     * 检查是否初始化
     * @return void
     */
    final private function checkIsInited()
    {
        if (!$this->isInited) {
            throw new \Exception("Collector has not been initialized", 1);
        }
    }

    /**
     * 根据规则替换目标字符串
     * @param  string             $string       字符串
     * @param  CollectorSelector  $replacements 一组替换规则
     * @return string
     */
    final private function replaceString($string, CollectorSelector $selector)
    {
        $replacements = $selector->getReplacements();

        foreach ($replacements as $replacement) {
            if ($replacement->mode === $selector::REPLACE_STRING) {
                $string = str_replace($replacement->search, $replacement->replace, $string);
            }
            else if ($replacement->mode === $selector::REPLACE_PATTERN) {
                $string = preg_replace($replacement->search, $replacement->replace, $string);
            }
            /* Fallback */
            else {
                if (@preg_match($replacement->search, null) === false) {
                    $string = str_replace($replacement->search, $replacement->replace, $string);
                }
                else {
                    $string = preg_replace($replacement->search, $replacement->replace, $string);
                }
            }
        }

        return $string;
    }

    /**
     * 查找内容页分页链接
     * @param  string          $html    html字符串
     * @param  simple_html_dom $htmlDom simple_html_dom 对象
     * @return array
     */
    final private function getContentInlinePages($html, $htmlDom)
    {
        $urls = [];
        $page_html = $this->findBySelector($html, $htmlDom, $this->contentPagesSelector);

        if ($page_html) {
            $pattern = '/<a[^\/]+href=(?:"|\')(.+?)(?:"|\')[^\/]>/';
            $match = [];
            preg_match_all($pattern, $page_html, $match, PREG_SET_ORDER);
            foreach ($match as $val) {
                $urls[] = $val[1];
            }
        }

        return $urls;
    }

    /**
     * 设置是否需要下载图片
     * @param boolean $boolean ture下载图片|false不下载图片
     */
    public function setDownloadPictrue($boolean)
    {
        $this->downloadPicture = (bool)$boolean;
    }

    /**
     * 设置收集分析器
     * 包括简化链接、转化链接、常规化链接等
     * @param CollectorParser $parser 收集分析器实例
     */
    public function setCollectorParser(CollectorParser $parser)
    {
        $this->CollectorParser = $parser;
    }

    public function setContentPagesParser(/* callable */ $parser)
    {
        $this->contentPagesParser = $parser;
    }

    public function setMax($number)
    {
        $this->max = $number;
    }

    public function setWildcardUrl($url, $offset)
    {
        if (strpos($url, self::WILDCARD_MARK) !== false) {
            $length = $this->max - count($this->urls);
            for ($i = 1; $i <= $length; $i++) {
                $this->addUrl(str_replace(self::WILDCARD_MARK, $i + 1 + $offset, $url));
            }
        }
        else {
            throw new \Exception("Use method 'addUrl' to add single url", 1);
        }
    }

    public function setContentUrlSelector(CollectorSelector $selector)
    {
        $this->addSelector(self::CONTENT_URL_SELECTOR_ID, $selector);
    }

    public function setContentSelector($alias, CollectorSelector $selector)
    {
        $this->contentSelector = $selector;
        $this->contentSelectorIDAlias = $alias;
        $this->addSelector(self::MAIN_CONTENT_SELECTOR_ID, $selector);
    }

    /**
     * 设置查找内容页分页包裹的查找器
     * @param CollectorSelector $selector 查找器
     */
    public function setContentPagesSelector(CollectorSelector $selector)
    {
        $selector->setAttr('innertext');
        if (!$selector->offset) {
            $selector->setOffset(0);
        }
        $this->contentPagesSelector = $selector;
    }

    /**
     * 设置图片处理器
     * @param pictureMaker $maker 图片处理器
     */
    public function setPictureMaker(CollectorPictureMaker $maker)
    {
        $this->pictureMaker = $maker;
    }

    /**
     * 添加多个需要采集的链接
     * @param array $urls 需要采集的链接
     */
    public function addUrls(array $urls)
    {
        foreach ($urls as $url) {
            $this->addUrl($url);
        }
    }

    /**
     * 添加需要采集的链接
     * @param string $url 有效的链接
     */
    public function addUrl($url)
    {
        $this->urls[] = $url;
    }

    /**
     * 获得图片制造器
     * @return CollectorPictureMaker 图片制造器类型或子类
     */
    public function getPictureMaker()
    {
        if (!$this->pictureMaker) {
            $this->setPictureMaker(new CollectorPictureMaker());
        }
        return $this->pictureMaker;
    }

    /**
     * 初始化
     * @return void
     */
    public function init()
    {
        if (!$this->CollectorParser) {
            $this->CollectorParser = new CollectorParser();
        }

        if (!$this->pictureMaker) {
            $this->pictureMaker = new CollectorPictureMaker();
        }

        $this->isInited = true;
    }

    /**
     * 获得内容链接
     * @return array 内容链接集合
     */
    public function getContentUrls()
    {
        /* 检查初始化状态 */
        $this->checkIsInited();

        $content_urls = array();
        /* 遍历入口链接 */
        foreach ($this->urls as $url) {
            /* 分析入口链接 */
            $this->CollectorParser->simplifyUrl($url);
            if ( ($result = $this->getResult($url)) !== false ) {
                /* 遍历内链接 */
                foreach ($result[self::CONTENT_URL_SELECTOR_ID] as $key => $_url) {
                    /* 处理页面内链接 */
                    $result[self::CONTENT_URL_SELECTOR_ID][$key] = $this->CollectorParser->changeUrl($_url);
                }
                $content_urls = array_merge($content_urls, $result[self::CONTENT_URL_SELECTOR_ID]);
            }
        }
        return $content_urls;
    }

    /**
     * 采集内容
     * @return void
     */
    public function getContents()
    {
        /* 检查初始化状态 */
        $this->checkIsInited();

        /* 所有入口链接 */
        foreach ($this->urls as $url) {
            $this->CollectorParser->simplifyUrl($url);

            /* 初始化分页正文内容容器 */
            $paged_main_content = [];

            /* 获得内容入口页面内容 */
            if ( ($result = $this->getResult($url)) !== false) {

                /* 判断采集内容是否有正文内容 */
                if (isset($result[self::MAIN_CONTENT_SELECTOR_ID])) {
                    /* 保存第一页内容 */
                    $paged_main_content[] = $result[self::MAIN_CONTENT_SELECTOR_ID];

                    /* 如果设置了分页分析器，则使用设定的分析器获得分页链接 */
                    if ($this->contentPagesParser) {
                        $page_urls = call_user_func_array($this->contentPagesParser, [$this->getHtml(), $this->getHtmlDom()]);
                    }
                    /** 
                     * 如果没有设置分析器，则根据不同模式获得指定分页链接 
                     * 分页列表模式
                     **/
                    else if ($this->contentPageMode === self::PAGES_INLINE) {
                        $page_urls = $this->getContentInlinePages($this->html, $this->htmlDom);
                    }
                    /**
                     * 上下文分页模式
                     */
                    else if ($this->contentPageMode === self::PAGES_CONTEXT) {
                        throw new \Exception("This mode is not implement yet.", 1);
                    }

                    /* 内部查找实例，用于查找分页其他内容 */
                    $mainContentFinder = new Finder();
                    $mainContentFinder->addSelector(self::MAIN_CONTENT_SELECTOR_ID, $this->contentSelector);
                    foreach ($page_urls as $key => $page_url) {
                        // $current_url = $this->CollectorParser->changeUrl($page_url);
                        $page_result = $mainContentFinder->getResult($this->CollectorParser->changeUrl($page_url));

                        /* 判断是否存在内容 */
                        if ($page_result[self::MAIN_CONTENT_SELECTOR_ID]) {
                            $paged_main_content[] = $result[self::MAIN_CONTENT_SELECTOR_ID];
                        }
                    }
                    unset($key, $page_url, $page_result, $result[self::MAIN_CONTENT_SELECTOR_ID]);
                }

                /* 处理替换工作 */
                foreach ($result as $key => &$item) {
                    if ($selector = $this->getSelector($key)) {
                        $item = $this->replaceString($item, $selector);
                    }
                }
                unset($key, $item);

                /* 遍历分页内容，并替换字符串 */
                foreach ($paged_main_content as &$content) {
                    if ($this->contentSelector) {
                        $content = $this->replaceString($content, $this->contentSelector);
                    }
                }
                unset($content);

                if ($paged_main_content) {
                    /* 创建闭包函数需要的实例 */
                    $CollectorParser = $this->CollectorParser;
                    $pictureMaker = $this->pictureMaker;
                    $downloadPicture = $this->downloadPicture;

                    $content_pictures = [];         /* 初始化正文图片容器，用于返回给监听器 */

                    /* 替换正文内容的图片地址为采集后地址 */
                    foreach ($paged_main_content as &$content) {
                        $paged_content_pictures = [];   /* 初始化存储每页图片容器，用于返回给监听器 */

                        $content = preg_replace_callback(
                            '/<img\s[^>]*\ssrc="([^>]+?)"\s[^>]*\/?>/i',
                            function ($match) use ($CollectorParser, $pictureMaker, &$content_pictures, &$paged_content_pictures, $downloadPicture) {
                                /* 补全图片链接 */
                                $pic_url = $CollectorParser->changeUrl($match[1]);

                                /* 如果需要下载图片，则替换为目标地址 */
                                if ($downloadPicture) {
                                    $pic_url = $pictureMaker->getUrl($pic_url);
                                }

                                $content_pictures[] = $pic_url;
                                $paged_content_pictures[] = $pic_url;
                                return '<img src="' . $pic_url . '" />';
                            },
                            $content
                        );
                        $this->dispatch('collect_paged_main_content_success', $url, $content, $paged_content_pictures);
                    }
                    unset($CollectorParser, $pictureMaker, $downloadPicture, $content, $paged_content_pictures);

                    /* 替换别名 */
                    $result[$this->contentSelectorIDAlias] = Helper::formatContent(implode('', $paged_main_content));
                }

                $this->dispatch('collect_content_success', $url, $result, $content_pictures);
            }
            else {
                $this->dispatch('collect_content_fail', $url);
            }
        }
        unset($url);

        if ($this->downloadPicture) {
            /* 开始下载图片 */
            $this->pictureMaker->download();
        }
    }

    /**
     * 检查是否是有效事件类型
     * @param  string $event 事件类型
     * @return void
     */
    final private function checkEventIsValid($event)
    {
        if (!isset($this->listeners[$event])) {
            throw new \Exception("Unkown event [" . $event . "]", 1);
        }
    }

    /**
     * 添加事件监听
     * @param string    $event    事件类型
     * @param callback  $listener 监听函数
     * @param string    $id       监听ID
     */
    final public function addEventListener($event, $listener, $id = null)
    {
        $this->checkEventIsValid($event);
        ($id !== null && is_string($id) && !empty($id)) ? 
            $this->listeners[$event][$id] = $listener : 
            $this->listeners[$event][] = $listener;
    }

    /**
     * 移除事件监听
     * @param  string $event 事件类型
     * @param  string $id    监听器id
     * @return void
     */
    final public function removeEventListener($event, $id)
    {
        $this->checkEventIsValid($event);
        if (isset($this->listeners[$event][$id])) {
            unset($this->listeners[$event][$id]);
        }
    }

    /**
     * 处罚事件
     * @param  string $event 事件类型
     * @return void
     */
    final public function dispatch($event)
    {
        $this->checkEventIsValid($event);
        $args = func_get_args();
        
        if (count($args) > 1) {
            array_shift($args);
        }

        foreach ($this->listeners[$event] as $listener) {
            if (empty($args)) {
                call_user_func_array($listener);
            }
            else {
                call_user_func_array($listener, $args);
            }
        }
    }

}

class Helper
{
    public static function formatContent($string)
    {
        $string = preg_replace(
            ['/<p\s+[^>]*>(.+?)<\/p>/i', '/\s{2,}/', '/<([a-z]+)\s+[^>]*[^\/]>/i'], 
            ['<p>$1</p>', ' ', '<$1>'],
            $string
        );
        $string = trim(str_replace(['&nbsp;', '　'], '', $string));
        return $string;
    }
}