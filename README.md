# Tilda API

Yii2 component

API https://help-ru.tilda.cc/api

```php
$tilda = new TildaApi([
    'publicKey' => '',
    'secretKey' => '',
    'projectId' => 1111111,
    'cacheDuration' => 86400,
]);

//Get HMTL and add CSS and JS to View
$html = $tilda->pageHtml($this->view, $tildaPageId);

//Clear cache
$tilda->clearCache($tildaPageId)
```