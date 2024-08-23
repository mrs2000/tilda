<?php

namespace mrssoft\tilda;

use yii\base\Component;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use yii\di\Instance;
use yii\web\NotFoundHttpException;
use yii\web\View;

class TildaApi extends Component
{
    public ?string $publicKey;

    public ?string $secretKey;

    public ?int $projectId;

    public ?int $cacheDuration = null;

    public string|bool|CacheInterface $cache = 'cache';

    public string $cacheTag = 'tilda-page';

    public function init(): void
    {
        parent::init();

        if ($this->cache) {
            $this->cache = Instance::ensure($this->cache, CacheInterface::class);
        }
    }

    public function pageHtml(?View $view, ?int $pageId = null, int $projectId = null): ?string
    {
        $data = $this->page($pageId, $projectId);
        if ($data) {
            if ($view) {
                foreach ($data['js'] as $js) {
                    $view->registerJsFile($js, ['position' => View::POS_HEAD]);
                }
                foreach ($data['css'] as $css) {
                    $view->registerCssFile($css);
                }
            }

            return $data['html'];
        }

        return null;
    }

    public function clearCache(?int $pageId): void
    {
        if ($this->cache) {
            if ($pageId) {
                $this->cache->delete($this->cacheKey($pageId));
            } else {
                TagDependency::invalidate($this->cache, $this->cacheTag);
            }
        }
    }

    private function cacheKey(int $pageId): string
    {
        return 'tildaPage' . $this->projectId . $pageId;
    }

    /**
     * Получить данные Tilda страницы
     */
    public function page(?int $pageId = null, int $projectId = null): ?array
    {
        $projectId = $projectId ?? $this->projectId;

        $result = $this->cache ? $this->cache->get($this->cacheKey($pageId)) : false;

        if ($result === false) {

            //Страница не указана - определить первую страницу проекта
            if ($pageId === null) {
                $pages = $this->request('getpageslist', $projectId);
                if ($pages === null || count($pages) == 0) {
                    throw new NotFoundHttpException("Error load project $projectId page list.");
                }
                $pageId = $pages[0]['id'];
            }

            $response = $this->request('getpage', null, $pageId);
            if ($response === null) {
                throw new NotFoundHttpException("Error load project $projectId page $pageId");
            }

            $result = [
                'js' => $response['js'],
                'css' => $response['css'],
                'html' => $response['html'],
            ];

            if ($this->cache && $this->cacheDuration !== null) {
                $this->cache->set($this->cacheKey($pageId), $result, $this->cacheDuration, new TagDependency([
                    'tags' => $this->cacheTag
                ]));
            }
        }

        return $result;
    }

    /**
     * http://help-ru.tilda.ws/api
     */
    private function request(string $command, ?int $projectId, int $pageId = null): ?array
    {
        $params = [
            'publickey' => $this->publicKey,
            'secretkey' => $this->secretKey,
            'projectid' => $projectId,
            'pageid' => $pageId,
        ];

        $url = "https://api.tildacdn.info/v1/$command/?" . http_build_query($params);
        $response = file_get_contents($url);

        if ($response) {
            $data = json_decode($response, true);
            if ($data['status'] === 'FOUND') {
                return $data['result'];
            }
        }

        return null;
    }
}
