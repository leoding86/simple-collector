<?php
require('./src/simple_html_dom.php');
require('./src/autoload.php');

use leod\collector;

// 采集所有文章入口
$contentUrlsSelector = new collector\CollectorSelector();
$contentUrlsSelector->setQuerySelector('#main-content .art-li a', 'href');

$contentUrlsCollector = new collector\Collector();
$contentUrlsCollector->setMax(1);
$contentUrlsCollector->addUrl('http://news.cjn.cn/sywh/');
$contentUrlsCollector->setWildcardUrl('http://news.cjn.cn/sywh/index_[*].htm', -1);
$contentUrlsCollector->setContentUrlSelector($contentUrlsSelector);
$contentUrlsCollector->init();

// 获得目标内容链接
$urls = $contentUrlsCollector->getContentUrls();

// 采集文章内容
$contentTitleSelector = new collector\CollectorSelector();
$contentTitleSelector->setQuerySelector('#main-content .art-title', 'plaintext');
$contentTitleSelector->setOffset(0);
$contentAuthorSelector = new collector\CollectorSelector();
$contentAuthorSelector->setWildcardSelector('责编：[[*]]</p>');
$contentAuthorSelector->setOffset(0);
$contentContentSelector = new collector\CollectorSelector();
$contentContentSelector->setQuerySelector('.art-main', 'innertext');
$contentContentSelector->setOffset(0);
$contentContentSelector->addReplacement('/<div\s[^>]*>/i', '', collector\CollectorSelector::REPLACE_PATTERN);
$contentContentSelector->addReplacement('/<\/div>/i', '', collector\CollectorSelector::REPLACE_PATTERN);
$contentContentSelector->addReplacement('/<!--(?:.*?)-->/', '', collector\CollectorSelector::REPLACE_PATTERN);

$contentsCollector = new collector\Collector();
$contentsCollector->setDownloadPictrue(1);
$contentsCollector->setContentSelector('content', $contentContentSelector);
$contentsCollector->getPictureMaker()->addSize(100, 80);
$contentsCollector->getPictureMaker()->addSize(80, 100);
$contentsCollector->getPictureMaker()->setRootUrl('http://app.cjn.cn');
$contentsCollector->getPictureMaker()->setSavePath('D:\\Workspace\\Web\\_test\\collector');
$contentsCollector->setContentPagesParser(function($html, $htmlDom) {
    $pageFinder = new collector\Finder();
    $result = $pageFinder->findByWildcard($html, '<script type="text/javascript">createPageHTML([[*]])</script>');
    list($max_page, , $page_name, $suffix) = explode(',', preg_replace(['/\s+/', '/"/'], '', $result[0]));
    $page_urls = [];
    for ($i = 1; $i < $max_page; $i++) {
        $page_urls[] = $page_name . '_' . $i . '.' . $suffix;
    }
    return $page_urls;
});
$contentsCollector->addUrls($urls);
$contentsCollector->addSelector('title', $contentTitleSelector);
$contentsCollector->addSelector('author', $contentAuthorSelector);

$contentsCollector->addEventListener('collect_content_success', function($success_url, $result, $pictures) {
    echo 'content event: ' . PHP_EOL;
    echo 'url: ' . $success_url . PHP_EOL;
    // echo 'success: ' . $success_url . PHP_EOL;
    // echo 'title: ' . $result['title'] . PHP_EOL;
    // echo 'author: ' . $result['author'] . PHP_EOL;
    var_dump($pictures);
    var_dump($result['content']);
    echo PHP_EOL . PHP_EOL;
});
$contentsCollector->addEventListener('collect_paged_main_content_success', function($url, $content, $pictures) {
    echo 'paged event: ' . PHP_EOL;
    echo 'url: ' . $url . PHP_EOL;
    var_dump($pictures);
    var_dump($content);
    echo PHP_EOL . PHP_EOL;
});
// $contentsCollector->addEventListener('content_collect_fail', function($fail_url) {
//     var_dump($fail_url);
// });

/* 初始化采集器 */
$contentsCollector->init();

/* 采集内容 */
$contentsCollector->getContents();

// $start_time = time();
// $end_time = time();

// echo 'Complete is ' . ($end_time - $start_time) . PHP_EOL;
// echo 'Max memory used ' . memory_get_peak_usage() . PHP_EOL;