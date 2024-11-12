<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.YandexTurbo
 *
 * @copyright   (C) 2024 Sergey Kuznetsov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\YandexTurbo\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Yandex Turbo plugin
 *
 * @since  1.0
 */
class YandexTurbo extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var string[]
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'yandexturbo.channel' => [
            'langConstPrefix' => 'PLG_TASK_CHANNEL_CREATE',
            'form'            => 'channel',
            'method'          => 'channelCreate',
        ],
    ];

    /**
     * The root directory path
     *
     * @var    string
     * @since  4.2.0
     */
    private $rootDirectory;

    /**
     * Constructor.
     *
     * @param   DispatcherInterface  $dispatcher     The dispatcher
     * @param   array                $config         An optional associative array of configuration settings
     * @param   string               $rootDirectory  The root directory to look for images
     *
     * @since   4.2.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config, string $rootDirectory)
    {
        parent::__construct($dispatcher, $config);
        $this->rootDirectory = $rootDirectory;
    }

    /**
     * Load the language file on instantiation
     *
     * @var    boolean
     * @since  1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function getRevars($txt)
    {
        $plugin = PluginHelper::getPlugin('system', 'revars');

        if ($plugin) {
            $plugin->params = new Registry($plugin->params);
            $vars = (new Registry($plugin->params->get('variables')))->toArray();
            $nesting = (int) $plugin->params->get('nesting', 1);
            $allVariables = [];
            $txt_revars = '';

            if (!empty($vars)) {
                foreach ($vars as $variable) {
                    $allVariables[] = (object) $variable;
                }
            }

            $allVariables = array_reverse($allVariables);

            foreach ($allVariables as $variable) {
                $plugin->variables_prepare['keys'][]   = $variable->variable;
                $plugin->variables_prepare['values'][] = $variable->value;
            }

            $plugin->variables_all = array_reverse($allVariables);

            for ($i = 1; $i <= $nesting; $i++) {
                $txt_revars = str_replace($plugin->variables_prepare['keys'], $plugin->variables_prepare['values'], $txt);
            }

            return $txt_revars;
        } else return $txt;
    }

    function realCleanImageUrl($img)
    {
        $imgClean = HTMLHelper::cleanImageURL($img);
        if ($imgClean->url != '') $img = $imgClean->url;
        return $img;
    }

    private function setImage($image)
    {
        $host = Uri::getInstance()->getHost();
        $linkImg = $image;

        $absU = 0;
        // Test if this link is absolute http:// then do not change it
        $pos1 = strpos($image, 'http://');
        if ($pos1 === false) {
        } else {
            $absU = 1;
        }

        // Test if this link is absolute https:// then do not change it
        $pos2 = strpos($image, 'https://');
        if ($pos2 === false) {
        } else {
            $absU = 1;
        }

        if ($absU == 1) {
            $linkImg = $image;
        } else {
            $linkImg = 'https://' . $host . '/' . $image;

            if ($image[0] == '/') {
                $myURI = new Uri(Uri::base(false));
                $myURI->setPath($image);
                $linkImg = $myURI->toString();
            } else {
                $linkImg = 'https://' . $host . '/' . $image;
            }
        }

        return $linkImg;
    }

    private function itemRender($item)
    {
        $app = $this->getApplication();

        $host = Uri::getInstance()->getHost();
        $itemLink = 'https://' . $host . '/' . $item->category_route . '/' . $item->alias;

        $timezone = new \DateTimeZone($app->get('offset', 'UTC'));
        $item_date = Factory::getDate($item->modified);
        $item_date->setTimezone($timezone);
        $modDate = $item_date->toRFC822(true);

        $images = json_decode($item->images); // массив изображений
        $image_intro = $images->image_intro; // изображение вступительного текста
        $image_intro_alt = $images->image_intro_alt; // ALT изображения вступительного текста
        $image_fulltext = $images->image_fulltext; // изображение полного текста
        $image_fulltext_alt = $images->image_fulltext_alt; // ALT изображения полного текста
        $itemImageLink = $this->setImage($this->realCleanImageURL($image_intro ?: $image_fulltext));
        $itemImageAlt = $image_intro_alt ?: $image_fulltext_alt;
        $imageAttribs = [
            'src' => $itemImageLink,
            'alt' => $itemImageAlt,
            'width' => 768,
            'height' => 512,
            'class' => 'item-img'
        ];

        $itemImage = LayoutHelper::render('joomla.html.image', $imageAttribs); // изображение айтема

        return '
        <item turbo="true">
            <turbo:extendedHtml>true</turbo:extendedHtml>
            <link>' . $itemLink . '</link>
            <turbo:source>' . $itemLink . '</turbo:source>
            <turbo:topic>' . htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8', false) . '</turbo:topic>
            <pubDate>' . htmlspecialchars($modDate, ENT_COMPAT, 'UTF-8', false) . '</pubDate>
            <author>' . htmlspecialchars($item->author, ENT_COMPAT, 'UTF-8', false) . '</author>
            <turbo:content>
                <![CDATA[ <header><h1>' . htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8', false) . '</h1></header>
                <div>' . $itemImage . '</div>' . $this->getRevars($item->introtext) . ' ]]>
            </turbo:content>
        </item>';
    }

    private function channelInfoRender($cat)
    {
        $host = Uri::getInstance()->getHost();

        return '
        <title>' . htmlspecialchars($cat['name'], ENT_COMPAT, 'UTF-8', false) . '</title>
        <link>https://' . $host . '/' . trim($cat['link'], '/') . '</link>
        <description>' . htmlspecialchars($this->getRevars($cat['description']), ENT_COMPAT, 'UTF-8', false) . '</description>
        <language>ru</language>';
    }

    private function channelRender($data)
    {
        $items = '';

        foreach ($data['items'] as $item) {
            $items .= $this->itemRender($item);
        }

        return '
        <rss xmlns:yandex="http://news.yandex.ru" xmlns:media="http://search.yahoo.com/mrss/" xmlns:turbo="http://turbo.yandex.ru" version="2.0">
            <channel>'
            . $this->channelInfoRender($data) . $items .
            '</channel>
        </rss>
        ';
    }

    private function fileSave($data)
    {
        $path = Path::check($this->rootDirectory . 'yandex');

        if (!is_dir($path)) mkdir($path); // проверяем есть ли папка yandex, если нет, создаем ее
        if (isset($data) && !empty($data)) { // если есть данные
            file_put_contents($path  . '/' . trim($data['filename'], '/') . '.turbo.xml', print_r($this->channelRender($data), true)); // то записываем в файл
        }
    }

    /**
     * Plugin method for the 'onTaskYandexTurbo' event.
     *
     * @param   string  $context  The context of the event
     * @param   mixed   $data     The data related to the event
     *
     * @return  void
     *
     * @since   1.0
     */
    protected function channelCreate(ExecuteTaskEvent $event): int
    {
        $app = $this->getApplication();
        $factory = $app->bootComponent('com_content')->getMVCFactory();
        $articles = $factory->createModel('Articles', 'Site', ['ignore_request' => true]);
        $appParams = ComponentHelper::getParams('com_article');
        $articles->setState('params', $appParams);
        $articles->setState('list.start', 0);
        $articles->setState('filter.published', ContentComponent::CONDITION_PUBLISHED);

        $params = new Registry($event->getArgument('params'));

        $articles->setState('list.limit', (int) $params->get('count', 0));

        $catids = $params->get('catid');
        $articles->setState('filter.category_id.include', (bool) $params->get('category_filtering_type', 1));

        if ($catids) {
            if ($params->get('show_child_category_articles', 0) && (int) $params->get('levels', 0) > 0) {
                $categories = $factory->createModel('Categories', 'Site', ['ignore_request' => true]);
                $categories->setState('params', $appParams);
                $levels = $params->get('levels', 1) ?: 9999;
                $categories->setState('filter.get_children', $levels);
                $categories->setState('filter.published', 1);
                $additional_catids = [];

                foreach ($catids as $catid) {
                    $categories->setState('filter.parentId', $catid);
                    $recursive = true;
                    $items = $categories->getItems($recursive);

                    if ($items) {
                        foreach ($items as $category) {
                            $condition = (($category->level - $categories->getParent()->level) <= $levels);

                            if ($condition) {
                                $additional_catids[] = $category->id;
                            }
                        }
                    }
                }

                $catids = array_unique(array_merge($catids, $additional_catids));
            }
            $articles->setState('filter.category_id', $catids);
        }

        $ex_or_include_articles = $params->get('ex_or_include_articles', 0);
        $filterInclude = true;
        $articlesList = [];

        $articlesListToProcess = $params->get('included_articles', '');

        if ($ex_or_include_articles === 0) {
            $filterInclude = false;
            $articlesListToProcess = $params->get('excluded_articles', '');
        }

        foreach (ArrayHelper::fromObject($articlesListToProcess) as $article) {
            $articlesList[] = (int) $article['id'];
        }

        if ($ex_or_include_articles === 1 && empty($articlesList)) {
            $filterInclude  = false;
            $articlesList[] = $currentArticleId;
        }

        if (!empty($articlesList)) {
            $articles->setState('filter.article_id', $articlesList);
            $articles->setState('filter.article_id.include', $filterInclude);
        }

        $items = $articles->getItems();

        $cat = [];
        if (
            empty($params->get('channel_name'))
            || empty($params->get('channel_link'))
            || empty($params->get('channel_description'))
        ) {
            $categoryFactory = $app->bootComponent('com_content')->getCategory();
            $cat = $categoryFactory->get($catids[0]);
        }

        $channelName = $params->get('channel_name') ?: $cat->title;
        $channelLink = $params->get('channel_link') ?: $cat->alias;
        $channelFileName = $cat->alias;
        $channelDescription = $params->get('channel_description') ?: strip_tags($cat->description);

        $data = [
            'name' => $channelName,
            'link' => $channelLink,
            'description' => $channelDescription,
            'filename' => $channelFileName,
            'items' => $items
        ];

        $this->fileSave($data);

        return TaskStatus::OK;
    }
}