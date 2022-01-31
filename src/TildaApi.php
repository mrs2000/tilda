<?php

namespace mrssoft\tilda;

use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\caching\TagDependency;
use yii\web\NotFoundHttpException;
use yii\web\View;

class TildaApi extends Component
{
    public ?string $publicKey;

    public ?string $secretKey;

    public ?int $projectId;

    public int $cacheDuration = 86400;

    /**
     * @param View|null $view
     * @param int|null $pageId
     * @param int|null $projectId
     * @return string|null
     * @throws ErrorException
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function pageHtml(?View $view, ?int $pageId = null, int $projectId = null): ?string
    {
        $data = $this->page($pageId, $projectId);
        if ($data) {
            if ($view) {
                foreach ($data['js'] as $js) {
                    $view->registerJsFile($js['from'], ['position' => View::POS_HEAD]);
                }
                foreach ($data['css'] as $css) {
                    $view->registerCssFile($css['from']);
                }
            }

            return $data['html'];
        }

        return null;
    }

    /**
     * @param int|null $pageId
     */
    public function clearCache(?int $pageId): void
    {
        if ($pageId) {
            Yii::$app->cache->delete($this->cacheKey($pageId));
        } else {
            TagDependency::invalidate(Yii::$app->cache, 'tilda-page');
        }
    }

    /**
     * @param int $pageId
     * @return string
     */
    private function cacheKey(int $pageId): string
    {
        return 'tildaPage' . $this->projectId . $pageId;
    }

    /**
     * Получить данные Tilda страницы
     * @param int|null $pageId
     * @param int|null $projectId
     * @return array|null
     * @throws ErrorException
     * @throws NotFoundHttpException
     */
    public function page(?int $pageId = null, int $projectId = null): ?array
    {
        $projectId = $projectId ?? $this->projectId;

        $result = $this->cacheDuration ? Yii::$app->cache->get($this->cacheKey($pageId)) : false;

        if ($result === false) {

            $project = $this->request('getprojectexport', $projectId);
            if ($project === null) {
                throw new ErrorException("Error load project $projectId");
            }

            //Страница не указана - определить первую страницу проекта
            if ($pageId === null) {
                $pages = $this->request('getpageslist', $projectId);
                if ($pages === null || count($pages) == 0) {
                    throw new NotFoundHttpException("Error load project $projectId page list.");
                }
                $pageId = $pages[0]['id'];
            }

            $pageHtml = $this->request('getpage', null, $pageId);
            if ($pageHtml === null) {
                throw new NotFoundHttpException("Error load project $projectId page $pageId");
            }

            $result = [
                'js' => $project['js'],
                'css' => $project['css'],
                'html' => $pageHtml['html'],
            ];

            if ($this->cacheDuration) {
                Yii::$app->cache->set($this->cacheKey($pageId), $result, $this->cacheDuration, new TagDependency(['tags' => 'tilda-page']));
            }
        }

        return $result;
    }

    /**
     * http://help-ru.tilda.ws/api
     *
     * @param string $command
     * @param int|null $projectId
     * @param int|null $pageId
     * @return array|null
     * @noinspection HttpUrlsUsage
     */
    private function request(string $command, ?int $projectId, int $pageId = null): ?array
    {
        $params = [
            'publickey' => $this->publicKey,
            'secretkey' => $this->secretKey,
            'projectid' => $projectId,
            'pageid' => $pageId,
        ];

        $url = "http://api.tildacdn.info/v1/$command/?" . http_build_query($params);
        $response = file_get_contents($url);

        if ($response) {
            $data = json_decode($response, true);
            if ($data['status'] == 'FOUND') {
                return $data['result'];
            }
        }

        return null;
    }
}
